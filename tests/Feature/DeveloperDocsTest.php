<?php

namespace Tests\Feature;

use App\Http\Controllers\DeveloperDocsController;
use Tests\TestCase;

class DeveloperDocsTest extends TestCase
{
    public function test_every_guide_page_renders_in_arabic_and_english(): void
    {
        foreach (['ar', 'en'] as $locale) {
            foreach (array_keys(DeveloperDocsController::PAGES) as $page) {
                $path = '/developers'.($page === 'index' ? '' : '/'.$page).'?lang='.$locale;
                $response = $this->get($path);
                $response->assertOk();
                $response->assertSee('dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'"', false);
                $response->assertSee(DeveloperDocsController::PAGES[$page][$locale]);
                $response->assertDontSee('{{BASE_URL}}', false);
            }
        }

        $this->get('/developers/unknown-page')->assertNotFound();
    }
}
