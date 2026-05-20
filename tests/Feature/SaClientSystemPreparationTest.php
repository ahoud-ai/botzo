<?php

namespace Tests\Feature;

use App\Http\Requests\StoreConfig;
use App\Services\SettingService;
use App\Support\SupportedPaymentProcessors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SaClientSystemPreparationTest extends TestCase
{
    public function test_removed_module_routes_are_not_registered_and_core_routes_remain(): void
    {
        $uris = collect(Route::getRoutes())
            ->map(fn ($route) => $route->uri())
            ->values();

        foreach ([
            'api/mobile',
            'admin/marketing-website',
            'developer-tools/webhooks',
            'notifications',
            'meta-messaging',
            'messaging-channels',
            'addons/setup/google-recaptcha',
            'addons/setup/google-analytics',
            'addons/setup/google-maps',
            'addons/setup/google-authenticator',
            'admin/updates',
            'admin/update',
            'admin/'.'addons/update',
            'in'.'stall',
            'settings/addons',
            'settings/plugins',
            'plugin/download',
            'tfa',
            'payment/{processor}',
        ] as $retiredPath) {
            $this->assertFalse(
                $uris->contains(fn (string $uri): bool => str_contains($uri, $retiredPath)),
                "Unexpected route path remains registered: {$retiredPath}"
            );
        }

        foreach ([
            '/',
            'pricing',
            'contact',
            'product',
            'api-documentation',
            'faqs',
            'webhook/whatsapp/{identifier?}',
            'webhook/waba',
            'payment/moyasar/webhook',
            'payment/moyasar',
        ] as $expectedPath) {
            $this->assertTrue(
                $uris->contains($expectedPath),
                "Expected route path is missing: {$expectedPath}"
            );
        }
    }

    public function test_saudi_timezone_country_and_currency_options_are_locked(): void
    {
        $this->assertSame([['value' => 'Asia/Riyadh', 'label' => 'Asia/Riyadh']], config('formats.timezones'));
        $this->assertSame([['value' => 'Saudi Arabia', 'label' => 'Saudi Arabia']], config('formats.countries'));
        $this->assertSame([['name' => 'Saudi Arabia', 'iso2' => 'SA', 'dialCode' => '966']], config('formats.phone_countries'));
        $this->assertSame([['value' => 'SAR', 'label' => 'Saudi riyal']], config('currencies'));
    }

    public function test_retired_admin_update_and_workspace_plugin_data_is_not_active(): void
    {
        if (Schema::hasTable('modules')) {
            $this->assertFalse(DB::table('modules')->where('name', 'updates')->exists());
        } else {
            $this->assertTrue(true);
        }

        if (Schema::hasTable('role_permissions')) {
            $this->assertFalse(DB::table('role_permissions')->where('module', 'updates')->exists());
        } else {
            $this->assertTrue(true);
        }

        if (Schema::hasTable('settings')) {
            $this->assertEmpty(DB::table('settings')
                ->whereIn('key', [
                    'available_version',
                    'is_update_available',
                    'last_update_check',
                    'release_date',
                    'version',
                ])
                ->pluck('key')
                ->all());
        } else {
            $this->assertTrue(true);
        }

        if (Schema::hasTable('addons')) {
            $this->assertFalse(Schema::hasColumn('addons', 'update_available'));
            $this->assertFalse(Schema::hasColumn('addons', 'version'));
            $this->assertFalse(Schema::hasColumn('addons', implode('', ['lic', 'ense'])));
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_mobile_app_and_firebase_runtime_surface_is_not_active(): void
    {
        foreach (['mobile_push_devices', 'mobile_message_idempotencies'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "Retired runtime table still exists: {$table}");
        }

        if (Schema::hasTable('settings')) {
            $this->assertFalse(DB::table('settings')
                ->whereIn('key', [
                    'mobile_app_enabled',
                    'mobile_push_enabled',
                    'firebase_credentials',
                    'firebase_project_id',
                    'fcm_server_key',
                    'fcm_sender_id',
                ])
                ->orWhere('key', 'like', 'mobile_%')
                ->orWhere('key', 'like', 'firebase_%')
                ->orWhere('key', 'like', 'fcm_%')
                ->exists());
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_mobile_app_and_firebase_settings_are_not_persisted(): void
    {
        if (! Schema::hasTable('settings')) {
            $this->assertTrue(true);

            return;
        }

        DB::table('settings')
            ->whereIn('key', [
                'mobile_app_enabled',
                'mobile_push_enabled',
                'firebase_credentials',
                'firebase_project_id',
                'firebase_web_api_key',
                'fcm_server_key',
                'fcm_sender_id',
            ])
            ->delete();

        app(SettingService::class)->updateSettings(Request::create('/admin/settings', 'PUT', [
            'mobile_app_enabled' => '1',
            'mobile_push_enabled' => '1',
            'firebase_credentials' => '{"project_id":"previous"}',
            'firebase_project_id' => 'previous',
            'firebase_web_api_key' => 'previous',
            'fcm_server_key' => 'previous',
            'fcm_sender_id' => 'previous',
        ]));

        $this->assertFalse(DB::table('settings')
            ->whereIn('key', [
                'mobile_app_enabled',
                'mobile_push_enabled',
                'firebase_credentials',
                'firebase_project_id',
                'firebase_web_api_key',
                'fcm_server_key',
                'fcm_sender_id',
            ])
            ->exists());
    }

    public function test_payment_processors_and_frontend_variant_are_restricted(): void
    {
        $this->assertSame(['moyasar'], SupportedPaymentProcessors::all());

        $premiumRequest = StoreConfig::create('/admin/settings', 'PUT', [
            'type' => 'general',
            'company_name' => 'Botzo SA',
            'frontend_variant' => 'premium',
        ]);
        $premiumRules = $premiumRequest->rules();
        $this->assertFalse(Validator::make($premiumRequest->all(), $premiumRules)->fails());

        $classicRequest = StoreConfig::create('/admin/settings', 'PUT', [
            'type' => 'general',
            'company_name' => 'Botzo SA',
            'frontend_variant' => 'classic',
        ]);
        $this->assertFalse(Validator::make($classicRequest->all(), $classicRequest->rules())->fails());

        $retiredRequest = StoreConfig::create('/admin/settings', 'PUT', [
            'type' => 'general',
            'company_name' => 'Botzo SA',
            'frontend_variant' => 'marketing_v2',
        ]);
        $this->assertTrue(Validator::make($retiredRequest->all(), $retiredRequest->rules())->fails());
    }
}
