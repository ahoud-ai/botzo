<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckSubscriptionStatus;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionCouponRouteContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupon_remove_route_rejects_get_requests(): void
    {
        [$user, $organization] = $this->createOwnedOrganization();
        $plan = $this->createPlan();

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get("/subscription/coupon/remove/{$plan->id}");

        $response->assertStatus(405);
    }

    public function test_coupon_remove_route_accepts_delete_requests(): void
    {
        [$user, $organization] = $this->createOwnedOrganization();
        $plan = $this->createPlan();

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->delete("/subscription/coupon/remove/{$plan->id}");

        $response->assertRedirect();
        $response->assertSessionHas('response_data.data', function ($data) use ($plan): bool {
            return is_array($data)
                && ($data['basePrice'] ?? null) === '20.00'
                && ($data['amountDue'] ?? null) === '20.00'
                && ($data['coupon']['code'] ?? null) === null;
        });
    }

    private function createOwnedOrganization(): array
    {
        $user = User::create([
            'first_name' => 'Billing',
            'last_name' => 'Owner',
            'email' => 'coupon-route+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $organization = Organization::create([
            'name' => 'Coupon Route Org',
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => $user->id,
            'organization_type' => 'main',
            'parent_organization_id' => null,
            'metadata' => json_encode(['addons' => []]),
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
            'name' => 'Coupon Route Plan',
            'name_en' => 'Coupon Route Plan',
            'name_ar' => 'خطة الكوبون',
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
