<?php

namespace App\Support;

final class SaClientPlanProfile
{
    public const PLAN_ADDONS = [
        'Embedded Signup',
        'AI Assistant',
        'Flow builder',
    ];

    public const RETIRED_ADDONS = [
        'Web'.'hooks',
        'Meta '.'Messaging',
        'Messenger',
        'Insta'.'gram',
        'Google '.'Analytics',
        'Google '.'Recaptcha',
        'Google '.'Authenticator',
        'Google '.'Maps',
        'Woo'.'Commerce',
        'Woo'.'commerce',
        'Razor'.'pay',
        'Pabbly '.'Subscriptions',
    ];

    public const RETIRED_METADATA_KEYS = [
        'webhook_endpoints_limit',
        'webhook_monthly_deliveries_limit',
        'webhook_rate_limit_per_minute',
        'webhook_retry_attempt_limit',
        'marketing',
        'marketing_v2',
        'marketing_website',
        'mobile',
        'mobile'.'_app',
        'mobile'.'_push',
        'fire'.'base',
        'fire'.'base_credentials',
        'fire'.'base_project_id',
        'f'.'cm',
        'push_notifications',
        'notifications',
        'notification_center',
        'meta_messaging',
        'messaging_channels',
        'mess'.'enger',
        'insta'.'gram',
        'recaptcha',
        'google_recaptcha',
        'google_authenticator',
        'google_auth',
        'google_analytics',
        'google_tag_manager',
        'google_maps',
        'woo'.'commerce',
        'str'.'ipe',
        'pay'.'pal',
        'pay'.'stack',
        'flutter'.'wave',
        'razor'.'pay',
        'pabbly',
        'click'.'pay',
    ];

    public const RETIRED_METADATA_PREFIXES = [
        'webhook_',
        'marketing_',
        'mobile'.'_',
        'fire'.'base_',
        'f'.'cm_',
        'notification_',
        'meta_messaging_',
        'messaging_channel_',
        'messenger_',
        'instagram_',
        'recaptcha_',
        'google_',
        'woo'.'commerce_',
        'str'.'ipe_',
        'pay'.'pal_',
        'pay'.'stack_',
        'flutter'.'wave_',
        'razor'.'pay_',
        'pabbly_',
        'click'.'pay_',
    ];

    public static function planAddonNames(): array
    {
        return self::PLAN_ADDONS;
    }

    public static function retiredAddonNames(): array
    {
        return self::RETIRED_ADDONS;
    }

    public static function isPlanAddon(string $name): bool
    {
        return in_array($name, self::PLAN_ADDONS, true);
    }

    public static function isRetiredAddon(string $name): bool
    {
        return in_array($name, self::RETIRED_ADDONS, true);
    }

    public static function sanitizePlanMetadata(array $metadata): array
    {
        foreach (array_keys($metadata) as $key) {
            if (self::isRetiredMetadataKey((string) $key)) {
                unset($metadata[$key]);
            }
        }

        if (isset($metadata['addons']) && is_array($metadata['addons'])) {
            $metadata['addons'] = self::sanitizeAddonMap($metadata['addons']);
        }

        foreach ($metadata as $key => $value) {
            if (is_array($value)) {
                $metadata[$key] = self::sanitizePlanMetadata($value);
            }
        }

        return $metadata;
    }

    public static function isRetiredMetadataKey(string $key): bool
    {
        if (in_array($key, self::RETIRED_METADATA_KEYS, true)) {
            return true;
        }

        foreach (self::RETIRED_METADATA_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function sanitizeAddonMap(array $addons): array
    {
        $sanitized = [];

        foreach (self::PLAN_ADDONS as $addonName) {
            if (array_key_exists($addonName, $addons)) {
                $sanitized[$addonName] = self::normalizeBoolean($addons[$addonName]);
            }
        }

        return $sanitized;
    }

    public static function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
