<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FrontendPremiumHomeMediaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_premium_homepage_receives_managed_media_settings(): void
    {
        Setting::updateOrCreate(['key' => 'display_frontend'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'frontend_variant'], ['value' => 'premium']);
        Setting::updateOrCreate(['key' => 'premium_home_hero_avatar_top_right'], ['value' => 'public/premium-home/top-right.png']);
        Setting::updateOrCreate(['key' => 'premium_home_steps_arrow_left'], ['value' => 'public/premium-home/step-left.png']);
        Setting::updateOrCreate(['key' => 'premium_home_cta_background'], ['value' => 'public/premium-home/cta-background.png']);
        Setting::updateOrCreate(['key' => 'premium_home_footer_payment_methods'], ['value' => 'public/premium-home/payment-methods.png']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('FrontendPremium/Index')
            ->where('premiumHomeMedia.premium_home_hero_avatar_top_right', 'public/premium-home/top-right.png')
            ->where('premiumHomeMedia.premium_home_steps_arrow_left', 'public/premium-home/step-left.png')
            ->where('premiumHomeMedia.premium_home_cta_background', 'public/premium-home/cta-background.png')
            ->where('premiumHomeMedia.premium_home_footer_payment_methods', 'public/premium-home/payment-methods.png')
            ->where('companyConfig.premium_home_footer_payment_methods', 'public/premium-home/payment-methods.png')
        );
    }

    public function test_premium_product_page_receives_feature_dashboard_media_setting(): void
    {
        Setting::updateOrCreate(['key' => 'display_frontend'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'frontend_variant'], ['value' => 'premium']);
        Setting::updateOrCreate(['key' => 'premium_home_feature_dashboard'], ['value' => 'public/premium-home/feature-dashboard.png']);

        $response = $this->get('/product');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('FrontendPremium/Product')
            ->where('premiumHomeMedia.premium_home_feature_dashboard', 'public/premium-home/feature-dashboard.png')
        );
    }
}
