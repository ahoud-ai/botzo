<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Contact;
use App\Services\AddonStateService;
use App\Services\IntelliReply\AiDisclosureService;
use App\Services\IntelliReply\AiReplyFallbackService;
use App\Services\IntelliReply\Channels\AiReplyChannel;
use App\Services\IntelliReply\Channels\WhatsappReplyChannel;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use Modules\IntelliReply\Services\AIResponseService;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

/**
 * Automatic replies go out through the contact's reply channel, so another channel
 * (the website widget) can reuse the whole reply pipeline without WhatsApp.
 */
class AiReplyChannelTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(AddonStateService::class)->shouldReceive('isModuleEnabledForOrganization')->andReturn(true);
        config(['logging.channels.intellireply' => ['driver' => 'null']]);
        config(['intellireply.disclosure.default_text' => 'أنا بوت {business}.']);
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'أهلاً وسهلاً!']]]])]);
    }

    public function test_the_reply_and_the_disclosure_go_out_through_the_channel_only(): void
    {
        [$contact, $chat] = $this->conversation();
        $channel = new RecordingReplyChannel();
        $service = Mockery::mock(AIResponseService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('replyChannel')->andReturn($channel);
        $service->shouldNotReceive('initializeWhatsappService');

        $this->assertTrue($service->handleAIResponse($chat, ''));

        $this->assertCount(1, $channel->texts);
        $this->assertStringStartsWith('أنا بوت ', $channel->texts[0]);
        $this->assertStringEndsWith('أهلاً وسهلاً!', $channel->texts[0]);
        $this->assertNull(app(AiDisclosureService::class)->pendingFor((int) $contact->organization_id, (int) $contact->id, [], 'x'));
    }

    public function test_a_failed_channel_send_keeps_the_disclosure_pending(): void
    {
        [$contact, $chat] = $this->conversation();
        $channel = new RecordingReplyChannel(accepts: false);
        $service = Mockery::mock(AIResponseService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('replyChannel')->andReturn($channel);

        $service->handleAIResponse($chat, '');

        $this->assertNotNull(app(AiDisclosureService::class)->pendingFor((int) $contact->organization_id, (int) $contact->id, [], 'x'));
    }

    public function test_fallback_messages_go_out_through_the_channel(): void
    {
        [$contact] = $this->conversation();
        app(AiDisclosureService::class)->markDisclosed((int) $contact->organization_id, (int) $contact->id);
        $channel = new RecordingReplyChannel();
        $fallback = Mockery::mock(AiReplyFallbackService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $fallback->shouldReceive('replyChannel')->andReturn($channel);
        $fallback->shouldNotReceive('whatsapp');

        $this->assertTrue($fallback->askToType($contact, 'ممكن تكتب سؤالك؟', 'voice_failed'));
        $this->assertSame(['ممكن تكتب سؤالك؟'], $channel->texts);
    }

    public function test_the_whatsapp_channel_builds_the_client_once_and_only_when_sending(): void
    {
        [$contact] = $this->conversation();
        $builds = 0;
        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendMessage')->twice()->with((string) $contact->uuid, Mockery::any())
            ->andReturn((object) ['success' => true], (object) ['success' => false]);
        $channel = new WhatsappReplyChannel(function () use (&$builds, $whatsapp) {
            $builds++;

            return $whatsapp;
        });

        $this->assertSame(0, $builds);
        $this->assertTrue($channel->sendText($contact, 'one'));
        $this->assertFalse($channel->sendText($contact, 'two'));
        $this->assertSame(1, $builds);
    }

    /**
     * @return array{0:Contact,1:Chat}
     */
    private function conversation(): array
    {
        [, $organization] = $this->createOwnerContext(['ai' => [
            'active' => true,
            'model' => 'gpt-4o-mini',
            'key_source' => 'organization',
            'api_key_encrypted' => Crypt::encryptString('sk-test'),
        ]], true);

        $contact = Contact::create([
            'first_name' => 'Visitor',
            'phone' => '+9665'.random_int(10000000, 99999999),
            'organization_id' => $organization->id,
            'created_by' => 0,
            'ai_assistance_enabled' => 1,
        ]);

        $chat = Chat::create([
            'organization_id' => $organization->id,
            'wam_id' => 'wamid.'.Str::random(12),
            'contact_id' => $contact->id,
            'type' => 'inbound',
            'metadata' => json_encode(['type' => 'text', 'text' => ['body' => 'السلام عليكم']]),
            'status' => 'delivered',
            'created_at' => now(),
        ]);

        return [$contact, $chat];
    }
}

class RecordingReplyChannel implements AiReplyChannel
{
    /** @var list<string> */
    public array $texts = [];

    public function __construct(private readonly bool $accepts = true)
    {
    }

    public function sendText(Contact $contact, string $text): bool
    {
        $this->texts[] = $text;

        return $this->accepts;
    }

    public function sendVoice(Contact $contact, array $organizationMetadata, string $apiKey, ?string $keySource, string $text): bool
    {
        return false;
    }
}
