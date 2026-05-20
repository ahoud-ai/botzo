<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\User;
use App\Services\AddonStateService;
use App\Services\SubscriptionPlanLimitService;
use App\Services\SubscriptionPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class AdminSubscriptionPlanDefinitionTest extends TestCase
{
    use CreatesOrganizationContext;
    use RefreshDatabase;

    private const RETIRED_ADDON_FIXTURES = [
        'Webhooks',
        'Meta Messaging',
        'Messenger',
        'Instagram',
        'Google Analytics',
        'Google Recaptcha',
        'Google Authenticator',
        'Google Maps',
        'WooCommerce',
        'Pabbly Subscriptions',
    ];

    public function test_admin_plan_definition_rejects_unknown_period_and_status(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->withoutMiddleware()
            ->actingAs($admin, 'admin')
            ->post('/admin/plans', $this->planPayload([
                'period' => 'weekly',
                'status' => 'deleted',
            ]));

        $response->assertSessionHasErrors(['period', 'status']);
        $this->assertDatabaseMissing('subscription_plans', [
            'name_en' => 'Commercial Plan',
        ]);
    }

    public function test_admin_plan_definition_rejects_unknown_addon_keys(): void
    {
        $admin = $this->createAdminUser();
        $this->createAddon('Flow builder');

        $response = $this->withoutMiddleware()
            ->actingAs($admin, 'admin')
            ->post('/admin/plans', $this->planPayload([
                'addons' => [
                    'Flow builder' => true,
                    'Unknown Addon' => true,
                ],
            ]));

        $response->assertSessionHasErrors(['addons']);
        $this->assertDatabaseMissing('subscription_plans', [
            'name_en' => 'Commercial Plan',
        ]);
    }

    public function test_admin_plan_definition_rejects_retired_addons_even_if_database_rows_exist(): void
    {
        $admin = $this->createAdminUser();
        $this->createAddon('Flow builder');
        foreach (self::RETIRED_ADDON_FIXTURES as $addonName) {
            $this->createAddon($addonName);
        }

        $response = $this->withoutMiddleware()
            ->actingAs($admin, 'admin')
            ->post('/admin/plans', $this->planPayload([
                'addons' => array_merge(
                    ['Flow builder' => true],
                    array_fill_keys(self::RETIRED_ADDON_FIXTURES, true),
                ),
            ]));

        $response->assertSessionHasErrors(['addons']);
        $this->assertDatabaseMissing('subscription_plans', [
            'name_en' => 'Commercial Plan',
        ]);
    }

    public function test_subscription_plan_service_strips_unavailable_addons_when_called_directly(): void
    {
        $this->createAddon('Flow builder');
        $this->createAddon('Internal Draft Addon', isPlanRestricted: false);
        foreach (self::RETIRED_ADDON_FIXTURES as $addonName) {
            $this->createAddon($addonName);
        }

        $request = Request::create('/admin/plans', 'POST', $this->planPayload([
            'addons' => array_merge([
                'Flow builder' => true,
                'Internal Draft Addon' => true,
                'Unknown Addon' => true,
            ], array_fill_keys(self::RETIRED_ADDON_FIXTURES, true)),
        ]));

        $plan = app(SubscriptionPlanService::class)->store($request);
        $metadata = json_decode((string) $plan->metadata, true);

        $this->assertSame(['Flow builder' => true], $metadata['addons'] ?? null);
    }

    public function test_retired_addons_are_not_enabled_for_organizations_even_if_previous_metadata_says_true(): void
    {
        $this->createAddon('Flow builder');
        foreach (self::RETIRED_ADDON_FIXTURES as $addonName) {
            $this->createAddon($addonName);
        }

        [, $organization] = $this->createOwnerContext();
        $this->createActiveSubscription($organization->id, [
            'addons' => array_merge(
                ['Flow builder' => true],
                array_fill_keys(self::RETIRED_ADDON_FIXTURES, true),
            ),
        ]);

        $addonState = app(AddonStateService::class);
        $planLimits = app(SubscriptionPlanLimitService::class);

        $this->assertTrue($addonState->isModuleEnabledForOrganization('Flow builder', $organization->id));
        $this->assertTrue($planLimits->addonEnabledForOrganization($organization->id, 'Flow builder'));
        foreach (self::RETIRED_ADDON_FIXTURES as $addonName) {
            $this->assertFalse($addonState->isModuleEnabledForOrganization($addonName, $organization->id));
            $this->assertFalse($planLimits->addonEnabledForOrganization($organization->id, $addonName));
        }
    }

    private function planPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'name_en' => 'Commercial Plan',
            'name_ar' => 'خطة تجارية',
            'price' => 99.99,
            'period' => 'monthly',
            'tier_rank' => 2,
            'campaign_limit' => 10,
            'message_limit' => 100,
            'contacts_limit' => 100,
            'canned_replies_limit' => 10,
            'team_limit' => 5,
            'ai_text_response_limit' => -1,
            'ai_audio_response_limit' => -1,
            'ai_organization_key_enabled' => false,
            'branches_limit' => 2,
            'ai_system_key_monthly_quota' => -1,
            'flow_builder_active_flows_limit' => -1,
            'flow_builder_nodes_per_flow_limit' => -1,
            'flow_builder_monthly_runs_limit' => -1,
            'flow_builder_advanced_enabled' => false,
            'receive_messages_after_expiration' => false,
            'addons' => [],
            'custom_features' => [],
            'status' => 'active',
        ], $overrides);
    }

    private function createAdminUser(): User
    {
        return User::create([
            'first_name' => 'Admin',
            'last_name' => 'Planner',
            'email' => 'plan-admin+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'email_verified_at' => now(),
        ]);
    }

    private function createAddon(string $name, bool $isPlanRestricted = true): Addon
    {
        return Addon::create([
            'uuid' => (string) Str::uuid(),
            'category' => 'business',
            'name' => $name,
            'logo' => Str::slug($name).'.svg',
            'description' => $name.' addon',
            'metadata' => json_encode(['name' => $name]),
            'status' => 1,
            'is_active' => 1,
            'is_plan_restricted' => $isPlanRestricted ? 1 : 0,
        ]);
    }
}
