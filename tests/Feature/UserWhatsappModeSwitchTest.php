<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckSubscriptionStatus;
use App\Models\Addon;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use App\Services\Whatsapp\WhatsappTokenVault;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

class UserWhatsappModeSwitchTest extends TestCase
{
    use DatabaseTransactions;

    public function test_toggle_off_switches_existing_embedded_integration_to_manual_mode(): void
    {
        [$user, $organization] = $this->createUserAndOrganization([
            'addons' => [
                'embedded_signup_enabled' => true,
            ],
            'whatsapp' => [
                'is_embedded_signup' => 1,
                'token_source' => 'embedded_signup',
                'access_token_encrypted' => (new WhatsappTokenVault())->encryptToken('EAAB-test-token'),
                'app_id' => 'app-123',
                'phone_number_id' => 'phone-123',
                'waba_id' => 'waba-123',
            ],
        ]);

        $this->seedPusherSettings();
        $this->seedEmbeddedSignupAddonAndGlobalFlag(true);

        Http::fake([
            'https://graph.facebook.com/*/waba-123/subscribed_apps' => Http::response([
                'success' => true,
            ], 200),
        ]);

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/settings/features/embedded-signup/toggle', [
                'enabled' => false,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status', function ($status): bool {
            return is_array($status)
                && ($status['type'] ?? null) === 'success'
                && ($status['message'] ?? null) === __('Embedded signup was disabled and your integration was switched to manual mode.');
        });

        $organization->refresh();
        $metadata = json_decode($organization->metadata ?? '{}', true);

        $this->assertIsArray($metadata);
        $this->assertFalse((bool) data_get($metadata, 'addons.embedded_signup_enabled'));
        $this->assertSame(0, (int) data_get($metadata, 'whatsapp.is_embedded_signup'));
        $this->assertSame('manual', data_get($metadata, 'whatsapp.token_source'));
    }

    public function test_toggle_off_without_whatsapp_integration_sets_auto_open_manual_setup_flag(): void
    {
        [$user, $organization] = $this->createUserAndOrganization([
            'addons' => [
                'embedded_signup_enabled' => true,
            ],
        ]);

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/settings/features/embedded-signup/toggle', [
                'enabled' => false,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('whatsapp_auto_open_manual_setup', true);

        $organization->refresh();
        $metadata = json_decode($organization->metadata ?? '{}', true);

        $this->assertIsArray($metadata);
        $this->assertFalse((bool) data_get($metadata, 'addons.embedded_signup_enabled'));
    }

    public function test_toggle_off_switches_to_manual_even_when_webhook_override_fails(): void
    {
        [$user, $organization] = $this->createUserAndOrganization([
            'addons' => [
                'embedded_signup_enabled' => true,
            ],
            'whatsapp' => [
                'is_embedded_signup' => 1,
                'token_source' => 'embedded_signup',
                'access_token_encrypted' => (new WhatsappTokenVault())->encryptToken('EAAB-test-token'),
                'app_id' => 'app-123',
                'phone_number_id' => 'phone-123',
                'waba_id' => 'waba-123',
            ],
        ]);

        $this->seedPusherSettings();
        $this->seedEmbeddedSignupAddonAndGlobalFlag(true);

        Http::fake([
            'https://graph.facebook.com/*/waba-123/subscribed_apps' => Http::response([
                'error' => [
                    'message' => __('Webhook override failed'),
                ],
            ], 500),
        ]);

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/settings/features/embedded-signup/toggle', [
                'enabled' => false,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status', function ($status): bool {
            return is_array($status)
                && ($status['type'] ?? null) === 'warning'
                && ($status['message'] ?? null) === __('Embedded signup was disabled, but automatic webhook switch failed. Please complete manual WhatsApp setup.');
        });
        $response->assertSessionHas('whatsapp_auto_open_manual_setup', true);

        $organization->refresh();
        $metadata = json_decode($organization->metadata ?? '{}', true);

        $this->assertIsArray($metadata);
        $this->assertFalse((bool) data_get($metadata, 'addons.embedded_signup_enabled'));
        $this->assertSame(0, (int) data_get($metadata, 'whatsapp.is_embedded_signup'));
        $this->assertSame('manual', data_get($metadata, 'whatsapp.token_source'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_manual_store_forces_manual_mode_after_previous_embedded_integration(): void
    {
        [$user, $organization] = $this->createUserAndOrganization([
            'addons' => [
                'embedded_signup_enabled' => true,
            ],
            'whatsapp' => [
                'is_embedded_signup' => 1,
                'token_source' => 'embedded_signup',
                'access_token_encrypted' => (new WhatsappTokenVault())->encryptToken('EAAB-old-embedded-token'),
                'app_id' => 'embedded-app-id',
                'phone_number_id' => 'embedded-phone-id',
                'waba_id' => 'embedded-waba-id',
            ],
        ]);

        $this->mockWhatsappServiceForManualStore();

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/settings/whatsapp', [
                'app_id' => 'manual-app-id',
                'access_token' => 'EAAB-manual-token',
                'phone_number_id' => 'manual-phone-id',
                'waba_id' => 'manual-waba-id',
            ]);

        $response->assertStatus(302);

        $organization->refresh();
        $metadata = json_decode($organization->metadata ?? '{}', true);

        $this->assertIsArray($metadata);
        $this->assertSame(0, (int) data_get($metadata, 'whatsapp.is_embedded_signup'));
        $this->assertSame('manual', data_get($metadata, 'whatsapp.token_source'));
        $this->assertSame('manual-app-id', data_get($metadata, 'whatsapp.app_id'));
        $this->assertSame('new-phone-id', data_get($metadata, 'whatsapp.phone_number_id'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_manual_store_handles_missing_optional_meta_phone_fields(): void
    {
        [$user, $organization] = $this->createUserAndOrganization([
            'addons' => [
                'embedded_signup_enabled' => true,
            ],
        ]);

        $this->mockWhatsappServiceForManualStore([
            'id' => 'sparse-phone-id',
        ]);

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/settings/whatsapp', [
                'app_id' => 'manual-app-id',
                'access_token' => 'EAAB-manual-token',
                'phone_number_id' => 'manual-phone-id',
                'waba_id' => 'manual-waba-id',
            ]);

        $response->assertStatus(302);

        $organization->refresh();
        $metadata = json_decode($organization->metadata ?? '{}', true);

        $this->assertIsArray($metadata);
        $this->assertSame('sparse-phone-id', data_get($metadata, 'whatsapp.phone_number_id'));
        $this->assertNull(data_get($metadata, 'whatsapp.display_phone_number'));
        $this->assertNull(data_get($metadata, 'whatsapp.verified_name'));
        $this->assertNull(data_get($metadata, 'whatsapp.quality_rating'));
        $this->assertNull(data_get($metadata, 'whatsapp.name_status'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_update_token_forces_manual_mode_after_previous_embedded_integration(): void
    {
        [$user, $organization] = $this->createUserAndOrganization([
            'addons' => [
                'embedded_signup_enabled' => true,
            ],
            'whatsapp' => [
                'is_embedded_signup' => 1,
                'token_source' => 'embedded_signup',
                'access_token_encrypted' => (new WhatsappTokenVault())->encryptToken('EAAB-old-embedded-token'),
                'app_id' => 'embedded-app-id',
                'phone_number_id' => 'embedded-phone-id',
                'waba_id' => 'embedded-waba-id',
            ],
        ]);

        $this->mockWhatsappServiceForManualStore();

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/settings/whatsapp/token', [
                'access_token' => 'EAAB-updated-manual-token',
            ]);

        $response->assertStatus(302);

        $organization->refresh();
        $metadata = json_decode($organization->metadata ?? '{}', true);

        $this->assertIsArray($metadata);
        $this->assertSame(0, (int) data_get($metadata, 'whatsapp.is_embedded_signup'));
        $this->assertSame('manual', data_get($metadata, 'whatsapp.token_source'));
        $this->assertSame('embedded-app-id', data_get($metadata, 'whatsapp.app_id'));
        $this->assertSame('new-phone-id', data_get($metadata, 'whatsapp.phone_number_id'));
    }

    private function createUserAndOrganization(array $metadata): array
    {
        $user = User::create([
            'first_name' => 'User',
            'last_name' => 'Tester',
            'email' => 'user+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $organization = Organization::create([
            'identifier' => 'org-'.Str::lower(Str::random(8)),
            'name' => 'Org '.Str::random(4),
            'created_by' => $user->id,
            'metadata' => json_encode($metadata),
        ]);

        $ownerRole = OrganizationRole::firstOrCreate(
            [
                'organization_id' => null,
                'name' => 'Owner',
            ],
            [
                'description' => __('Universal owner role'),
                'permissions' => ['*'],
            ]
        );

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        return [$user, $organization];
    }

    private function seedPusherSettings(): void
    {
        foreach (['pusher_app_key', 'pusher_app_secret', 'pusher_app_id', 'pusher_app_cluster'] as $key) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => 'test-'.$key]
            );
        }
    }

    private function seedEmbeddedSignupAddonAndGlobalFlag(bool $enabled): void
    {
        Addon::updateOrCreate(
            ['name' => 'Embedded Signup'],
            [
                'category' => 'business',
                'logo' => 'embedded-signup.png',
                'description' => __('Embedded signup addon'),
                'metadata' => json_encode(['name' => 'EmbeddedSignup']),
                'status' => 1,
                'is_active' => 1,
                'is_plan_restricted' => 1,
            ]
        );

        Setting::updateOrCreate(
            ['key' => 'is_embedded_signup_active'],
            ['value' => $enabled ? '1' : '0']
        );
    }

    private function mockWhatsappServiceForManualStore(array $phoneNumberData = []): void
    {
        $mock = Mockery::mock('overload:App\Services\WhatsappService');
        $defaultPhoneNumberData = [
            'id' => 'new-phone-id',
            'display_phone_number' => '+966501234567',
            'verified_name' => 'Test Biz',
            'quality_rating' => 'GREEN',
            'name_status' => 'APPROVED',
            'messaging_limit_tier' => 'TIER_1',
        ];

        $mock->shouldReceive('getPhoneNumberId')
            ->once()
            ->andReturn((object) [
                'success' => true,
                'data' => (object) (empty($phoneNumberData) ? $defaultPhoneNumberData : $phoneNumberData),
            ]);

        $mock->shouldReceive('getPhoneNumberStatus')
            ->once()
            ->andReturn((object) [
                'success' => true,
                'data' => (object) [
                    'status' => 'CONNECTED',
                    'code_verification_status' => 'VERIFIED',
                ],
            ]);

        $mock->shouldReceive('getAccountReviewStatus')
            ->once()
            ->andReturn((object) [
                'success' => true,
                'data' => (object) [
                    'account_review_status' => 'APPROVED',
                ],
            ]);

        $mock->shouldReceive('getBusinessProfile')
            ->once()
            ->andReturn((object) [
                'success' => true,
                'data' => (object) [
                    'about' => 'About',
                    'address' => 'Address',
                    'description' => 'Description',
                    'vertical' => 'OTHER',
                    'email' => 'biz@example.com',
                ],
            ]);

        $mock->shouldReceive('overrideCallbackUrl')
            ->once()
            ->andReturn((object) [
                'success' => true,
                'data' => (object) [],
            ]);

        $mock->shouldReceive('syncTemplates')
            ->once()
            ->andReturn((object) [
                'success' => true,
                'data' => (object) [],
            ]);
    }
}
