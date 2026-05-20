<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Addon;
use App\Models\Coupon;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use App\Services\IntelliReply\AiUsageLimiterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserBillingWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_page_filters_plans_by_selected_period(): void
    {
        $this->withoutMiddleware(HandleInertiaRequests::class);

        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $this->attachOwnerToOrganization($owner, $organization);

        $currentPlan = $this->createPlan(price: 49.00, period: 'monthly', tierRank: 1);
        $this->createPlan(price: 499.00, period: 'yearly', tierRank: 1);

        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $currentPlan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_organization' => $organization->id])
            ->get('/subscription?period=yearly');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/Billing/Plan')
            ->where('selectedPeriod', 'yearly')
            ->where('periodCounts.monthly', 1)
            ->where('periodCounts.yearly', 1)
            ->has('plans.data', 1)
            ->where('plans.data.0.period', 'yearly')
        );
    }

    public function test_invalid_period_falls_back_to_current_plan_period(): void
    {
        $this->withoutMiddleware(HandleInertiaRequests::class);

        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $this->attachOwnerToOrganization($owner, $organization);

        $this->createPlan(price: 49.00, period: 'monthly', tierRank: 1);
        $currentPlan = $this->createPlan(price: 499.00, period: 'yearly', tierRank: 1);

        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $currentPlan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addYear(),
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_organization' => $organization->id])
            ->get('/subscription?period=quarterly');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/Billing/Plan')
            ->where('selectedPeriod', 'yearly')
            ->has('plans.data', 1)
            ->where('plans.data.0.period', 'yearly')
        );
    }

    public function test_requested_period_falls_back_to_available_period_when_no_matching_plans_exist(): void
    {
        $this->withoutMiddleware(HandleInertiaRequests::class);

        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $this->attachOwnerToOrganization($owner, $organization);

        $currentPlan = $this->createPlan(price: 49.00, period: 'monthly', tierRank: 1);
        $this->createPlan(price: 99.00, period: 'monthly', tierRank: 2);

        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $currentPlan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_organization' => $organization->id])
            ->get('/subscription?period=yearly');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/Billing/Plan')
            ->where('selectedPeriod', 'monthly')
            ->where('periodCounts.monthly', 2)
            ->where('periodCounts.yearly', 0)
            ->has('plans.data', 2)
            ->where('plans.data.0.period', 'monthly')
        );
    }

    public function test_search_query_updates_period_counts_and_results(): void
    {
        $this->withoutMiddleware(HandleInertiaRequests::class);

        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $this->attachOwnerToOrganization($owner, $organization);

        $currentPlan = SubscriptionPlan::create([
            'name' => 'Growth Monthly',
            'name_ar' => 'جروث شهري',
            'name_en' => 'Growth Monthly',
            'price' => 49.00,
            'period' => 'monthly',
            'metadata' => json_encode([
                'tier_rank' => 1,
                'campaign_limit' => 10,
                'message_limit' => 100,
                'contacts_limit' => 100,
                'canned_replies_limit' => 10,
                'team_limit' => 5,
                'branches_limit' => 2,
                'addons' => [],
            ]),
            'status' => 'active',
        ]);

        SubscriptionPlan::create([
            'name' => 'Annual Pro',
            'name_ar' => 'احترافي سنوي',
            'name_en' => 'Annual Pro',
            'price' => 499.00,
            'period' => 'yearly',
            'metadata' => json_encode([
                'tier_rank' => 2,
                'campaign_limit' => 20,
                'message_limit' => 500,
                'contacts_limit' => 500,
                'canned_replies_limit' => 20,
                'team_limit' => 10,
                'branches_limit' => 5,
                'addons' => [],
            ]),
            'status' => 'active',
        ]);

        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $currentPlan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_organization' => $organization->id])
            ->get('/subscription?period=yearly&search=Annual');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/Billing/Plan')
            ->where('selectedPeriod', 'yearly')
            ->where('periodCounts.monthly', 0)
            ->where('periodCounts.yearly', 1)
            ->has('plans.data', 1)
            ->where('plans.data.0.name', 'Annual Pro')
            ->where('plans.data.0.period', 'yearly')
        );
    }

    public function test_coupon_preview_keeps_scheduled_downgrade_action(): void
    {
        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $this->attachOwnerToOrganization($owner, $organization);

        $higherPlan = $this->createPlan(price: 150.00, period: 'monthly', tierRank: 3);
        $lowerPlan = $this->createPlan(price: 75.00, period: 'monthly', tierRank: 2);

        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $higherPlan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        Coupon::create([
            'name' => 'Spring Sale',
            'code' => 'SAVE10',
            'percentage' => 10,
            'quantity' => 10,
            'quantity_redeemed' => 0,
            'status' => 'active',
        ]);

        $response = $this->actingAs($owner)
            ->from('/subscription')
            ->withSession(['current_organization' => $organization->id])
            ->post("/subscription/coupon/apply/{$lowerPlan->id}", [
                'coupon' => 'SAVE10',
            ]);

        $response->assertRedirect('/subscription');
        $response->assertSessionHas('response_data.data.change_action', 'downgrade_at_renewal');
        $response->assertSessionHas('response_data.data.effectiveAt');
    }

    public function test_usage_page_shows_family_usage_for_billing_owner_scope(): void
    {
        $owner = $this->createUser();
        $branchUser = $this->createUser();
        $parent = $this->createOrganization($owner, ['name' => 'Parent Workspace']);
        $branch = $this->createOrganization($owner, [
            'name' => 'Branch Workspace',
            'organization_type' => 'branch',
            'parent_organization_id' => $parent->id,
        ]);

        $ownerRole = $this->ownerRole();
        Team::create([
            'organization_id' => $parent->id,
            'user_id' => $owner->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);
        Team::create([
            'organization_id' => $branch->id,
            'user_id' => $branchUser->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        $plan = $this->createPlan(price: 99.00, period: 'monthly', tierRank: 2, metadata: [
            'branches_limit' => 3,
            'team_limit' => 5,
            'campaign_limit' => 10,
            'message_limit' => 100,
            'contacts_limit' => 100,
            'canned_replies_limit' => 10,
            'addons' => [],
        ]);

        Subscription::create([
            'organization_id' => $parent->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_organization' => $parent->id])
            ->get('/billing/usage');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/Billing/Usage')
            ->where('billingOwner.name', 'Parent Workspace')
            ->where('usageSummary.metrics.0.key', 'branches')
            ->where('usageSummary.metrics.0.used', 2)
            ->where('usageSummary.metrics.0.limit', 3)
            ->where('usageSummary.metrics.0.remaining', 1)
            ->where('usageSummary.metrics.1.key', 'users')
            ->where('usageSummary.metrics.1.used', 2)
            ->where('usageSummary.metrics.1.limit', 5)
            ->where('usageSummary.metrics.1.remaining', 3)
        );
    }

    public function test_branch_usage_page_uses_parent_billing_context(): void
    {
        $owner = $this->createUser();
        $parent = $this->createOrganization($owner, ['name' => 'Parent Workspace']);
        $branch = $this->createOrganization($owner, [
            'name' => 'Branch Workspace',
            'organization_type' => 'branch',
            'parent_organization_id' => $parent->id,
        ]);

        $this->attachOwnerToOrganization($owner, $parent);
        $this->attachOwnerToOrganization($owner, $branch);

        $plan = $this->createPlan(price: 99.00, period: 'monthly', tierRank: 2, metadata: [
            'branches_limit' => 3,
            'team_limit' => 5,
            'campaign_limit' => 10,
            'message_limit' => 100,
            'contacts_limit' => 100,
            'canned_replies_limit' => 10,
            'addons' => [],
        ]);

        Subscription::create([
            'organization_id' => $parent->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_organization' => $branch->id])
            ->get('/billing/usage');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/Billing/Usage')
            ->where('subscriptionManagedByParent', true)
            ->where('billingOwner.name', 'Parent Workspace')
            ->where('subscription.organization_id', $parent->id)
            ->where('usageSummary.metrics.0.key', 'branches')
            ->where('usageSummary.metrics.0.used', 2)
            ->where('usageSummary.metrics.0.limit', 3)
        );
    }

    public function test_parent_owner_can_view_branch_billing_without_direct_branch_membership(): void
    {
        $owner = $this->createUser();
        $parent = $this->createOrganization($owner, ['name' => 'Parent Workspace']);
        $branch = $this->createOrganization($owner, [
            'name' => 'Branch Workspace',
            'organization_type' => 'branch',
            'parent_organization_id' => $parent->id,
        ]);

        $this->attachOwnerToOrganization($owner, $parent);

        $plan = $this->createPlan(price: 99.00, period: 'monthly', tierRank: 2, metadata: [
            'branches_limit' => 3,
            'team_limit' => 5,
            'campaign_limit' => 10,
            'message_limit' => 100,
            'contacts_limit' => 100,
            'canned_replies_limit' => 10,
            'addons' => [],
        ]);

        Subscription::create([
            'organization_id' => $parent->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_organization' => $branch->id])
            ->get('/billing');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/Billing/Index')
            ->where('subscriptionManagedByParent', true)
            ->where('billingOwner.name', 'Parent Workspace')
        );
    }

    public function test_branch_local_owner_cannot_view_parent_billing_without_parent_membership(): void
    {
        $parentOwner = $this->createUser();
        $branchOwner = $this->createUser();
        $parent = $this->createOrganization($parentOwner, ['name' => 'Parent Workspace']);
        $branch = $this->createOrganization($parentOwner, [
            'name' => 'Branch Workspace',
            'organization_type' => 'branch',
            'parent_organization_id' => $parent->id,
        ]);

        $this->attachOwnerToOrganization($parentOwner, $parent);
        $this->attachOwnerToOrganization($branchOwner, $branch);

        $plan = $this->createPlan(price: 99.00, period: 'monthly', tierRank: 2, metadata: [
            'branches_limit' => 3,
            'team_limit' => 5,
            'campaign_limit' => 10,
            'message_limit' => 100,
            'contacts_limit' => 100,
            'canned_replies_limit' => 10,
            'addons' => [],
        ]);

        Subscription::create([
            'organization_id' => $parent->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $response = $this->actingAs($branchOwner)
            ->withSession(['current_organization' => $branch->id])
            ->get('/billing');

        $response->assertRedirect(route('dashboard'));
    }

    public function test_usage_page_includes_ai_notices_and_global_quota_metric(): void
    {
        Setting::updateOrCreate(['key' => 'enable_ai_billing'], ['value' => 1]);
        $this->createAddon('AI Assistant');

        $owner = $this->createUser();
        $organization = $this->createOrganization($owner, ['name' => 'AI Billing Workspace']);
        $this->attachOwnerToOrganization($owner, $organization);

        $plan = $this->createPlan(price: 99.00, period: 'monthly', tierRank: 2, metadata: [
            'branches_limit' => 3,
            'team_limit' => 5,
            'campaign_limit' => 10,
            'message_limit' => 100,
            'contacts_limit' => 100,
            'canned_replies_limit' => 10,
            'ai_text_response_limit' => 5,
            'ai_audio_response_limit' => 2,
            'ai_system_key_monthly_quota' => 1,
            'addons' => [
                'AI Assistant' => true,
            ],
        ]);

        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $this->assertTrue(app(AiUsageLimiterService::class)->consumeText($organization->id, 'global'));

        $response = $this->actingAs($owner)
            ->withSession(['current_organization' => $organization->id])
            ->get('/billing/usage');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/Billing/Usage')
            ->where('usageSummary', function ($summary): bool {
                $metrics = collect($summary['metrics'] ?? []);
                $notices = collect($summary['notices'] ?? []);

                return $metrics->contains(fn ($metric) => ($metric['key'] ?? null) === 'ai_system_key' && ($metric['used'] ?? null) === 1 && ($metric['limit'] ?? null) === 1)
                    && $notices->contains(fn ($notice) => ($notice['key'] ?? null) === 'ai_usage_scope')
                    && $notices->contains(fn ($notice) => ($notice['key'] ?? null) === 'ai_global_quota_exceeded');
            })
        );
    }

    private function createUser(): User
    {
        return User::create([
            'first_name' => 'Billing',
            'last_name' => 'Owner',
            'email' => 'billing+'.Str::random(8).'@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);
    }

    private function createOrganization(User $creator, array $attributes = []): Organization
    {
        return Organization::create(array_merge([
            'name' => 'Organization '.Str::random(4),
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => $creator->id,
            'organization_type' => 'main',
            'parent_organization_id' => null,
            'metadata' => json_encode([
                'addons' => [],
            ]),
        ], $attributes));
    }

    private function createPlan(float $price, string $period, int $tierRank, array $metadata = []): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Plan '.Str::random(4),
            'name_ar' => 'خطة '.Str::random(4),
            'name_en' => 'Plan '.Str::random(4),
            'price' => $price,
            'period' => $period,
            'metadata' => json_encode(array_merge([
                'tier_rank' => $tierRank,
                'campaign_limit' => 10,
                'message_limit' => 100,
                'contacts_limit' => 100,
                'canned_replies_limit' => 10,
                'team_limit' => 5,
                'branches_limit' => 2,
                'addons' => [],
            ], $metadata)),
            'status' => 'active',
        ]);
    }

    private function createAddon(string $name): void
    {
        Addon::updateOrCreate(
            ['name' => $name],
            [
                'uuid' => Addon::query()->where('name', $name)->value('uuid') ?: (string) Str::uuid(),
                'category' => 'business',
                'logo' => Str::slug($name).'.svg',
                'description' => $name.' addon',
                'metadata' => json_encode(['name' => $name]),
                'status' => 1,
                'is_active' => 1,
                'is_plan_restricted' => 1,
            ]
        );
    }

    private function attachOwnerToOrganization(User $user, Organization $organization): void
    {
        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'organization_role_id' => $this->ownerRole()->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);
    }

    private function ownerRole(): OrganizationRole
    {
        return OrganizationRole::firstOrCreate(
            [
                'organization_id' => null,
                'name' => 'Owner',
            ],
            [
                'description' => 'Owner role for billing tests',
                'permissions' => ['*'],
            ]
        );
    }
}
