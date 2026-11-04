<?php

namespace App\Services\Salla;

use App\Models\SallaStore;
use App\Models\SallaWebhookEvent;
use App\Support\SallaDate;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Records a verified Salla event once and applies it. A failure is rethrown so the controller
 * answers 500 and Salla delivers the same body again.
 */
class SallaWebhookProcessor
{
    public function __construct(
        private readonly SallaStoreInstaller $installer,
        private readonly SallaSubscriptions $subscriptions,
    ) {
    }

    public function handle(string $rawBody, array $payload): void
    {
        $event = (string) ($payload['event'] ?? '');
        $merchantId = (int) ($payload['merchant'] ?? 0);

        if (! $this->handles($event) || $merchantId <= 0) {
            $this->recordOnly($rawBody, $event, $merchantId, $payload);

            return;
        }

        // The lock also keeps a token refresh from running while new tokens are being saved.
        Cache::lock(SallaStore::lockKey($merchantId), 60)->block(20, function () use ($rawBody, $event, $merchantId, $payload) {
            $record = $this->record($rawBody, $event, $merchantId, $payload, SallaWebhookEvent::STATUS_RECEIVED);

            if ($record->status === SallaWebhookEvent::STATUS_PROCESSED || $record->status === SallaWebhookEvent::STATUS_IGNORED) {
                return;
            }

            try {
                $applied = $this->apply($event, $merchantId, $payload);
            } catch (Throwable $exception) {
                // The class name only: messages from HTTP clients can carry request details.
                $record->update(['status' => SallaWebhookEvent::STATUS_FAILED, 'error' => Str::limit($exception::class, 250, '')]);

                throw $exception;
            }

            $record->update([
                'status' => $applied ? SallaWebhookEvent::STATUS_PROCESSED : SallaWebhookEvent::STATUS_IGNORED,
                'error' => null,
                'processed_at' => now(),
            ]);
        });
    }

    private function apply(string $event, int $merchantId, array $payload): bool
    {
        $occurredAt = SallaDate::parse($payload['created_at'] ?? null) ?? now();

        return match ($event) {
            'app.store.authorize' => $this->installer->authorize($merchantId, (array) ($payload['data'] ?? []), $occurredAt) !== null,
            'app.uninstalled' => $this->installer->uninstall($merchantId, $occurredAt),
            default => $this->subscriptions->apply($event, $merchantId, (array) ($payload['data'] ?? []), $occurredAt),
        };
    }

    private function handles(string $event): bool
    {
        return in_array($event, ['app.store.authorize', 'app.uninstalled', ...SallaSubscriptions::EVENTS], true);
    }

    private function recordOnly(string $rawBody, string $event, int $merchantId, array $payload): void
    {
        $this->record($rawBody, $event, $merchantId, $payload, SallaWebhookEvent::STATUS_IGNORED);
    }

    private function record(string $rawBody, string $event, int $merchantId, array $payload, string $status): SallaWebhookEvent
    {
        $hash = hash('sha256', $rawBody);

        try {
            return SallaWebhookEvent::query()->firstOrCreate(['event_hash' => $hash], [
                'event' => Str::limit($event !== '' ? $event : 'unknown', 64, ''),
                'merchant_id' => $merchantId > 0 ? $merchantId : null,
                'payload' => Str::startsWith($event, 'app.') ? $this->withoutSecrets($payload) : null,
                'status' => $status,
            ]);
        } catch (QueryException) {
            // Two deliveries of one event raced; the other one stored it.
            return SallaWebhookEvent::query()->where('event_hash', $hash)->firstOrFail();
        }
    }

    /** Removes anything that looks like a credential, at any depth. */
    private function withoutSecrets(array $payload): array
    {
        $clean = [];

        foreach ($payload as $key => $value) {
            if (is_string($key) && Str::contains(Str::lower($key), ['token', 'secret', 'password'])) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->withoutSecrets($value) : $value;
        }

        return $clean;
    }
}
