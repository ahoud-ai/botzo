<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class FrontendSeoMetaTagsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_homepage_renders_seo_meta_tags_from_settings(): void
    {
        Setting::updateOrCreate(['key' => 'display_frontend'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'seo_home_title_en'], ['value' => 'Botzo SEO Home']);
        Setting::updateOrCreate(['key' => 'seo_home_description_en'], ['value' => 'Premium WhatsApp automation platform.']);
        Setting::updateOrCreate(['key' => 'seo_robots_index'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'seo_robots_follow'], ['value' => '0']);
        Setting::updateOrCreate(['key' => 'seo_canonical_base_url'], ['value' => 'https://example.com']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<title>Botzo SEO Home</title>', false);
        $response->assertSee('<meta name="robots" content="index,nofollow">', false);
        $response->assertSee('<link rel="canonical" href="https://example.com">', false);
        $response->assertSee('<meta property="og:title" content="Botzo SEO Home">', false);
    }

    public function test_public_homepage_keeps_title_empty_when_homepage_title_for_active_locale_is_blank(): void
    {
        Setting::updateOrCreate(['key' => 'display_frontend'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'seo_home_title_en'], ['value' => '']);
        Setting::updateOrCreate(['key' => 'seo_home_title_ar'], ['value' => 'عنوان قديم']);
        Setting::updateOrCreate(['key' => 'seo_default_title_en'], ['value' => 'Fallback Title']);
        Setting::updateOrCreate(['key' => 'seo_site_name_en'], ['value' => 'Botzo']);

        $response = $this->withSession(['locale' => 'en'])->get('/');

        $response->assertOk();
        $response->assertSee('<title></title>', false);
        $response->assertDontSee('<title>Fallback Title</title>', false);
        $response->assertDontSee('<title>عنوان قديم</title>', false);
    }
}
