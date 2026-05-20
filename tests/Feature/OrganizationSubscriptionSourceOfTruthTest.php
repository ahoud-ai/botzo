<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\OrganizationService;
use App\Services\SubscriptionPlanLimitService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrganizationSubscriptionSourceOfTruthTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_plan_update_targets_current_operational_subscription_row(): void
    {
        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $previousPlan = $this->createPlan('Previous', 1);
        $currentPlan = $this->createPlan('Current', 3);
        $replacementPlan = $this->createPlan('Replacement', 4);

        $previousSubscription = Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $previousPlan->id,
            'status' => 'active',
            'start_date' => now()->subMonths(2),
            'valid_until' => now()->addYear(),
        ]);

        $currentSubscription = Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $currentPlan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $resolved = app(SubscriptionPlanLimitService::class)->subscriptionForOrganization($organization->id);

        $this->assertSame($currentSubscription->id, $resolved?->id);

        $request = Request::create('/admin/organizations/'.$organization->uuid, 'PUT', [
            'name' => $organization->name,
            'organization_type' => 'main',
            'plan' => $replacementPlan->uuid,
            'street' => null,
            'city' => null,
            'state' => null,
            'zip' => null,
            'country' => null,
        ]);

        app(OrganizationService::class)->update($request, $organization->uuid);

        $this->assertSame($previousPlan->id, $previousSubscription->fresh()->plan_id);
        $this->assertSame($replacementPlan->id, $currentSubscription->fresh()->plan_id);
    }

    public function test_plan_change_decision_uses_current_operational_subscription_when_multiple_rows_exist(): void
    {
        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $starterPlan = $this->createPlan('Starter', 1);
        $growthPlan = $this->createPlan('Growth', 3);
        $midPlan = $this->createPlan('Mid', 2);

        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $starterPlan->id,
            'status' => 'active',
            'start_date' => now()->subMonths(3),
            'valid_until' => now()->addYear(),
        ]);

        $currentSubscription = Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $growthPlan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $decision = SubscriptionService::determinePlanChangeAction($organization->id, $midPlan->id);

        $this->assertSame($currentSubscription->id, app(SubscriptionPlanLimitService::class)->subscriptionForOrganization($organization->id)?->id);
        $this->assertSame('downgrade_at_renewal', $decision['action']);
        $this->assertSame($growthPlan->id, $decision['current_plan']?->id);
    }

    private function createUser(): User
    {
        return User::create([
            'first_name' => 'Source',
            'last_name' => 'Truth',
            'email' => 'source-truth+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'email_verified_at' => now(),
        ]);
    }

    private function createOrganization(User $owner): Organization
    {
        return Organization::create([
            'name' => 'Org '.Str::random(5),
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => $owner->id,
            'organization_type' => 'main',
            'metadata' => json_encode([
                'addons' => [
                    'embedded_signup_enabled' => true,
                ],
            ]),
        ]);
    }

    private function createPlan(string $name, int $tierRank): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => $name.' '.Str::random(4),
            'price' => 99.00 + $tierRank,
            'period' => 'monthly',
            'metadata' => json_encode([
                'tier_rank' => $tierRank,
                'addons' => [],
            ]),
            'status' => 'active',
        ]);
    }
}
