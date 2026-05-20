<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Setting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class FrontendSitemapTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sitemap_endpoint_returns_public_pages_and_dynamic_pages(): void
    {
        Setting::updateOrCreate(
            ['key' => 'seo_canonical_base_url'],
            ['value' => 'https://example.com']
        );

        Page::create([
            'name' => 'Previous policy page',
            'name_en' => 'About Botzo',
            'name_ar' => 'حول بوتزو',
            'content' => 'Previous content',
            'content_en' => 'About page content',
            'content_ar' => 'محتوى صفحة حول',
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('<loc>https://example.com</loc>', false);
        $response->assertSee('<loc>https://example.com/pricing</loc>', false);
        $response->assertSee('<loc>https://example.com/contact</loc>', false);
        $response->assertSee('<loc>https://example.com/product</loc>', false);
        $response->assertSee('<loc>https://example.com/api-documentation</loc>', false);
        $response->assertSee('<loc>https://example.com/faqs</loc>', false);
        $response->assertSee('<loc>https://example.com/pages/about-botzo</loc>', false);
    }
}
