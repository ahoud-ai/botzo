<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Setting;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FrontendPublicPayloadTest extends TestCase
{
    use DatabaseTransactions;

    public function test_homepage_uses_slim_navigation_pages_and_skips_unused_catalog_payloads(): void
    {
        $this->prepareFrontend('premium');

        Page::query()->create([
            'name' => 'About us',
            'name_en' => 'About us',
            'name_ar' => 'من نحن',
            'content' => str_repeat('Long legal body ', 100),
            'content_en' => str_repeat('English legal body ', 100),
            'content_ar' => str_repeat('Arabic legal body ', 100),
        ]);

        SubscriptionPlan::query()->create([
            'name' => 'Starter',
            'name_en' => 'Starter',
            'name_ar' => 'البداية',
            'price' => 29,
            'period' => 'monthly',
            'status' => 'active',
            'metadata' => json_encode(['campaign_limit' => 10]),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('FrontendPremium/Index')
            ->has('plans', 1)
            ->where('plans.0.display_name', 'Starter')
            ->has('faqs')
            ->has('addons')
            ->where('phoneCountries', [])
            ->has('pages', 1)
            ->where('pages.0.display_name', 'About us')
            ->where('pages.0.slug', 'about-us')
            ->has('languages', 2)
            ->where('languages.0.code', 'en')
            ->where('languages.0.name', 'English')
            ->where('languages.0.is_rtl', false)
            ->missing('languages.0.created_at')
            ->missing('languages.0.updated_at')
            ->missing('pages.0.content')
            ->missing('pages.0.content_ar')
            ->missing('pages.0.content_en')
        );

        $response->assertDontSee('pusher_app_key', false);
        $response->assertDontSee('google_maps_api_key', false);
        $response->assertDontSee('fonts.googleapis.com', false);
    }

    public function test_pricing_page_keeps_plan_data_but_navigation_pages_remain_slim(): void
    {
        $this->prepareFrontend('classic');

        Page::query()->create([
            'name' => 'Privacy policy',
            'name_en' => 'Privacy policy',
            'name_ar' => 'سياسة الخصوصية',
            'content' => str_repeat('Privacy page body ', 100),
        ]);

        SubscriptionPlan::query()->create([
            'name' => 'Golden Plan',
            'name_en' => 'Golden Plan',
            'name_ar' => 'الخطة الذهبية',
            'price' => 99,
            'period' => 'monthly',
            'status' => 'active',
            'metadata' => json_encode([
                'campaign_limit' => 10,
                'message_limit' => 100,
            ]),
        ]);

        $response = $this->get('/pricing');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Frontend/Pricing')
            ->has('plans', 1)
            ->where('plans.0.display_name', 'Golden Plan')
            ->where('plans.0.period', 'monthly')
            ->where('phoneCountries', [])
            ->has('pages', 1)
            ->where('pages.0.display_name', 'Privacy policy')
            ->where('pages.0.slug', 'privacy-policy')
            ->missing('pages.0.content')
        );

        $response->assertDontSee('pusher_app_key', false);
        $response->assertDontSee('google_maps_api_key', false);
        $response->assertDontSee('fonts.googleapis.com', false);
    }

    private function prepareFrontend(string $variant): void
    {
        Setting::updateOrCreate(['key' => 'display_frontend'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'frontend_variant'], ['value' => $variant]);
    }
}
