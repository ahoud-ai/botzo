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
        $timezone = 'UTC'; // Default to UTC
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
