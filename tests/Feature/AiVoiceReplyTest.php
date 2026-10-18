<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\ChatMedia;
use App\Models\Contact;
use App\Services\AddonStateService;
use App\Services\IntelliReply\AiDisclosureService;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Modules\IntelliReply\Services\AIResponseService;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;
use Tests\Unit\AudioDurationTest;

/**
 * A customer's voice note gets a WhatsApp voice message back when the organization turned
 * audio responses on; anything that prevents it falls back to the same reply as text.
 */
class AiVoiceReplyTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(AddonStateService::class)->shouldReceive('isModuleEnabledForOrganization')->andReturn(true);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            Storage::disk('local')->delete($file);
        }
        foreach (Storage::disk('local')->files('public/audios') as $file) {
            if (str_starts_with(basename($file), 'reply_')) {
                Storage::disk('local')->delete($file);
            }
        }

        parent::tearDown();
    }

    public function test_a_voice_note_is_answered_with_a_voice_message_carrying_its_text(): void
    {
        [$contact, $chat] = $this->conversation(type: 'audio');
        $this->fakeOpenAi('أبغى أعرف سعر الكبسة', 'كبسة الدجاج بـ 35 ريال.', speech: AudioDurationTest::opus(3));

        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldNotReceive('sendMessage');
        $whatsapp->shouldReceive('sendMedia')->once()
            ->withArgs(fn ($uuid, $type, $name, $path, $url, $location, $caption, $transcription, $metadata) => $uuid === (string) $contact->uuid
                && $type === 'audio'
                && str_ends_with($url, '.ogg')
                && $transcription === 'كبسة الدجاج بـ 35 ريال.'
                && $metadata['voice'] === true
                && $metadata['content_type'] === 'audio/ogg')
            ->andReturn((object) ['success' => true]);

        $this->assertTrue($this->serviceWith($whatsapp)->handleAIResponse($chat, ''));

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/audio/speech')
            && $request['model'] === 'gpt-4o-mini-tts'
            && $request['voice'] === 'nova'
            && $request['response_format'] === 'opus'
            && $request['input'] === 'كبسة الدجاج بـ 35 ريال.'
            && str_contains($request['instructions'], 'Gulf'));

        $this->assertDatabaseHas('ai_usage_logs', [
            'contact_id' => $contact->id,
            'purpose' => 'speech',
            'outcome' => 'sent',
            'model' => 'gpt-4o-mini-tts',
            'audio_seconds' => 3.0,
            'cost_usd' => 0.00075,
        ]);
    }

    public function test_a_failed_speech_request_sends_the_reply_as_text(): void
    {
        [$contact, $chat] = $this->conversation(type: 'audio');
        Http::fake([
            'api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'عندكم توصيل؟']),
            'api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'أيوه نوصل.']]]]),
            'api.openai.com/v1/audio/speech' => Http::response(['error' => ['message' => 'overloaded']], 503),
        ]);

        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldNotReceive('sendMedia');
        $whatsapp->shouldReceive('sendMessage')->once()->with((string) $contact->uuid, 'أيوه نوصل.')->andReturn((object) ['success' => true]);

        $this->assertTrue($this->serviceWith($whatsapp)->handleAIResponse($chat, ''));
    }

    public function test_a_long_reply_is_sent_as_text_without_generating_speech(): void
    {
        config(['intellireply.speech.max_characters' => 20]);
        [$contact, $chat] = $this->conversation(type: 'audio');
        $this->fakeOpenAi('ابغى المنيو كامل', str_repeat('صنف وسعره. ', 10), speech: AudioDurationTest::opus(3));

        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldNotReceive('sendMedia');
        $whatsapp->shouldReceive('sendMessage')->once()->andReturn((object) ['success' => true]);

        $this->assertTrue($this->serviceWith($whatsapp)->handleAIResponse($chat, ''));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/audio/speech'));
    }

    public function test_a_text_message_is_answered_with_text_even_when_voice_replies_are_on(): void
    {
        [$contact, $chat] = $this->conversation(type: 'text');
        $this->fakeOpenAi('', 'أهلاً وسهلاً!', speech: AudioDurationTest::opus(3));

        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldNotReceive('sendMedia');
        $whatsapp->shouldReceive('sendMessage')->once()->andReturn((object) ['success' => true]);

        $this->assertTrue($this->serviceWith($whatsapp)->handleAIResponse($chat, ''));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/audio/speech'));
    }

    public function test_whatsapp_receives_voice_true_for_voice_messages(): void
    {
        [$contact] = $this->conversation(type: 'text');
        $whatsapp = Mockery::mock(WhatsappService::class, ['token', 'v20.0', 'app', 'phone-1', 'waba-1', (int) $contact->organization_id])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $whatsapp->shouldReceive('sendHttpRequest')->once()
            ->withArgs(fn ($method, $url, $data) => $data['type'] === 'audio' && ($data['audio']['voice'] ?? null) === true)
            ->andReturn((object) ['success' => true, 'data' => (object) ['messages' => [(object) ['id' => 'wamid.voice-out']]]]);

        $result = $whatsapp->sendMedia((string) $contact->uuid, 'audio', 'reply.ogg', 'public/audios/reply.ogg', 'https://botzo.test/media/public/audios/reply.ogg', 'local', null, 'نص الرد', ['voice' => true, 'content_type' => 'audio/ogg', 'size' => 100]);

        $this->assertTrue($result->success);
        $this->assertSame('نص الرد', data_get(json_decode(Chat::where('wam_id', 'wamid.voice-out')->value('metadata'), true), 'transcript'));
    }

    private function fakeOpenAi(string $transcript, string $reply, string $speech): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions' => Http::response(['text' => $transcript]),
            'api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => $reply]]]]),
            'api.openai.com/v1/audio/speech' => Http::response($speech, 200, ['Content-Type' => 'audio/ogg']),
        ]);
    }

    private function serviceWith(WhatsappService $whatsapp): AIResponseService
    {
        $service = Mockery::mock(AIResponseService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('initializeWhatsappService')->andReturn($whatsapp);

        return $service;
    }

    /**
     * @return array{0:Contact,1:Chat}
     */
    private function conversation(string $type): array
    {
        [, $organization] = $this->createOwnerContext(['ai' => [
            'active' => true,
            'enable_automatic_responses' => true,
            'model' => 'gpt-4o-mini',
            'key_source' => 'organization',
            'api_key_encrypted' => Crypt::encryptString('sk-test'),
            'allow_audio_response' => true,
            'voice' => 'nova',
            'persona' => ['business_name' => 'مطعم بيت الكبسة', 'dialect' => 'gulf'],
        ]], true);

        $contact = Contact::create([
            'first_name' => 'Customer',
            'phone' => '+9665'.random_int(10000000, 99999999),
            'organization_id' => $organization->id,
            'created_by' => 0,
            'ai_assistance_enabled' => 1,
        ]);
        app(AiDisclosureService::class)->markDisclosed((int) $organization->id, (int) $contact->id);

        $mediaId = null;
        if ($type === 'audio') {
            $storagePath = ChatMedia::PRIVATE_FOLDER.'/'.$organization->id.'/'.Str::random(10).'.ogg';
            Storage::disk('local')->put($storagePath, 'OggS-voice');
            $this->files[] = $storagePath;
            $mediaId = ChatMedia::create([
                'name' => 'N/A',
                'storage_path' => $storagePath,
                'visibility' => ChatMedia::VISIBILITY_PRIVATE,
                'type' => 'audio/ogg; codecs=opus',
                'size' => 10,
                'location' => 'local',
            ])->id;
        }

        $chat = Chat::create([
            'organization_id' => $organization->id,
            'wam_id' => 'wamid.'.Str::random(12),
            'contact_id' => $contact->id,
            'type' => 'inbound',
            'metadata' => json_encode($type === 'audio'
                ? ['type' => 'audio', 'audio' => ['id' => 'm1', 'mime_type' => 'audio/ogg; codecs=opus', 'voice' => true]]
                : ['type' => 'text', 'text' => ['body' => 'مرحبا']]),
            'media_id' => $mediaId,
            'status' => 'delivered',
            'created_at' => now(),
        ]);

        return [$contact, $chat];
    }
}
