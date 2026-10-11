<?php

namespace Tests\Feature;

use App\Services\Ai\AiProviderManager;
use App\Services\Ai\Data\AiChatRequest;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Providers\OpenAiProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Modules\IntelliReply\Services\AIResponseService;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class AiProviderLayerTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_openai_chat_returns_text_and_token_usage(): void
    {
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response([
            'model' => 'gpt-4o-mini-2024',
            'choices' => [['message' => ['content' => 'أهلاً بيك!']]],
            'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 15],
        ])]);

        $result = (new OpenAiProvider('sk-test'))->chat($this->request());

        $this->assertSame('أهلاً بيك!', $result->text);
        $this->assertSame(120, $result->inputTokens);
        $this->assertSame(15, $result->outputTokens);
        $this->assertSame('openai', $result->provider);
        $this->assertFalse($result->hasAudio());

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer sk-test')
            && $request['model'] === 'gpt-4o-mini'
            && $request['max_tokens'] === 300
            && ! isset($request['modalities']));
    }

    public function test_openai_chat_can_return_a_spoken_reply(): void
    {
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['audio' => ['id' => 'audio_1', 'data' => 'BASE64', 'transcript' => 'رد صوتي']]]],
        ])]);

        $result = (new OpenAiProvider('sk-test'))->chat($this->request(audioVoice: 'alloy'));

        $this->assertTrue($result->hasAudio());
        $this->assertSame('رد صوتي', $result->text);
        Http::assertSent(fn (Request $request) => $request['modalities'] === ['text', 'audio'] && $request['audio']['voice'] === 'alloy');
    }

    public function test_provider_errors_keep_the_provider_error_code(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['code' => 'model_not_found', 'message' => 'The model does not exist']], 404)]);

        try {
            (new OpenAiProvider('sk-test'))->embed('hello', 'text-embedding-3-small');
            $this->fail('Expected an AiProviderException.');
        } catch (AiProviderException $exception) {
            $this->assertSame(404, $exception->status);
            $this->assertSame('model_not_found', $exception->errorCode);
            $this->assertStringContainsString('model_not_found', $exception->getMessage());
        }
    }

    public function test_openai_embeddings_return_the_vector(): void
    {
        Http::fake(['api.openai.com/v1/embeddings' => Http::response(['data' => [['embedding' => [0.1, 0.2, 0.3]]]])]);

        $this->assertSame([0.1, 0.2, 0.3], (new OpenAiProvider('sk-test'))->embed('hello', 'text-embedding-3-small'));
    }

    public function test_the_manager_resolves_providers_by_name_and_rejects_unknown_ones(): void
    {
        $manager = app(AiProviderManager::class);

        $this->assertInstanceOf(OpenAiProvider::class, $manager->chat(null, 'sk-test'));
        $this->assertInstanceOf(OpenAiProvider::class, $manager->embeddings('openai', 'sk-test'));

        $this->expectException(InvalidArgumentException::class);
        $manager->chat('not-a-provider', 'sk-test');
    }

    public function test_ai_response_service_chat_goes_through_the_provider_and_reports_usage(): void
    {
        [, $organization] = $this->createOwnerContext(['ai' => ['model' => 'gpt-4o-mini', 'max_tokens' => 256]], true);
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'تمام']]],
            'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 5],
        ])]);

        $result = (new AIResponseService())->chat($organization->id, 'text', [['role' => 'user', 'content' => 'hi']], ['key' => 'sk-test', 'source' => 'global']);

        $this->assertSame('تمام', $result['text']);
        $this->assertSame(['provider' => 'openai', 'model' => 'gpt-4o-mini', 'input_tokens' => 50, 'output_tokens' => 5], $result['usage']);
    }

    public function test_ai_response_service_chat_returns_null_when_the_provider_fails(): void
    {
        [, $organization] = $this->createOwnerContext(['ai' => ['model' => 'gpt-4o-mini']], true);
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'overloaded']], 503)]);

        $this->assertNull((new AIResponseService())->chat($organization->id, 'text', [['role' => 'user', 'content' => 'hi']], ['key' => 'sk-test', 'source' => 'global']));
    }

    private function request(?string $audioVoice = null): AiChatRequest
    {
        return new AiChatRequest(
            model: 'gpt-4o-mini',
            messages: [['role' => 'user', 'content' => 'السلام عليكم']],
            maxTokens: 300,
            temperature: 0.3,
            timeoutSeconds: 10,
            audioVoice: $audioVoice,
        );
    }
}
