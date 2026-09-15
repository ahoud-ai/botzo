<?php 

namespace App\Helpers;

use App\Models\Organization;
use App\Services\SettingValueService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class DateTimeHelper
{
    public static function formatDate(string $dateTimeString)
    {
        $dt = Carbon::create($dateTimeString);
        $settings = app(SettingValueService::class);
        $dateFormat = $settings->getString('date_format', 'Y-m-d');
        $timeFormat = $settings->getString('time_format', 'H:i');

        return $dt->format($dateFormat . ' ' . $timeFormat); 
    }

    public static function convertToOrganizationTimezone($date)
    {
        // Fall back to the app's configured timezone (not UTC) so chats display
        // in local time by default — most organizations never set a per-org
        // timezone in their metadata, so this fallback is the common case.
        $timezone = config('app.timezone', 'UTC');
        $organizationId = session()->get('current_organization');

        if ($organizationId) {
            $organization = Organization::find($organizationId);
            if ($organization) {
                $metadata = $organization->metadata;
                $metadata = isset($metadata) ? json_decode($metadata, true) : null;

                if ($metadata && isset($metadata['timezone'])) {
                    $timezone = $metadata['timezone'];
                }
            }
        }

        // NOTE: $date's stored source timezone varies by caller/model (some are
        // UTC, some use standard Eloquent timestamps in the app's local zone) —
        // this shared helper can't assume one. Callers whose column is known to
        // be stored in UTC should pass an already UTC-tagged Carbon instance
        // (e.g. Carbon::parse($raw, 'UTC')) rather than a raw string, since
        // parse() preserves an existing DateTime's timezone instead of guessing.
        return Carbon::parse($date)->setTimezone($timezone);
    }

    public static function convertToCompanyTimezone($date)
    {
        $timezone = app(SettingValueService::class)->getString('timezone', 'UTC');

        return Carbon::parse($date)->setTimezone($timezone);
    }

    public static function formatDateWithoutHours($date)
    {
        return $date->format('d M Y'); // Format without hours, minutes, and seconds
    }
}
