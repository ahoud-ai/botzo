<?php

namespace App\Support;

/**
 * Collects how long the slow parts of a request took (Meta, OpenAI), so developer API responses can
 * report them in a Server-Timing header and a slow request shows whose side the time was spent on.
 */
class ServerTiming
{
    private static array $metrics = [];

    public static function reset(): void
    {
        self::$metrics = [];
    }

    public static function add(string $name, float $milliseconds): void
    {
        self::$metrics[$name] = (self::$metrics[$name] ?? 0) + $milliseconds;
    }

    public static function header(float $totalMilliseconds): string
    {
        $parts = ['app;dur='.round($totalMilliseconds, 1)];
        foreach (self::$metrics as $name => $milliseconds) {
            $parts[] = $name.';dur='.round($milliseconds, 1);
        }

        return implode(', ', $parts);
    }
}
