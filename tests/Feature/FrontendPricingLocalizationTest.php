<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FrontendPricingLocalizationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pricing_page_uses_arabic_plan_display_name_when_locale_is_arabic(): void
    {
        $this->prepareFrontend();
        $this->createPlan();

        $response = $this->withSession(['locale' => 'ar'])->get('/pricing');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Frontend/Pricing')
            ->has('plans', 1)
            ->where('plans.0.display_name', 'الخطة الذهبية')
            ->where('plans.0.name_ar', 'الخطة الذهبية')
            ->where('plans.0.name_en', 'Golden Plan')
        );
    }

    public function test_pricing_page_uses_english_plan_display_name_when_locale_is_english(): void
    {
        $this->prepareFrontend();
        $this->createPlan();

        $response = $this->withSession(['locale' => 'en'])->get('/pricing');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Frontend/Pricing')
            ->has('plans', 1)
            ->where('plans.0.display_name', 'Golden Plan')
            ->where('plans.0.name_ar', 'الخطة الذهبية')
            ->where('plans.0.name_en', 'Golden Plan')
        );
    }

    private function prepareFrontend(): void
    {
        Setting::updateOrCreate(['key' => 'display_frontend'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'frontend_variant'], ['value' => 'classic']);
    }

    private function createPlan(): void
    {
        SubscriptionPlan::query()->create([
            'name' => 'Golden Plan',
            'name_ar' => 'الخطة الذهبية',
            'name_en' => 'Golden Plan',
            'price' => 99,
            'period' => 'monthly',
            'status' => 'active',
            'metadata' => json_encode([
                'campaign_limit' => 10,
                'message_limit' => 100,
                'contacts_limit' => 500,
                'canned_replies_limit' => 25,
                'team_limit' => 5,
                'branches_limit' => 1,
                'addons' => [
                    'AI Assistant' => 1,
                ],
            ]),
        ]);
    }
}
