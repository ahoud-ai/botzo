<?php

use Illuminate\Database\Migrations\Migration;
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
        'Razorpay',
        'Pabbly Subscriptions',
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
        $this->refineAddons();
        $this->refinePlanMetadata();
        $this->refineTrialAddons();
    }

    public function down(): void
    {
        //
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

    private function refinePlanMetadata(): void
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

    private function refineTrialAddons(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $value = DB::table('settings')->where('key', 'trial_addons')->value('value');
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            return;
        }

        DB::table('settings')
            ->where('key', 'trial_addons')
            ->update(['value' => json_encode($this->sanitizeAddonMap($decoded))]);
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
