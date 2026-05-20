<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckSubscriptionStatus;
use App\Models\Coupon;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class SubscriptionCouponPreviewStateTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_apply_coupon_preview_does_not_store_coupon_in_session(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);
        $selectedPlan = $this->createSelectablePlan();
        $coupon = $this->createCoupon('SAVE10');

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post("/subscription/coupon/apply/{$selectedPlan->id}", [
                'coupon' => $coupon->code,
            ]);

        $response->assertRedirect();
        $response->assertSessionMissing('applied_coupon');
        $response->assertSessionHas('response_data', function ($payload) use ($coupon): bool {
            return data_get($payload, 'data.coupon.code') === $coupon->code;
        });
    }

    public function test_preview_ignores_previous_coupon_session_state_when_coupon_query_is_missing(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);
        $selectedPlan = $this->createSelectablePlan();
        $this->createCoupon('SAVE10');

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession([
                'current_organization' => $organization->id,
                'applied_coupon' => 'SAVE10',
            ])
            ->get("/subscription/{$selectedPlan->id}");

        $response->assertRedirect();
        $response->assertSessionHas('response_data', function ($payload): bool {
            return data_get($payload, 'data.coupon', []) === [];
        });
    }

    private function createSelectablePlan(): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Growth '.uniqid(),
            'name_en' => 'Growth',
            'name_ar' => 'نمو',
            'price' => 50,
            'period' => 'monthly',
            'status' => 'active',
            'metadata' => json_encode([
                'tier_rank' => 2,
                'addons' => [],
            ]),
        ]);
    }

    private function createCoupon(string $code): Coupon
    {
        return Coupon::create([
            'name' => $code,
            'code' => $code,
            'percentage' => 10,
            'quantity' => 5,
            'quantity_redeemed' => 0,
            'status' => 'active',
        ]);
    }
}
