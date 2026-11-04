<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Salla sends dates in several shapes: Riyadh time without an offset ("2026-11-04 19:40:19"),
 * ISO with an offset ("2021-10-09T21:00:00.000000Z"), a date alone ("2023-07-27") and
 * "Wed Jun 30 2021 12:16:25 GMT+0300 (Arabian Standard Time)".
 */
class SallaDate
{
    private const SALLA_TIMEZONE = 'Asia/Riyadh';

    public static function parse(mixed $value, bool $endOfDay = false): ?CarbonInterface
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $text = trim((string) preg_replace('/\s*\(.*\)\s*$/', '', $value));

        try {
            $date = Carbon::parse($text, self::SALLA_TIMEZONE);
        } catch (Throwable) {
            return null;
        }

        if ($endOfDay && preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1) {
            $date = $date->endOfDay();
        }

        return $date->setTimezone(config('app.timezone'));
    }
}
