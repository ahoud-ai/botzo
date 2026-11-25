<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\SeoDomains;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SeoDomainsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('frontend.seo_domain_alternates', [
            'x-default' => 'botzo.net',
            'ar-SA' => 'botzo.sa',
        ]);
        Setting::updateOrCreate(['key' => 'seo_canonical_base_url'], ['value' => 'https://botzo.net']);
    }

    public function test_sitemap_uses_the_host_it_is_served_on(): void
    {
        $sa = $this->get('https://botzo.sa/sitemap.xml');
        $sa->assertOk();
        $sa->assertSee('<loc>https://botzo.sa</loc>', false);
        $sa->assertSee('<loc>https://botzo.sa/pricing</loc>', false);
        $sa->assertDontSee('https://botzo.net', false);

        $net = $this->get('https://www.botzo.net/sitemap.xml');
        $net->assertOk();
        $net->assertSee('<loc>https://botzo.net/pricing</loc>', false);
        $net->assertDontSee('https://botzo.sa', false);
    }

    public function test_other_hosts_keep_the_admin_canonical_base(): void
    {
        $response = $this->get('https://preview.botzo.net/sitemap.xml');

        $response->assertOk();
        $response->assertSee('<loc>https://botzo.net/pricing</loc>', false);
    }

    public function test_home_page_has_its_own_canonical_and_hreflang_alternates(): void
    {
        $response = $this->get('https://botzo.sa/pricing');

        $response->assertOk();
        $response->assertSee('<link rel="canonical" href="https://botzo.sa/pricing">', false);
        $response->assertSee('<link rel="alternate" hreflang="ar-SA" href="https://botzo.sa/pricing">', false);
        $response->assertSee('<link rel="alternate" hreflang="x-default" href="https://botzo.net/pricing">', false);
    }

    public function test_hreflang_links_only_on_indexed_hosts(): void
    {
        $request = \Illuminate\Http\Request::create('https://preview.botzo.net/pricing');

        $this->assertSame([], SeoDomains::alternateLinks('pricing', $request));
        $this->assertSame(
            ['https://botzo.net/sitemap.xml', 'https://botzo.sa/sitemap.xml'],
            SeoDomains::sitemapUrls()
        );
    }
}
