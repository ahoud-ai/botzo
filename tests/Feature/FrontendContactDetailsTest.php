<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FrontendContactDetailsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_contact_page_exposes_localized_business_hours_settings(): void
    {
        Setting::updateOrCreate(['key' => 'display_frontend'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'frontend_variant'], ['value' => 'premium']);
        Setting::updateOrCreate(['key' => 'frontend_contact_business_hours_primary_ar'], ['value' => 'الأحد - الخميس: 9:00 ص - 6:00 م']);
        Setting::updateOrCreate(['key' => 'frontend_contact_business_hours_secondary_ar'], ['value' => 'الجمعة - السبت: مغلق']);
        Setting::updateOrCreate(['key' => 'frontend_contact_business_hours_primary_en'], ['value' => 'Sunday - Thursday: 9:00 AM - 6:00 PM']);
        Setting::updateOrCreate(['key' => 'frontend_contact_business_hours_secondary_en'], ['value' => 'Friday - Saturday: Closed']);

        $response = $this->withSession(['locale' => 'ar'])->get('/contact');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('FrontendPremium/Contact')
            ->where('companyConfig.frontend_contact_business_hours_primary_ar', 'الأحد - الخميس: 9:00 ص - 6:00 م')
            ->where('companyConfig.frontend_contact_business_hours_secondary_ar', 'الجمعة - السبت: مغلق')
            ->where('companyConfig.frontend_contact_business_hours_primary_en', 'Sunday - Thursday: 9:00 AM - 6:00 PM')
            ->where('companyConfig.frontend_contact_business_hours_secondary_en', 'Friday - Saturday: Closed')
        );
    }
}
