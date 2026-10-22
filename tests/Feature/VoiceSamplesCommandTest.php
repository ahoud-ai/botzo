<?php

namespace Tests\Feature;

use App\Services\IntelliReply\AiKeyResolver;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VoiceSamplesCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        File::deleteDirectory(public_path('voice-samples'));

        parent::tearDown();
    }

    public function test_samples_from_both_providers_are_published_and_can_be_removed(): void
    {
        $this->mock(AiKeyResolver::class)->shouldReceive('resolveGlobalKey')->andReturn('sk-test');
        config(['services.elevenlabs.key' => 'sk_eleven']);
        Http::fake([
            'api.openai.com/v1/audio/speech' => Http::response('mp3-bytes'),
            'api.elevenlabs.io/v1/voices' => Http::response(['voices' => [
                ['voice_id' => 'premade1', 'name' => 'Rachel', 'category' => 'premade'],
                ['voice_id' => 'abc123', 'name' => 'Saudi Fahad', 'category' => 'professional'],
            ]]),
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response('mp3-bytes'),
        ]);

        $this->artisan('botzo:voice-samples')->expectsOutputToContain('/voice-samples/')->assertSuccessful();

        $folders = File::directories(public_path('voice-samples'));
        $this->assertCount(1, $folders);
        $page = File::get($folders[0].'/index.html');
        $this->assertStringContainsString('openai-marin.mp3', $page);
        $this->assertStringContainsString('Saudi Fahad', $page);
        $this->assertStringNotContainsString('Rachel', $page);
        $this->assertStringContainsString('noindex', $page);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'text-to-speech/abc123') && $request['model_id'] === 'eleven_multilingual_v2');

        $this->artisan('botzo:voice-samples', ['--cleanup' => true])->assertSuccessful();
        $this->assertDirectoryDoesNotExist(public_path('voice-samples'));
    }
}
