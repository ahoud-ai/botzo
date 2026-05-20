<?php

namespace App\Services;

use App\Models\PaymentGateway;
use App\Resolvers\PaymentPlatformResolver;
use App\Support\SupportedPaymentProcessors;
use Illuminate\Support\Collection;

class PaymentProcessorAvailabilityService
{
    public function __construct(
        private readonly PaymentPlatformResolver $paymentPlatformResolver,
    ) {
    }

    public function availableMethodRows(): array
    {
        return PaymentGateway::query()
            ->get()
            ->filter(fn (PaymentGateway $gateway) => $this->isAvailable($gateway->name))
            ->map(fn (PaymentGateway $gateway) => [
                'name' => $gateway->name,
            ])
            ->values()
            ->all();
    }

    public function isAvailable(?string $processor): bool
    {
        return $this->availabilityStatus($processor) === 'available';
    }

    public function assertAvailable(?string $processor): void
    {
        $status = $this->availabilityStatus($processor);

        if ($status === 'available') {
            return;
        }

        throw new \InvalidArgumentException(match ($status) {
            'unsupported' => __('The selected payment processor is not supported.'),
            'inactive' => __('The selected payment method is not currently available.'),
            default => __('The selected payment method is not configured correctly right now.'),
        });
    }

    public function availabilityStatus(?string $processor): string
    {
        $normalized = SupportedPaymentProcessors::normalizeName((string) $processor);

        if (!SupportedPaymentProcessors::isSupported($normalized)) {
            return 'unsupported';
        }

        $gateway = $this->gatewayForProcessor($normalized);
        if (!$gateway || (int) $gateway->is_active !== 1) {
            return 'inactive';
        }

        $gatewayConfigurationStatus = $this->gatewayConfigurationStatus($normalized, $gateway);
        if ($gatewayConfigurationStatus !== 'available') {
            return $gatewayConfigurationStatus;
        }

        try {
            $this->paymentPlatformResolver->resolveService($normalized);

            return 'available';
        } catch (\Throwable) {
            return 'misconfigured';
        }
    }

    private function gatewayForProcessor(string $processor): ?PaymentGateway
    {
        $displayName = $this->displayNameForProcessor($processor);

        return PaymentGateway::query()
            ->where('name', $displayName)
            ->first();
    }

    private function gatewayConfigurationStatus(string $processor, PaymentGateway $gateway): string
    {
        if ($processor !== 'moyasar') {
            return 'available';
        }

        $metadata = $this->decodeMetadata($gateway->metadata);
        $activeMode = strtolower((string) ($metadata['active_mode'] ?? $metadata['mode'] ?? 'test'));
        $activeMode = in_array($activeMode, ['test', 'live'], true) ? $activeMode : 'test';
        $activeConfig = is_array($metadata[$activeMode] ?? null) ? $metadata[$activeMode] : $metadata;

        $secretKey = trim((string) ($activeConfig['secret_key'] ?? $metadata['secret_key'] ?? ''));
        $webhookSecret = trim((string) ($activeConfig['webhook_secret'] ?? $metadata['webhook_secret'] ?? ''));

        if ($secretKey === '' || $webhookSecret === '') {
            return 'misconfigured';
        }

        if (
            config('app.env') === 'production'
            && $activeMode !== 'live'
            && !config('app.allow_test_payment_mode_on_production', false)
        ) {
            return 'misconfigured';
        }

        return 'available';
    }

    private function decodeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (!is_string($metadata) || trim($metadata) === '') {
            return [];
        }

        $decoded = json_decode($metadata, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function displayNameForProcessor(string $processor): string
    {
        return match (SupportedPaymentProcessors::normalizeName($processor)) {
            'moyasar' => 'Moyasar',
            default => $processor,
        };
    }
}
