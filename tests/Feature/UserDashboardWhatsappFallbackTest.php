<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserDashboardWhatsappFallbackTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_embedded_signup_is_inactive_when_globally_disabled(): void
    {
        [$user, $organization] = $this->createUserAndOrganization([
            'addons' => [
                'embedded_signup_enabled' => true,
            ],
        ]);

        $this->seedEmbeddedSignupAddonAndGlobalFlag(false);
        $this->seedAppAndConfigSettings();
        $this->seedSubscriptionForOrganization($organization->id, true);
        $this->seedSubscriptionSettings();

        $response = $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/Dashboard')
            ->where('embeddedSignupActive', false)
            ->where('appId', 'app-id-123')
            ->where('configId', 'config-id-123')
        );
    }

    public function test_dashboard_embedded_signup_is_inactive_when_plan_disabled(): void
    {
        [$user, $organization] = $this->createUserAndOrganization([
            'addons' => [
                'embedded_signup_enabled' => true,
            ],
        ]);

        $this->seedEmbeddedSignupAddonAndGlobalFlag(true);
        $this->seedAppAndConfigSettings();
        $this->seedSubscriptionForOrganization($organization->id, false);
        $this->seedSubscriptionSettings();

        $response = $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/Dashboard')
            ->where('embeddedSignupActive', false)
            ->where('appId', 'app-id-123')
            ->where('configId', 'config-id-123')
        );
    }

    public function test_dashboard_embedded_signup_is_inactive_when_org_disabled(): void
    {
        [$user, $organization] = $this->createUserAndOrganization([
            'addons' => [
                'embedded_signup_enabled' => false,
            ],
        ]);

        $this->seedEmbeddedSignupAddonAndGlobalFlag(true);
        $this->seedAppAndConfigSettings();
        $this->seedSubscriptionForOrganization($organization->id, true);
        $this->seedSubscriptionSettings();

        $response = $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/Dashboard')
            ->where('embeddedSignupActive', false)
            ->where('appId', 'app-id-123')
            ->where('configId', 'config-id-123')
        );
    }

    private function createUserAndOrganization(array $metadata): array
    {
        $user = User::create([
            'first_name' => 'User',
            'last_name' => 'Tester',
            'email' => 'dashboard-user+'.Str::random(8).'@example.com',
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

    private function seedAppAndConfigSettings(): void
    {
        Setting::updateOrCreate(['key' => 'whatsapp_client_id'], ['value' => 'app-id-123']);
        Setting::updateOrCreate(['key' => 'whatsapp_config_id'], ['value' => 'config-id-123']);
    }

    private function seedSubscriptionSettings(): void
    {
        Setting::updateOrCreate(['key' => 'is_tax_inclusive'], ['value' => '0']);
    }

    private function seedSubscriptionForOrganization(int $organizationId, bool $embeddedEnabledInPlan): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Starter '.Str::random(5),
            'price' => 10,
            'period' => 'monthly',
            'metadata' => json_encode([
                'addons' => [
                    'Embedded Signup' => $embeddedEnabledInPlan,
                ],
            ]),
            'status' => 'active',
        ]);

        Subscription::create([
            'organization_id' => $organizationId,
            'plan_id' => $plan->id,
            'payment_details' => null,
            'start_date' => now(),
            'valid_until' => now()->addMonth(),
            'status' => 'active',
        ]);
    }
}
