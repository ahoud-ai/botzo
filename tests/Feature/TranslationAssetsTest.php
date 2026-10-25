<?php

namespace Tests\Feature;

use App\Support\TranslationAssets;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TranslationAssetsTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->source = base_path('lang/zz.json');
    }

    protected function tearDown(): void
    {
        @unlink($this->source);
        foreach (glob(public_path('i18n/zz.*.json')) ?: [] as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public function test_a_language_is_published_as_a_static_file_named_by_its_content(): void
    {
        file_put_contents($this->source, json_encode(['Hello' => 'أهلاً']));

        $url = TranslationAssets::url('zz');

        $this->assertMatchesRegularExpression('#^/i18n/zz\.[0-9a-f]{12}\.json$#', $url);
        $contents = file_get_contents(public_path(ltrim($url, '/')));
        $this->assertSame(['Hello' => 'أهلاً'], json_decode($contents, true));
        $this->assertStringContainsString('أهلاً', $contents, 'Arabic is stored unescaped (half the size).');
        $this->assertSame($url, TranslationAssets::url('zz'));
    }

    public function test_changing_the_strings_makes_a_new_file_and_removes_the_old_one(): void
    {
        file_put_contents($this->source, json_encode(['Hello' => 'أهلاً']));
        $first = TranslationAssets::url('zz');

        file_put_contents($this->source, json_encode(['Hello' => 'مرحبا']));
        touch($this->source, time() + 5);
        clearstatcache();
        $second = TranslationAssets::url('zz');

        $this->assertNotSame($first, $second);
        $this->assertFileExists(public_path(ltrim($second, '/')));
        $this->assertFileDoesNotExist(public_path(ltrim($first, '/')));
    }

    public function test_a_missing_file_is_written_again_even_when_its_name_was_cached(): void
    {
        file_put_contents($this->source, json_encode(['Hello' => 'أهلاً']));
        $url = TranslationAssets::url('zz');
        unlink(public_path(ltrim($url, '/')));

        $this->assertSame($url, TranslationAssets::url('zz'));
        $this->assertFileExists(public_path(ltrim($url, '/')));
    }

    public function test_pages_receive_the_translation_urls(): void
    {
        Cache::flush();

        $page = $this->get('/pricing')->assertOk()->getContent();
        preg_match('/data-page="([^"]+)"/', $page, $match);
        $props = json_decode(html_entity_decode($match[1]), true)['props'];

        $this->assertMatchesRegularExpression('#^/i18n/ar\.[0-9a-f]{12}\.json$#', $props['translationUrls']['ar']);
        $this->assertMatchesRegularExpression('#^/i18n/en\.[0-9a-f]{12}\.json$#', $props['translationUrls']['en']);
    }
}
