<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PLAN_ADDONS = [
        'Embedded Signup',
        'AI Assistant',
        'Flow builder',
    ];

    private const RETIRED_ADDONS = [
        'Webhooks',
        'Meta Messaging',
        'Messenger',
        'Instagram',
        'Google Analytics',
        'Google Recaptcha',
        'Google Authenticator',
        'Google Maps',
        'WooCommerce',
        'Woocommerce',
        'Razorpay',
        'Pabbly Subscriptions',
    ];

    private const RETIRED_SETTINGS = [
        'google_analytics_status',
        'google_analytics_tracking_id',
        'tracking_google_tag_manager_id',
        'google_maps_api_key',
        'google_auth_active',
        'recaptcha_active',
        'recaptcha_secret_key',
        'recaptcha_site_key',
        'woocommerce_plugin_link',
        'woocommerce_plugin_name',
        'is_meta_messaging_active',
        'meta_messaging_app_id',
        'meta_messaging_app_secret',
        'meta_messaging_verify_token',
        'meta_messaging_manual_connect_enabled',
    ];

    private const RETIRED_SETTING_PATTERNS = [
        'google\_analytics\_%',
        'tracking\_google\_%',
        'google\_maps\_%',
        'google\_auth\_%',
        'recaptcha\_%',
        'woocommerce\_%',
        'meta\_messaging\_%',
        'messaging\_channel\_%',
        'messenger\_%',
        'instagram\_%',
    ];

    private const RETIRED_METADATA_KEYS = [
        'webhook_endpoints_limit',
        'webhook_monthly_deliveries_limit',
        'webhook_rate_limit_per_minute',
        'webhook_retry_attempt_limit',
        'marketing',
        'marketing_v2',
        'marketing_website',
        'mobile',
        'mobile_app',
        'mobile_push',
        'notifications',
        'notification_center',
        'meta_messaging',
        'messaging_channels',
        'messenger',
        'instagram',
        'recaptcha',
        'google_recaptcha',
        'google_authenticator',
        'google_auth',
        'google_analytics',
        'google_tag_manager',
        'google_maps',
        'woocommerce',
        'stripe',
        'paypal',
        'paystack',
        'flutterwave',
        'razorpay',
        'pabbly',
        'click'.'pay',
    ];

    public function up(): void
    {
        $this->refineSettings();
        $this->refineAddons();
        $this->refineSubscriptionPlans();
        $this->refineUserSecurityColumns();
    }

    public function down(): void
    {
        //
    }

    private function refineSettings(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->whereIn('key', self::RETIRED_SETTINGS)->delete();

        foreach (self::RETIRED_SETTING_PATTERNS as $pattern) {
            DB::table('settings')->where('key', 'like', $pattern)->delete();
        }

        $this->cleanJsonSetting('socials');
        $this->cleanJsonSetting('trial_addons');
    }

    private function refineAddons(): void
    {
        if (! Schema::hasTable('addons')) {
            return;
        }

        DB::table('addons')->whereIn('name', self::RETIRED_ADDONS)->delete();

        if (Schema::hasColumn('addons', 'is_plan_restricted')) {
            DB::table('addons')
                ->whereNotIn('name', self::PLAN_ADDONS)
                ->where('is_plan_restricted', 1)
                ->update(['is_plan_restricted' => 0]);
        }
    }

    private function refineSubscriptionPlans(): void
    {
        if (! Schema::hasTable('subscription_plans')) {
            return;
        }

        DB::table('subscription_plans')
            ->select(['id', 'metadata'])
            ->orderBy('id')
            ->chunkById(100, function ($plans): void {
                foreach ($plans as $plan) {
                    $metadata = json_decode((string) $plan->metadata, true);
                    if (! is_array($metadata)) {
                        $metadata = [];
                    }

                    DB::table('subscription_plans')
                        ->where('id', $plan->id)
                        ->update(['metadata' => json_encode($this->sanitizePlanMetadata($metadata))]);
                }
            });
    }

    private function refineUserSecurityColumns(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        foreach (['tfa_secret', 'tfa'] as $column) {
            if (! Schema::hasColumn('users', $column)) {
                continue;
            }

            Schema::table('users', function (Blueprint $table) use ($column): void {
                $table->dropColumn($column);
            });
        }
    }

    private function cleanJsonSetting(string $key): void
    {
        $value = DB::table('settings')->where('key', $key)->value('value');
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            return;
        }

        $cleaned = $key === 'trial_addons'
            ? $this->sanitizeAddonMap($decoded)
            : $this->sanitizePlanMetadata($decoded);

        DB::table('settings')
            ->where('key', $key)
            ->update(['value' => json_encode($cleaned)]);
    }

    private function sanitizePlanMetadata(array $metadata): array
    {
        foreach (self::RETIRED_METADATA_KEYS as $key) {
            unset($metadata[$key]);
        }

        if (isset($metadata['addons']) && is_array($metadata['addons'])) {
            $metadata['addons'] = $this->sanitizeAddonMap($metadata['addons']);
        }

        foreach ($metadata as $key => $value) {
            if (is_array($value)) {
                $metadata[$key] = $this->sanitizePlanMetadata($value);
            }
        }

        return $metadata;
    }

    private function sanitizeAddonMap(array $addons): array
    {
        $sanitized = [];

        foreach (self::PLAN_ADDONS as $addonName) {
            if (array_key_exists($addonName, $addons)) {
                $sanitized[$addonName] = $this->normalizeBoolean($addons[$addonName]);
            }
        }

        return $sanitized;
    }

    private function normalizeBoolean(mixed $value): bool
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
};
