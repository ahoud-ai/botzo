<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->retireModuleTables();
        $this->prepareSettings();
        $this->preparePaymentGateways();
        $this->prepareAddons();
        $this->prepareSubscriptionPlans();
        $this->preparePermissions();
    }

    public function down(): void
    {
        //
    }

    private function retireModuleTables(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'marketing_content_revisions',
            'marketing_navigation_items',
            'marketing_navigation_groups',
            'marketing_publish_snapshots',
            'marketing_contact_leads',
            'marketing_success_companies',
            'marketing_knowledge_articles',
            'marketing_knowledge_categories',
            'marketing_blog_posts',
            'marketing_blog_categories',
            'marketing_sections',
            'marketing_pages',
            'marketing_sectors',
            'marketing_media_assets',
            'notification_recipients',
            'notification_preferences',
            'notification_events',
            'admin_announcements',
            'notifications',
            'mobile_push_devices',
            'mobile_message_idempotencies',
            'personal_access_tokens',
            'webhook_delivery_attempts',
            'webhook_deliveries',
            'webhook_endpoints',
            'messaging_channel_webhook_events',
            'messaging_channel_messages',
            'messaging_channel_identities',
            'messaging_channel_accounts',
            'messaging_channel_integrations',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    private function prepareSettings(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        foreach ([
            'currency' => 'SAR',
            'timezone' => 'Asia/Riyadh',
            'country' => 'Saudi Arabia',
            'country_code' => 'SA',
            'phone_country' => 'SA',
            'phone_code' => '+966',
        ] as $key => $value) {
            DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value]);
        }

        DB::table('settings')
            ->where('key', 'frontend_variant')
            ->where('value', 'marketing_v2')
            ->update(['value' => 'premium']);

        if (! DB::table('settings')->where('key', 'frontend_variant')->exists()) {
            DB::table('settings')->insert(['key' => 'frontend_variant', 'value' => 'premium']);
        }

        $retiredExactKeys = [
            'frontend_marketing_navigation',
            'frontend_marketing_settings',
            'is_meta_messaging_active',
            'meta_messaging_app_id',
            'meta_messaging_app_secret',
            'meta_messaging_verify_token',
            'meta_messaging_manual_connect_enabled',
            'mobile_app_enabled',
            'mobile_push_enabled',
            'firebase_credentials',
            'firebase_project_id',
            'notification_center_enabled',
            'admin_announcements_enabled',
            'stripe_publishable_key',
            'stripe_secret_key',
            'paypal_client_id',
            'paypal_secret',
            'paystack_public_key',
            'paystack_secret_key',
            'flutterwave_public_key',
            'flutterwave_secret_key',
            'razorpay_key_id',
            'razorpay_key_secret',
            'pabbly_webhook_secret',
        ];

        DB::table('settings')->whereIn('key', $retiredExactKeys)->delete();

        foreach ([
            'marketing\_%',
            'frontend\_marketing\_%',
            'meta\_messaging\_%',
            'messaging\_channel\_%',
            'mobile\_%',
            'notification\_%',
            'admin\_announcement\_%',
            'firebase\_%',
            'stripe\_%',
            'paypal\_%',
            'paystack\_%',
            'flutterwave\_%',
            'razorpay\_%',
            'pabbly\_%',
            'click'.'pay\_%',
        ] as $pattern) {
            DB::table('settings')->where('key', 'like', $pattern)->delete();
        }

        $this->cleanJsonSetting('trial_addons');
    }

    private function preparePaymentGateways(): void
    {
        if (! Schema::hasTable('payment_gateways')) {
            return;
        }

        DB::table('payment_gateways')
            ->whereRaw('LOWER(name) <> ?', ['moyasar'])
            ->delete();

        DB::table('payment_gateways')
            ->whereRaw('LOWER(name) = ?', ['moyasar'])
            ->update(['name' => 'Moyasar', 'updated_at' => now()]);

        DB::table('payment_gateways')->updateOrInsert(
            ['name' => 'Moyasar'],
            [
                'metadata' => DB::table('payment_gateways')->whereRaw('LOWER(name) = ?', ['moyasar'])->value('metadata') ?: json_encode([]),
                'is_active' => DB::table('payment_gateways')->whereRaw('LOWER(name) = ?', ['moyasar'])->value('is_active') ?? 0,
                'updated_at' => now(),
                'created_at' => DB::table('payment_gateways')->whereRaw('LOWER(name) = ?', ['moyasar'])->value('created_at') ?? now(),
            ]
        );
    }

    private function prepareAddons(): void
    {
        if (! Schema::hasTable('addons')) {
            return;
        }

        DB::table('addons')->whereIn('name', [
            'Webhooks',
            'Meta Messaging',
            'Razorpay',
            'Pabbly Subscriptions',
        ])->delete();
    }

    private function prepareSubscriptionPlans(): void
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

                    $metadata = $this->cleanRetiredPayload($metadata);

                    DB::table('subscription_plans')
                        ->where('id', $plan->id)
                        ->update(['metadata' => json_encode($metadata)]);
                }
            });
    }

    private function preparePermissions(): void
    {
        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')
                ->whereIn('module', [
                    'webhooks',
                    'notifications',
                    'notification',
                    'mobile',
                    'marketing',
                    'marketing_website',
                    'meta_messaging',
                    'messaging_channels',
                    'payment_gateways_previous',
                ])
                ->delete();
        }

        if (! Schema::hasTable('organization_roles') || ! Schema::hasColumn('organization_roles', 'permissions')) {
            return;
        }

        DB::table('organization_roles')
            ->select(['id', 'permissions'])
            ->orderBy('id')
            ->chunkById(100, function ($roles): void {
                foreach ($roles as $role) {
                    $permissions = json_decode((string) $role->permissions, true);
                    if (! is_array($permissions)) {
                        continue;
                    }

                    $filtered = array_values(array_filter($permissions, function ($permission): bool {
                        $permission = strtolower((string) $permission);

                        foreach ([
                            'webhooks.',
                            'notifications.',
                            'notification.',
                            'mobile.',
                            'marketing.',
                            'marketing_website.',
                            'meta_messaging.',
                            'messaging_channels.',
                        ] as $prefix) {
                            if (str_starts_with($permission, $prefix)) {
                                return false;
                            }
                        }

                        return true;
                    }));

                    if ($filtered !== $permissions) {
                        DB::table('organization_roles')
                            ->where('id', $role->id)
                            ->update(['permissions' => json_encode($filtered)]);
                    }
                }
            });
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

        DB::table('settings')
            ->where('key', $key)
            ->update(['value' => json_encode($this->cleanRetiredPayload($decoded))]);
    }

    private function cleanRetiredPayload(array $payload): array
    {
        foreach ([
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
        ] as $key) {
            unset($payload[$key]);
        }

        if (isset($payload['addons']) && is_array($payload['addons'])) {
            foreach ([
                'Webhooks',
                'Meta Messaging',
                'Razorpay',
                'Pabbly Subscriptions',
            ] as $addon) {
                unset($payload['addons'][$addon]);
            }
        }

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->cleanRetiredPayload($value);
            }
        }

        return $payload;
    }
};
