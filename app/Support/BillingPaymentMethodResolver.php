<?php

namespace App\Support;

class BillingPaymentMethodResolver
{
    public static function fromProcessor(string $processor, array $payload = []): string
    {
        $normalized = self::normalizeCandidates([
            data_get($payload, 'payment_method'),
            data_get($payload, 'method'),
            data_get($payload, 'payment_method_type'),
            data_get($payload, 'payment_type'),
            data_get($payload, 'source.type'),
            data_get($payload, 'source.company'),
            data_get($payload, 'source.brand'),
            data_get($payload, 'source.name'),
            data_get($payload, 'source.network'),
            data_get($payload, 'source.card.brand'),
            data_get($payload, 'source.card.type'),
            data_get($payload, 'card.brand'),
            data_get($payload, 'card.type'),
            data_get($payload, 'data.payment_type'),
            data_get($payload, 'data.card.brand'),
            data_get($payload, 'data.card.type'),
            data_get($payload, 'authorization.brand'),
            data_get($payload, 'authorization.card_type'),
            data_get($payload, 'authorization.channel'),
            data_get($payload, 'payment_method_details.card.brand'),
            data_get($payload, 'payment_method_details.type'),
            data_get($payload, 'payment_method_types.0'),
        ]);

        foreach ($normalized as $candidate) {
            $resolved = self::resolveKeyword($candidate);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return match (strtolower(trim($processor))) {
            'bank' => 'Bank transfer',
            'manual' => 'Manual',
            'moyasar' => 'Card',
            default => 'Payment',
        };
    }

    public static function displayLabel(?string $storedMethod, ?string $processor = null): string
    {
        $storedMethod = trim((string) $storedMethod);

        if ($storedMethod !== '') {
            return self::resolveKeyword($storedMethod) ?? $storedMethod;
        }

        if ($processor !== null && trim($processor) !== '') {
            return self::fromProcessor($processor);
        }

        return 'Payment';
    }

    /**
     * @param array<int, mixed> $candidates
     * @return array<int, string>
     */
    private static function normalizeCandidates(array $candidates): array
    {
        return collect($candidates)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => strtolower(trim($value)))
            ->values()
            ->all();
    }

    private static function resolveKeyword(string $candidate): ?string
    {
        if (str_contains($candidate, 'apple')) {
            return 'Apple Pay';
        }

        if (str_contains($candidate, 'visa')) {
            return 'Visa';
        }

        if (str_contains($candidate, 'master')) {
            return 'Mastercard';
        }

        if (str_contains($candidate, 'mada')) {
            return 'Mada';
        }

        if (str_contains($candidate, 'amex') || str_contains($candidate, 'american express')) {
            return 'American Express';
        }

        if (str_contains($candidate, 'bank')) {
            return 'Bank transfer';
        }

        if (str_contains($candidate, 'manual')) {
            return 'Manual';
        }

        if (str_contains($candidate, 'card') || str_contains($candidate, 'credit')) {
            return 'Card';
        }

        return null;
    }
}
