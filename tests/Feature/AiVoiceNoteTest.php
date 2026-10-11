<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\ChatMedia;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Setting;
use App\Services\AddonStateService;
use App\Services\IntelliReply\AiDisclosureService;
use App\Services\IntelliReply\AiReplyFallbackService;
use App\Services\IntelliReply\AiReplySafetyGuard;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use Modules\IntelliReply\Services\AIResponseService;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;
use Tests\Unit\AudioDurationTest;

class AiVoiceNoteTest extends TestCase
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
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_a_voice_note_is_transcribed_and_answered_when_the_assistant_is_on(): void
    {
        [$contact, $chat] = $this->voiceConversation();
        $this->fakeOpenAi('عندكم توصيل؟', 'أيوه، بنوصّل لكل المحافظات.');

        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendMessage')->once()->andReturn((object) ['success' => true]);

        $this->assertTrue($this->serviceWith($whatsapp)->handleAIResponse($chat, ''));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/audio/transcriptions'));
    }

    public function test_nothing_is_sent_to_the_provider_when_the_assistant_is_off(): void
    {
        [, $chat] = $this->voiceConversation(['active' => false]);
        Http::fake();

        $this->assertFalse($this->serviceWith($this->silentWhatsapp())->handleAIResponse($chat, ''));
        Http::assertNothingSent();
    }

    public function test_nothing_is_sent_to_the_provider_when_the_contact_is_not_enabled(): void
    {
        [, $chat] = $this->voiceConversation(['enable_automatic_responses' => false], contactEnabled: false);
        Http::fake();

        $this->assertFalse($this->serviceWith($this->silentWhatsapp())->handleAIResponse($chat, ''));
        Http::assertNothingSent();
    }

    public function test_nothing_is_sent_to_the_provider_when_the_plan_limit_is_used_up(): void
    {
        Setting::updateOrCreate(['key' => 'enable_ai_billing'], ['value' => 1]);
        Setting::updateOrCreate(['key' => 'ai_global_api_key_encrypted'], ['value' => Crypt::encryptString('sk-global')]);
        [, $chat] = $this->voiceConversation(['key_source' => 'global', 'api_key_encrypted' => null], planMetadata: ['ai_text_response_limit' => 0]);
        Http::fake();

        $this->assertFalse($this->serviceWith($this->silentWhatsapp())->handleAIResponse($chat, ''));
        Http::assertNothingSent();
    }

    public function test_nothing_is_sent_to_the_provider_when_the_hourly_reply_cap_is_reached(): void
    {
        config(['intellireply.safety.max_replies_per_contact_per_hour' => 1]);
        [$contact, $chat] = $this->voiceConversation();
        app(AiReplySafetyGuard::class)->recordReplyToContact((int) $contact->organization_id, (int) $contact->id);
        Http::fake();

        $this->assertFalse($this->serviceWith($this->silentWhatsapp())->handleAIResponse($chat, ''));
        Http::assertNothingSent();
    }

    public function test_the_transcript_reaches_the_model_as_the_customer_message_and_is_stored(): void
    {
        [, $chat] = $this->voiceConversation(['persona' => ['dialect' => 'maghrebi', 'business_name' => 'Dar Atlas']]);
        $this->fakeOpenAi('بغيت نعرف واش كاين livraison لكازا؟', 'إيه، كاين التوصيل لكازا.');

        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendMessage')->once()->andReturn((object) ['success' => true]);
        $this->assertTrue($this->serviceWith($whatsapp)->handleAIResponse($chat, ''));

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), '/audio/transcriptions')) {
                return false;
            }

            $parts = collect($request->data())->keyBy('name');

            return $parts['file']['filename'] === 'voice.ogg'
                && ($parts['file']['headers']['Content-Type'] ?? null) === 'audio/ogg'
                && $parts['model']['contents'] === 'gpt-4o-mini-transcribe'
                && ! $parts->has('language')
                && str_contains($parts['prompt']['contents'], 'Dar Atlas')
                && str_contains($parts['prompt']['contents'], 'Maghrebi Arabic');
        });

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), '/chat/completions')) {
                return false;
            }

            $last = collect($request->data()['messages'])->last();

            return $last['role'] === 'user' && $last['content'] === 'بغيت نعرف واش كاين livraison لكازا؟';
        });

        $this->assertSame('بغيت نعرف واش كاين livraison لكازا؟', data_get(json_decode($chat->fresh()->metadata, true), 'ai_transcript.text'));
    }

    public function test_english_pins_the_language_and_a_stored_transcript_is_not_requested_again(): void
    {
        [, $chat] = $this->voiceConversation(['persona' => ['dialect' => 'english']]);
        $this->fakeOpenAi('Do you deliver?', 'Yes, we do.');
        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendMessage')->twice()->andReturn((object) ['success' => true]);

        $this->serviceWith($whatsapp)->handleAIResponse($chat, '');
        $this->serviceWith($whatsapp)->handleAIResponse($chat->fresh(), '');

        $transcriptions = Http::recorded(fn (Request $request) => str_contains($request->url(), '/audio/transcriptions'));
        $this->assertCount(1, $transcriptions);
        $this->assertSame(1, \App\Models\AiUsageLog::where('contact_id', $chat->contact_id)->where('purpose', 'transcription')->count(), 'The stored transcript is reused at no cost.');
        $this->assertSame('en', collect($transcriptions[0][0]->data())->keyBy('name')['language']['contents']);
    }

    public function test_unsupported_formats_are_not_sent_to_the_provider_and_the_customer_is_asked_to_type(): void
    {
        [$contact, $chat] = $this->voiceConversation(mimeType: 'audio/amr');
        Http::fake();
        $this->expectAskToType($contact, 'ممكن تكتب سؤالك؟');

        $this->assertTrue($this->serviceWith($this->silentWhatsapp())->handleAIResponse($chat, ''));
        Http::assertNothingSent();
    }

    public function test_a_voice_note_over_the_limit_is_not_transcribed_and_the_customer_is_told(): void
    {
        [$contact, $chat] = $this->voiceConversation(audio: AudioDurationTest::opus(150));
        Http::fake();
        $this->expectAskToType($contact, 'الفويس أطول من دقيقتين');

        $this->assertTrue($this->serviceWith($this->silentWhatsapp())->handleAIResponse($chat, ''));
        Http::assertNothingSent();
        $this->assertDatabaseMissing('ai_usage_logs', ['contact_id' => $chat->contact_id]);
    }

    public function test_a_short_enough_voice_note_is_transcribed(): void
    {
        [, $chat] = $this->voiceConversation(audio: AudioDurationTest::opus(90));
        $this->fakeOpenAi('عندكم فرع في جدة؟', 'أيوه، عندنا فرع في جدة.');
        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendMessage')->once()->andReturn((object) ['success' => true]);

        $this->assertTrue($this->serviceWith($whatsapp)->handleAIResponse($chat, ''));
        $this->assertEqualsWithDelta(90.0, data_get(json_decode($chat->fresh()->metadata, true), 'ai_transcript.seconds'), 0.1);

        // 1.5 minutes at the configured 0.003 USD per minute.
        $this->assertDatabaseHas('ai_usage_logs', [
            'contact_id' => $chat->contact_id,
            'purpose' => 'transcription',
            'outcome' => 'transcribed',
            'model' => 'gpt-4o-mini-transcribe',
            'key_source' => 'organization',
            'audio_seconds' => 90.0,
            'cost_usd' => 0.0045,
        ]);
    }

    public function test_a_failed_transcription_asks_the_customer_to_type_once_per_cooldown(): void
    {
        [$contact, $chat] = $this->voiceConversation();
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'server error']], 500)]);
        $this->expectAskToType($contact, 'ممكن تكتب سؤالك؟');

        $service = $this->serviceWith($this->silentWhatsapp());
        $this->assertTrue($service->handleAIResponse($chat, ''));
        $this->assertTrue($service->handleAIResponse($chat, ''), 'A second failure within the cooldown stays quiet.');
    }

    public function test_an_empty_transcript_counts_as_not_understood(): void
    {
        [$contact, $chat] = $this->voiceConversation();
        Http::fake(['api.openai.com/v1/audio/transcriptions' => Http::response(['text' => ' '])]);
        $this->expectAskToType($contact, 'ممكن تكتب سؤالك؟');

        $this->assertTrue($this->serviceWith($this->silentWhatsapp())->handleAIResponse($chat, ''));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/chat/completions'));
        $this->assertDatabaseHas('ai_usage_logs', ['contact_id' => $chat->contact_id, 'purpose' => 'transcription', 'outcome' => 'empty']);
    }

    public function test_a_voice_note_whose_file_never_arrived_asks_the_customer_to_type(): void
    {
        [$contact, $chat] = $this->voiceConversation();
        $chat->update(['media_id' => null]);
        Http::fake();
        $this->expectAskToType($contact, 'ممكن تكتب سؤالك؟');

        $this->assertTrue($this->serviceWith($this->silentWhatsapp())->handleAIResponse($chat->fresh(), ''));
        Http::assertNothingSent();
    }

    public function test_a_model_failure_after_transcription_uses_the_team_fallback(): void
    {
        config(['intellireply.fallback.text' => 'فريقنا هيرد عليك قريب.']);
        [$contact, $chat] = $this->voiceConversation();
        Http::fake([
            'api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'عندكم توصيل؟']),
            'api.openai.com/v1/chat/completions' => Http::response(['error' => ['message' => 'overloaded']], 503),
        ]);
        $this->expectAskToType($contact, 'فريقنا هيرد عليك قريب.');

        $this->assertTrue($this->serviceWith($this->silentWhatsapp())->handleAIResponse($chat, ''));
    }

    public function test_agent_suggestions_reuse_the_stored_transcript(): void
    {
        [$contact, $chat] = $this->voiceConversation();
        $metadata = json_decode($chat->metadata, true);
        $metadata['ai_transcript'] = ['text' => 'كم سعر التوصيل؟', 'model' => 'gpt-4o-mini-transcribe'];
        $chat->update(['metadata' => json_encode($metadata)]);
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'التوصيل بـ 20 ريال.']]]])]);

        $result = (new AIResponseService())->suggestReply((int) $contact->organization_id, $contact, ['key' => 'sk-test', 'source' => 'organization']);

        $this->assertTrue($result['success']);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/audio/transcriptions'));
        Http::assertSent(fn (Request $request) => str_contains(json_encode($request->data(), JSON_UNESCAPED_UNICODE), 'كم سعر التوصيل؟'));
    }

    public function test_downloaded_voice_notes_get_a_clean_file_extension(): void
    {
        $method = new \ReflectionMethod(\App\Jobs\ProcessWebhookMediaJob::class, 'generateFilename');
        $job = new \App\Jobs\ProcessWebhookMediaJob([], 1, 1);

        $this->assertStringEndsWith('.ogg', $method->invoke($job, 'x', 'audio/ogg; codecs=opus'));
        $this->assertStringEndsWith('.jpeg', $method->invoke($job, 'x', 'image/jpeg'));
    }

    private function fakeOpenAi(string $transcript, string $reply): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions' => Http::response(['text' => $transcript]),
            'api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => $reply]]]]),
        ]);
    }

    /**
     * The fallback service sends the "please type" (or team fallback) message; nothing else may be sent.
     */
    private function expectAskToType(Contact $contact, string $expectedFragment): void
    {
        $channel = Mockery::mock(WhatsappService::class);
        $channel->shouldReceive('sendMessage')->once()
            ->with((string) $contact->uuid, Mockery::on(fn ($message) => str_contains($message, $expectedFragment)))
            ->andReturn((object) ['success' => true]);

        $fallback = Mockery::mock(AiReplyFallbackService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $fallback->shouldReceive('whatsapp')->andReturn($channel);
        $this->app->instance(AiReplyFallbackService::class, $fallback);
    }

    private function silentWhatsapp(): WhatsappService
    {
        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldNotReceive('sendMessage');

        return $whatsapp;
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
    private function voiceConversation(array $ai = [], bool $contactEnabled = true, ?array $planMetadata = null, string $mimeType = 'audio/ogg; codecs=opus', string $audio = 'OggS-fake-voice'): array
    {
        [, $organization] = $this->createOwnerContext(['ai' => array_merge([
            'active' => true,
            'enable_automatic_responses' => true,
            'model' => 'gpt-4o-mini',
            'key_source' => 'organization',
            'api_key_encrypted' => Crypt::encryptString('sk-test'),
        ], $ai)], $planMetadata === null);

        if ($planMetadata !== null) {
            $this->createActiveSubscription($organization->id, $planMetadata);
        }

        $contact = Contact::create([
            'first_name' => 'Customer',
            'phone' => '+9665'.random_int(10000000, 99999999),
            'organization_id' => $organization->id,
            'created_by' => 0,
            'ai_assistance_enabled' => $contactEnabled ? 1 : 0,
        ]);
        app(AiDisclosureService::class)->markDisclosed((int) $organization->id, (int) $contact->id);

        // An earlier message today, so "new conversation" auto-enabling does not apply.
        Chat::create([
            'organization_id' => $organization->id,
            'wam_id' => 'wamid.'.Str::random(12),
            'contact_id' => $contact->id,
            'type' => 'inbound',
            'metadata' => json_encode(['type' => 'text', 'text' => ['body' => 'hello']]),
            'status' => 'delivered',
            'created_at' => now()->subHours(2),
        ]);

        $fileName = 'voice-test-'.Str::random(8).'.ogg';
        $this->files[] = storage_path('app/public/'.$fileName);
        @mkdir(storage_path('app/public'), 0777, true);
        file_put_contents(storage_path('app/public/'.$fileName), $audio);

        $media = ChatMedia::create([
            'name' => 'N/A',
            'path' => rtrim((string) config('app.url'), '/').'/media/public/'.$fileName,
            'type' => $mimeType,
            'size' => 15,
            'location' => 'local',
        ]);

        $chat = Chat::create([
            'organization_id' => $organization->id,
            'wam_id' => 'wamid.'.Str::random(12),
            'contact_id' => $contact->id,
            'type' => 'inbound',
            'metadata' => json_encode(['type' => 'audio', 'audio' => ['id' => 'media-1', 'mime_type' => 'audio/ogg; codecs=opus', 'voice' => true]]),
            'media_id' => $media->id,
            'status' => 'delivered',
            'created_at' => now(),
        ]);

        return [$contact, $chat];
    }
}
