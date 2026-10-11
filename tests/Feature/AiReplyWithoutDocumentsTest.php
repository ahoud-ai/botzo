<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Contact;
use App\Services\AddonStateService;
use App\Services\IntelliReply\AiConversationPauseService;
use App\Services\IntelliReply\AiDisclosureService;
use App\Services\IntelliReply\AiReplyFallbackService;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Mockery;
use Modules\IntelliReply\Services\AIResponseService;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class AiReplyWithoutDocumentsTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['intellireply.unknown_answer.text' => 'هحوّلك لزميل من الفريق.']);
        $this->mock(AddonStateService::class)->shouldReceive('isModuleEnabledForOrganization')->andReturn(true);
    }

    public function test_the_assistant_replies_from_the_persona_when_no_documents_exist(): void
    {
        [$contact, $chat] = $this->conversation('مواعيدكم إيه؟');
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'مواعيدنا يومياً من 12 الظهر لـ 10 بالليل.']]],
            'usage' => ['prompt_tokens' => 420, 'completion_tokens' => 18],
        ])]);

        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendMessage')->once()
            ->with((string) $contact->uuid, 'مواعيدنا يومياً من 12 الظهر لـ 10 بالليل.')
            ->andReturn((object) ['success' => true]);

        $this->assertTrue($this->serviceWith($whatsapp)->handleAIResponse($chat, 'مواعيدكم إيه؟'));

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/embeddings'));
        $this->assertDatabaseHas('ai_usage_logs', [
            'organization_id' => $contact->organization_id,
            'contact_id' => $contact->id,
            'purpose' => 'auto_reply',
            'outcome' => 'replied',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'key_source' => 'organization',
            'input_tokens' => 420,
            'output_tokens' => 18,
        ]);
        Http::assertSent(function (Request $request) {
            $system = (string) data_get($request->data(), 'messages.0.content');

            return str_contains($system, '"عيادة النور"')
                && str_contains($system, 'يومياً من 12 ظهراً حتى 10 مساءً')
                && str_contains($system, 'No documents were provided');
        });
    }

    public function test_the_chat_goes_to_the_team_when_the_assistant_cannot_answer(): void
    {
        [$contact, $chat] = $this->conversation('عايز أسترجع فلوسي');
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => '[[HANDOFF]]']]],
            'usage' => ['prompt_tokens' => 400, 'completion_tokens' => 3],
        ])]);

        $replyChannel = Mockery::mock(WhatsappService::class);
        $replyChannel->shouldNotReceive('sendMessage');

        $handoffChannel = Mockery::mock(WhatsappService::class);
        $handoffChannel->shouldReceive('sendMessage')->once()
            ->with((string) $contact->uuid, 'هحوّلك لزميل من الفريق.')
            ->andReturn((object) ['success' => true]);
        $fallback = Mockery::mock(AiReplyFallbackService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $fallback->shouldReceive('whatsapp')->andReturn($handoffChannel);
        $this->app->instance(AiReplyFallbackService::class, $fallback);

        $this->assertTrue($this->serviceWith($replyChannel)->handleAIResponse($chat, 'عايز أسترجع فلوسي'));
        $this->assertTrue(app(AiConversationPauseService::class)->isPaused($contact->fresh()));
        $this->assertDatabaseHas('ai_usage_logs', [
            'contact_id' => $contact->id,
            'outcome' => 'handoff',
            'input_tokens' => 400,
            'output_tokens' => 3,
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
    private function conversation(string $text): array
    {
        [, $organization] = $this->createOwnerContext(['ai' => [
            'active' => true,
            'enable_automatic_responses' => true,
            'model' => 'gpt-4o-mini',
            'key_source' => 'organization',
            'api_key_encrypted' => Crypt::encryptString('sk-test'),
            'persona' => [
                'business_name' => 'عيادة النور',
                'business_hours_address' => 'يومياً من 12 ظهراً حتى 10 مساءً',
                'dialect' => 'egyptian',
            ],
        ]], true);

        $contact = Contact::create([
            'first_name' => 'Customer',
            'phone' => '+201001234567',
            'organization_id' => $organization->id,
            'created_by' => 0,
            'ai_assistance_enabled' => 1,
        ]);
        app(AiDisclosureService::class)->markDisclosed((int) $organization->id, (int) $contact->id);

        $chat = Chat::create([
            'organization_id' => $organization->id,
            'wam_id' => 'wamid.'.uniqid(),
            'contact_id' => $contact->id,
            'type' => 'inbound',
            'metadata' => json_encode(['type' => 'text', 'text' => ['body' => $text]]),
            'status' => 'delivered',
            'created_at' => now(),
        ]);

        return [$contact, $chat];
    }
}
