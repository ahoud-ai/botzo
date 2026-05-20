<?php

namespace App\Support;

final class SupportedPaymentProcessors
{
    private const PROCESSORS = [
        'moyasar',
    ];

    public static function all(): array
    {
        return self::PROCESSORS;
    }

    public static function values(): array
    {
        return self::all();
    }

    public static function contains(?string $processor): bool
    {
        if ($processor === null) {
            return false;
        }

        return in_array(self::normalize($processor), self::PROCESSORS, true);
    }

    public static function isSupported(?string $processor): bool
    {
        return self::contains($processor);
    }

    public static function normalize(string $processor): string
    {
        $normalized = strtolower(trim($processor));
        $normalized = preg_replace('/[\s_-]+/', '', $normalized);

        return $normalized ?? strtolower(trim($processor));
    }

    public static function normalizeName(string $processor): string
    {
        return self::normalize($processor);
    }

    public static function filterMethodRows(array $rows): array
    {
        $filtered = [];
        $seen = [];

        foreach ($rows as $row) {
            $name = is_array($row) ? ($row['name'] ?? null) : $row;
            if (!is_string($name) || !self::contains($name)) {
                continue;
            }

            $normalized = self::normalize($name);
            if (isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $filtered[] = ['name' => $name];
        }

        return $filtered;
    }

    public static function filterMethods(array $rows): array
    {
        return self::filterMethodRows($rows);
    }
}
