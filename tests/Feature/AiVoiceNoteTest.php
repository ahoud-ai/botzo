<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\ChatMedia;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Setting;
use App\Services\AddonStateService;
use App\Services\IntelliReply\AiDisclosureService;
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

    private function fakeOpenAi(string $transcript, string $reply): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions' => Http::response(['text' => $transcript]),
            'api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => $reply]]]]),
        ]);
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
    private function voiceConversation(array $ai = [], bool $contactEnabled = true, ?array $planMetadata = null): array
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
        file_put_contents(storage_path('app/public/'.$fileName), 'OggS-fake-voice');

        $media = ChatMedia::create([
            'name' => 'N/A',
            'path' => rtrim((string) config('app.url'), '/').'/media/public/'.$fileName,
            'type' => 'audio/ogg; codecs=opus',
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
