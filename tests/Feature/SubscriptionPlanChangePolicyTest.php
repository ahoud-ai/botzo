<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use App\Services\SubscriptionPlanService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SubscriptionPlanChangePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_plan_is_reported_as_current_plan(): void
    {
        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $plan = $this->createPlan(price: 99.00, period: 'monthly', tierRank: 2);

        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $decision = SubscriptionService::determinePlanChangeAction($organization->id, $plan->id);

        $this->assertSame('current_plan', $decision['action']);
        $this->assertSame($plan->id, $decision['selected_plan']->id);
    }

    public function test_higher_rank_plan_is_treated_as_immediate_upgrade(): void
    {
        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $starter = $this->createPlan(price: 49.00, period: 'monthly', tierRank: 1);
        $growth = $this->createPlan(price: 99.00, period: 'monthly', tierRank: 2);

        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $starter->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $decision = SubscriptionService::determinePlanChangeAction($organization->id, $growth->id);

        $this->assertSame('upgrade_now', $decision['action']);
        $this->assertNull($decision['effective_at']);
    }

    public function test_same_rank_monthly_to_yearly_is_treated_as_immediate_upgrade(): void
    {
        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $monthly = $this->createPlan(price: 50.00, period: 'monthly', tierRank: 2);
        $yearly = $this->createPlan(price: 500.00, period: 'yearly', tierRank: 2);

        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $monthly->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $decision = SubscriptionService::determinePlanChangeAction($organization->id, $yearly->id);

        $this->assertSame('upgrade_now', $decision['action']);
    }

    public function test_lower_rank_plan_is_scheduled_for_renewal_and_can_be_canceled(): void
    {
        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $this->attachOwnerToOrganization($owner, $organization);

        $higherPlan = $this->createPlan(price: 150.00, period: 'monthly', tierRank: 3);
        $lowerPlan = $this->createPlan(price: 75.00, period: 'monthly', tierRank: 2);

        $subscription = Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $higherPlan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $response = $this->actingAs($owner)
            ->from('/subscription')
            ->withSession(['current_organization' => $organization->id])
            ->post('/subscription', [
                'plan' => $lowerPlan->id,
            ]);

        $response->assertRedirect(route('subscription.index'));

        $subscription->refresh();
        $this->assertSame($lowerPlan->id, $subscription->scheduled_plan_id);
        $this->assertNotNull($subscription->scheduled_plan_change_at);

        $cancelResponse = $this->actingAs($owner)
            ->from('/subscription')
            ->withSession(['current_organization' => $organization->id])
            ->post('/subscription/scheduled-change/cancel');

        $cancelResponse->assertRedirect('/subscription');

        $subscription->refresh();
        $this->assertNull($subscription->scheduled_plan_id);
        $this->assertNull($subscription->scheduled_plan_change_at);
    }

    public function test_scheduled_plan_is_applied_on_renewal(): void
    {
        Setting::updateOrCreate(
            ['key' => 'smtp_email_active'],
            ['value' => '0']
        );

        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $this->attachOwnerToOrganization($owner, $organization);

        $currentPlan = $this->createPlan(price: 0.00, period: 'monthly', tierRank: 3);
        $scheduledPlan = $this->createPlan(price: 0.00, period: 'monthly', tierRank: 2);

        $subscription = Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $currentPlan->id,
            'scheduled_plan_id' => $scheduledPlan->id,
            'scheduled_plan_change_at' => now()->subMinute(),
            'status' => 'active',
            'start_date' => now()->subMonth(),
            'valid_until' => now()->subMinute(),
        ]);

        SubscriptionService::activateSubscriptionIfInactiveAndExpiredWithCredits($organization->id, $owner->id);

        $subscription->refresh();

        $this->assertSame($scheduledPlan->id, $subscription->plan_id);
        $this->assertNull($subscription->scheduled_plan_id);
        $this->assertNull($subscription->scheduled_plan_change_at);
        $this->assertSame('active', $subscription->status);
    }

    public function test_transfer_subscribers_updates_scheduled_plan_reference(): void
    {
        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $currentPlan = $this->createPlan(price: 150.00, period: 'monthly', tierRank: 3);
        $fromPlan = $this->createPlan(price: 75.00, period: 'monthly', tierRank: 2);
        $toPlan = $this->createPlan(price: 49.00, period: 'monthly', tierRank: 1);

        $subscription = Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $currentPlan->id,
            'scheduled_plan_id' => $fromPlan->id,
            'scheduled_plan_change_at' => now()->addMonth(),
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        app(SubscriptionPlanService::class)->transferSubscribers($fromPlan->uuid, $toPlan->uuid);

        $subscription->refresh();

        $this->assertSame($toPlan->id, $subscription->scheduled_plan_id);
    }

    public function test_destroying_plan_with_scheduled_subscribers_requires_transfer_first(): void
    {
        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $currentPlan = $this->createPlan(price: 150.00, period: 'monthly', tierRank: 3);
        $scheduledPlan = $this->createPlan(price: 75.00, period: 'monthly', tierRank: 2);

        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $currentPlan->id,
            'scheduled_plan_id' => $scheduledPlan->id,
            'scheduled_plan_change_at' => now()->addMonth(),
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $this->expectException(ValidationException::class);

        app(SubscriptionPlanService::class)->destroy($scheduledPlan->uuid);
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

    private function createOrganization(User $creator): Organization
    {
        return Organization::create([
            'name' => 'Org '.Str::random(4),
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => $creator->id,
            'organization_type' => 'main',
            'parent_organization_id' => null,
            'metadata' => json_encode([
                'addons' => [],
            ]),
        ]);
    }

    private function createPlan(float $price, string $period, int $tierRank): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Plan '.Str::random(4),
            'name_ar' => 'خطة '.Str::random(4),
            'name_en' => 'Plan '.Str::random(4),
            'price' => $price,
            'period' => $period,
            'metadata' => json_encode([
                'tier_rank' => $tierRank,
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
