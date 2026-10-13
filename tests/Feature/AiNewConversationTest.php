<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Contact;
use App\Services\AddonStateService;
use App\Services\IntelliReply\AiDisclosureService;
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
 * "Enable automatic AI assistance for new conversations": a contact whose last message is
 * older than a day is switched on automatically, and every silent outcome leaves a reason.
 */
class AiNewConversationTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(AddonStateService::class)->shouldReceive('isModuleEnabledForOrganization')->andReturn(true);
        $this->logPath = storage_path('logs/intellireply-test-'.uniqid().'.log');
        config(['logging.channels.intellireply' => ['driver' => 'single', 'path' => $this->logPath, 'level' => 'info']]);
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'أهلاً وسهلاً!']]]])]);
    }

    protected function tearDown(): void
    {
        @unlink($this->logPath);

        parent::tearDown();
    }

    public function test_a_new_conversation_switches_the_contact_on_and_gets_a_reply(): void
    {
        [$contact, $chat] = $this->conversation(aiActive: true, previousMessageAgo: now()->subDays(2));
        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendMessage')->once()->andReturn((object) ['success' => true]);

        $this->assertTrue($this->serviceWith($whatsapp)->handleAIResponse($chat, ''));
        $this->assertSame(1, (int) $contact->fresh()->ai_assistance_enabled);
    }

    public function test_an_inactive_assistant_stays_silent_and_says_why(): void
    {
        [, $chat] = $this->conversation(aiActive: false, previousMessageAgo: now()->subDays(2));

        $this->assertFalse($this->serviceWith($this->silentWhatsapp())->handleAIResponse($chat, ''));
        Http::assertNothingSent();
        $this->assertStringContainsString('"reason":"assistant_inactive"', (string) file_get_contents($this->logPath));
    }

    public function test_an_ongoing_conversation_is_not_switched_on_automatically_and_says_why(): void
    {
        [$contact, $chat] = $this->conversation(aiActive: true, previousMessageAgo: now()->subHours(2));

        $this->assertFalse($this->serviceWith($this->silentWhatsapp())->handleAIResponse($chat, ''));
        $this->assertSame(0, (int) $contact->fresh()->ai_assistance_enabled);
        $this->assertStringContainsString('"reason":"contact_not_enabled"', (string) file_get_contents($this->logPath));
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
    private function conversation(bool $aiActive, \DateTimeInterface $previousMessageAgo): array
    {
        [, $organization] = $this->createOwnerContext(['ai' => [
            'active' => $aiActive,
            'enable_automatic_responses' => true,
            'model' => 'gpt-4o-mini',
            'key_source' => 'organization',
            'api_key_encrypted' => Crypt::encryptString('sk-test'),
        ]], true);

        $contact = Contact::create([
            'first_name' => 'Customer',
            'phone' => '+9665'.random_int(10000000, 99999999),
            'organization_id' => $organization->id,
            'created_by' => 0,
            'ai_assistance_enabled' => 0,
        ]);
        app(AiDisclosureService::class)->markDisclosed((int) $organization->id, (int) $contact->id);

        foreach ([[$previousMessageAgo, 'Hello'], [now(), 'السلام عليكم']] as [$at, $text]) {
            $chat = Chat::create([
                'organization_id' => $organization->id,
                'wam_id' => 'wamid.'.Str::random(12),
                'contact_id' => $contact->id,
                'type' => 'inbound',
                'metadata' => json_encode(['type' => 'text', 'text' => ['body' => $text]]),
                'status' => 'delivered',
                'created_at' => $at,
            ]);
        }

        return [$contact, $chat];
    }
}
