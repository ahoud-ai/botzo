<?php

namespace App\Services\Webhooks;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\PublicHost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sends signed event notifications to the customer's own server. The first attempt goes out at
 * once; failures are retried by the scheduler (see webhooks:retry) and a dead URL is switched off.
 */
class WebhookDispatcher
{
    private const TIMEOUT_SECONDS = 5;
    private const CONNECT_TIMEOUT_SECONDS = 3;
    private const KEEP_DAYS = 14;

    public function dispatch(int $organizationId, string $event, array $data): void
    {
        try {
            $endpoints = WebhookEndpoint::where('organization_id', $organizationId)
                ->where('is_active', true)
                ->get()
                ->filter(fn (WebhookEndpoint $endpoint) => $endpoint->listensTo($event));

            foreach ($endpoints as $endpoint) {
                $this->deliver($this->record($endpoint, $event, $data));
            }
        } catch (\Throwable $exception) {
            // A customer's webhook must never break message processing.
            report($exception);
        }
    }

    public function sendTest(WebhookEndpoint $endpoint): WebhookDelivery
    {
        $delivery = $this->record($endpoint, 'webhook.test', [
            'message' => __('Test event from Botzo. If you can read this, your webhook works.'),
            'timestamp' => now('UTC')->toIso8601String(),
        ]);
        $this->deliver($delivery, retry: false);

        return $delivery->fresh();
    }

    public function deliver(WebhookDelivery $delivery, bool $retry = true): void
    {
        $endpoint = $delivery->endpoint;
        if (! $endpoint || $endpoint->trashed() || (! $endpoint->is_active && $delivery->event !== 'webhook.test')) {
            $delivery->update(['status' => WebhookDelivery::STATUS_FAILED, 'next_attempt_at' => null, 'error' => 'endpoint_disabled']);

            return;
        }

        $delivery->attempts++;
        $startedAt = microtime(true);
        $responseStatus = null;
        $error = null;

        if (! PublicHost::urlIsSafe($endpoint->url)) {
            $error = 'blocked_address';
        } else {
            try {
                $timestamp = (string) time();
                $body = $delivery->payload;
                $response = Http::timeout(self::TIMEOUT_SECONDS)
                    ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                    ->withOptions(['allow_redirects' => false])
                    ->withHeaders([
                        'User-Agent' => 'Botzo-Webhooks/1.0',
                        'X-Botzo-Event' => $delivery->event,
                        'X-Botzo-Delivery' => $delivery->event_id,
                        'X-Botzo-Timestamp' => $timestamp,
                        'X-Botzo-Signature' => 'sha256='.self::sign($endpoint->secret(), $timestamp, $body),
                    ])
                    ->withBody($body, 'application/json')
                    ->post($endpoint->url);

                $responseStatus = $response->status();
                if (! $response->successful()) {
                    $error = 'http_'.$responseStatus;
                }
            } catch (\Throwable $exception) {
                $error = Str::limit('connection: '.$exception->getMessage(), 250, '');
            }
        }

        $delivery->response_status = $responseStatus;
        $delivery->response_ms = (int) round((microtime(true) - $startedAt) * 1000);

        if ($error === null) {
            $delivery->forceFill(['status' => WebhookDelivery::STATUS_SUCCEEDED, 'error' => null, 'next_attempt_at' => null, 'delivered_at' => now()])->save();
            $endpoint->forceFill(['consecutive_failures' => 0, 'last_success_at' => now()])->save();

            return;
        }

        $nextWait = $retry ? (WebhookDelivery::RETRY_MINUTES[$delivery->attempts - 1] ?? null) : null;
        $delivery->forceFill([
            'status' => $nextWait === null ? WebhookDelivery::STATUS_FAILED : WebhookDelivery::STATUS_PENDING,
            'error' => $error,
            'next_attempt_at' => $nextWait === null ? null : now()->addMinutes($nextWait),
        ])->save();

        if ($delivery->event === 'webhook.test') {
            return;
        }

        $failures = $endpoint->consecutive_failures + 1;
        $endpoint->forceFill(['consecutive_failures' => $failures, 'last_failure_at' => now()]);
        if ($failures >= WebhookEndpoint::DISABLE_AFTER_FAILURES && $endpoint->is_active) {
            $endpoint->forceFill(['is_active' => false, 'disabled_at' => now()]);
            Log::warning('Webhook endpoint disabled after repeated failures.', ['organization_id' => $endpoint->organization_id, 'endpoint' => $endpoint->uuid]);
        }
        $endpoint->save();
    }

    /** Retries whatever is due; run every minute by the scheduler. */
    public function retryDue(int $limit = 50): int
    {
        $due = WebhookDelivery::where('status', WebhookDelivery::STATUS_PENDING)
            ->whereNotNull('next_attempt_at')
            ->where('next_attempt_at', '<=', now())
            ->orderBy('next_attempt_at')
            ->limit($limit)
            ->get();

        $due->each(fn (WebhookDelivery $delivery) => $this->deliver($delivery));

        return $due->count();
    }

    public function prune(): int
    {
        return WebhookDelivery::where('created_at', '<', now()->subDays(self::KEEP_DAYS))->delete();
    }

    public static function sign(string $secret, string $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    private function record(WebhookEndpoint $endpoint, string $event, array $data): WebhookDelivery
    {
        $eventId = 'evt_'.Str::random(24);

        return WebhookDelivery::create([
            'event_id' => $eventId,
            'webhook_endpoint_id' => $endpoint->id,
            'organization_id' => $endpoint->organization_id,
            'event' => $event,
            'payload' => json_encode([
                'id' => $eventId,
                'type' => $event,
                'created_at' => now('UTC')->toIso8601String(),
                'data' => $data,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => WebhookDelivery::STATUS_PENDING,
        ]);
    }
}
