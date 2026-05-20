<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardDisplayStateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_maps_workspace_shell_and_expired_subscription_states(): void
    {
        [$shellUser, $shellOrganization] = $this->createOwnedOrganization();
        [$trialUser, $trialOrganization] = $this->createOwnedOrganization();
        [$paymentUser, $paymentOrganization] = $this->createOwnedOrganization();
        $plan = $this->createPlan();

        Subscription::create([
            'organization_id' => $trialOrganization->id,
            'plan_id' => $plan->id,
            'status' => 'trial',
            'start_date' => now()->subWeek(),
            'valid_until' => now()->subMinute(),
        ]);

        Subscription::create([
            'organization_id' => $paymentOrganization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subMonth(),
            'valid_until' => now()->subMinute(),
        ]);

        $shellResponse = $this->withoutMiddleware([
                CheckSubscriptionStatus::class,
                HandleInertiaRequests::class,
            ])
            ->actingAs($shellUser, 'user')
            ->withSession(['current_organization' => $shellOrganization->id])
            ->get('/dashboard');

        $shellResponse->assertOk();
        $shellResponse->assertInertia(fn (Assert $page) => $page
            ->component('User/Dashboard')
            ->where('subscriptionDisplayState.variant', 'billing_pending')
            ->where('subscriptionDisplayState.is_active', false)
        );

        $trialResponse = $this->withoutMiddleware([
                CheckSubscriptionStatus::class,
                HandleInertiaRequests::class,
            ])
            ->actingAs($trialUser, 'user')
            ->withSession(['current_organization' => $trialOrganization->id])
            ->get('/dashboard');

        $trialResponse->assertOk();
        $trialResponse->assertInertia(fn (Assert $page) => $page
            ->component('User/Dashboard')
            ->where('subscriptionDisplayState.variant', 'trial_expired')
            ->where('subscriptionDisplayState.is_active', false)
        );

        $paymentResponse = $this->withoutMiddleware([
                CheckSubscriptionStatus::class,
                HandleInertiaRequests::class,
            ])
            ->actingAs($paymentUser, 'user')
            ->withSession(['current_organization' => $paymentOrganization->id])
            ->get('/dashboard');

        $paymentResponse->assertOk();
        $paymentResponse->assertInertia(fn (Assert $page) => $page
            ->component('User/Dashboard')
            ->where('subscriptionDisplayState.variant', 'payment_required')
            ->where('subscriptionDisplayState.is_active', false)
            ->where('subscriptionDetails.accountBalance', '0.00')
            ->where('subscriptionDetails.amountDue', '20.00')
        );
    }

    private function createOwnedOrganization(): array
    {
        $user = User::create([
            'first_name' => 'Dashboard',
            'last_name' => 'Viewer',
            'email' => 'dashboard-state+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $organization = Organization::create([
            'name' => 'Dashboard State Org',
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => $user->id,
            'organization_type' => 'main',
            'parent_organization_id' => null,
            'metadata' => json_encode([
                'addons' => [],
            ]),
        ]);

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'organization_role_id' => $this->ownerRole()->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        return [$user, $organization];
    }

    private function createPlan(): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Dashboard State Plan',
            'name_en' => 'Dashboard State Plan',
            'name_ar' => 'خطة لوحة التحكم',
            'price' => 20,
            'period' => 'monthly',
            'status' => 'active',
            'metadata' => json_encode([
                'tier_rank' => 1,
                'addons' => [],
            ]),
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
                'description' => 'Owner role',
                'permissions' => ['*'],
            ]
        );
    }
}
