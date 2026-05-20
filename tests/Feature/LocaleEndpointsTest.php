<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocaleEndpointsTest extends TestCase
{
    public function test_locales_endpoint_returns_only_supported_locales(): void
    {
        $response = $this->getJson('/locales');

        $response->assertOk();
        $locales = $response->json();

        $this->assertIsArray($locales);
        $supported = array_map('strtolower', config('i18n.supported_locales', ['en', 'ar']));

        foreach ($locales as $locale) {
            $this->assertContains(strtolower((string) $locale), $supported);
        }

        $this->assertNotContains('sw', array_map('strtolower', $locales));
        $this->assertNotContains('tr', array_map('strtolower', $locales));
    }

    public function test_web_translations_endpoint_allows_only_supported_locales(): void
    {
        $supported = array_map('strtolower', config('i18n.supported_locales', ['en', 'ar']));

        foreach ($supported as $locale) {
            $response = $this->getJson("/translations/{$locale}");
            $response->assertOk();
            $this->assertIsArray($response->json());
        }

        foreach (['sw', 'tr', 'fr'] as $locale) {
            $this->getJson("/translations/{$locale}")
                ->assertStatus(404)
                ->assertJson([
                    'error' => __('Locale not supported'),
                ]);
        }
    }

    public function test_api_translations_endpoint_allows_only_supported_locales(): void
    {
        $supported = array_map('strtolower', config('i18n.supported_locales', ['en', 'ar']));

        foreach ($supported as $locale) {
            $response = $this->getJson("/api/translations/{$locale}");
            $response->assertOk();
            $this->assertIsArray($response->json());
        }

        foreach (['sw', 'tr', 'fr'] as $locale) {
            $this->getJson("/api/translations/{$locale}")
                ->assertStatus(404)
                ->assertJson([
                    'error' => __('Locale not supported'),
                ]);
        }
    }
}

