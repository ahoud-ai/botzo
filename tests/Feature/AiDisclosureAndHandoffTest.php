<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Contact;
use App\Models\Organization;
use App\Services\AutoReplyDispatchService;
use App\Services\AutoReplyService;
use App\Services\IntelliReply\AiConversationPauseService;
use App\Services\IntelliReply\AiDisclosureService;
use App\Services\IntelliReply\AiHumanHandoffService;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class AiDisclosureAndHandoffTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_disclosure_is_sent_once_per_conversation(): void
    {
        config(['intellireply.disclosure.default_text' => 'أنا المساعد الآلي لـ {business}.', 'intellireply.disclosure.conversation_hours' => 24]);
        $disclosure = app(AiDisclosureService::class);

        $this->assertSame('أنا المساعد الآلي لـ متجر نور.', $disclosure->pendingFor(1, 10, [], 'متجر نور'));

        $disclosure->markDisclosed(1, 10);
        $this->assertNull($disclosure->pendingFor(1, 10, [], 'متجر نور'));
        $this->assertNotNull($disclosure->pendingFor(1, 11, [], 'متجر نور'), 'Another contact starts its own conversation.');

        $this->travel(25)->hours();
        $this->assertNotNull($disclosure->pendingFor(1, 10, [], 'متجر نور'), 'A new conversation discloses again.');
    }

    public function test_each_organization_can_customize_the_disclosure_text(): void
    {
        $text = app(AiDisclosureService::class)->textFor(
            ['ai' => ['disclosure_text' => 'هلا! معك بوت {business}، اكتب موظف للتحدث معنا.']],
            'نويت'
        );

        $this->assertSame('هلا! معك بوت نويت، اكتب موظف للتحدث معنا.', $text);
    }

    public function test_keyword_matching_normalizes_arabic_and_matches_whole_words_only(): void
    {
        $handoff = app(AiHumanHandoffService::class);
        $keywords = ['موظف', 'انسان', 'خدمة العملاء'];

        $this->assertTrue($handoff->asksForHuman('عايز اكلم موظف لو سمحت', $keywords));
        $this->assertTrue($handoff->asksForHuman('أبي إنسان', $keywords));
        $this->assertTrue($handoff->asksForHuman('ممكن خدمه العملاء؟', $keywords));
        $this->assertTrue($handoff->asksForHuman('مُوظّف', $keywords));
        $this->assertFalse($handoff->asksForHuman('الموظفين عندكم ممتازين', $keywords));
        $this->assertFalse($handoff->asksForHuman('بكام الخدمة؟', $keywords));
    }

    public function test_asking_for_a_person_pauses_automatic_replies_and_confirms(): void
    {
        config(['intellireply.handoff.confirmation_text' => 'حوّلناك لفريقنا.', 'intellireply.handoff.pause_minutes' => 720]);
        [$organization, $contact] = $this->contactContext();

        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendMessage')->once()->with((string) $contact->uuid, 'حوّلناك لفريقنا.')->andReturn((object) ['success' => true]);
        $this->bindHandoff(assistantActive: true, whatsapp: $whatsapp);
        $this->expectAutoReplies(0);

        app(AutoReplyDispatchService::class)->run($this->inboundChat($organization, $contact, 'wamid.H1', 'عايز اكلم موظف'));

        $this->assertTrue(app(AiConversationPauseService::class)->isPaused($contact->fresh()));
    }

    public function test_handoff_keywords_are_ignored_when_the_assistant_is_off(): void
    {
        [$organization, $contact] = $this->contactContext();
        $this->bindHandoff(assistantActive: false, whatsapp: Mockery::mock(WhatsappService::class));
        $this->expectAutoReplies(1);

        app(AutoReplyDispatchService::class)->run($this->inboundChat($organization, $contact, 'wamid.H2', 'موظف'));

        $this->assertFalse(app(AiConversationPauseService::class)->isPaused($contact->fresh()));
    }

    public function test_regular_messages_go_to_the_normal_auto_reply_flow(): void
    {
        [$organization, $contact] = $this->contactContext();
        $this->bindHandoff(assistantActive: true, whatsapp: Mockery::mock(WhatsappService::class));
        $this->expectAutoReplies(1);

        app(AutoReplyDispatchService::class)->run($this->inboundChat($organization, $contact, 'wamid.H3', 'بكام الخدمة؟'));
    }

    private function bindHandoff(bool $assistantActive, WhatsappService $whatsapp): void
    {
        $handoff = Mockery::mock(AiHumanHandoffService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $handoff->shouldReceive('assistantActive')->andReturn($assistantActive);
        $handoff->shouldReceive('whatsapp')->andReturn($whatsapp);
        $this->app->instance(AiHumanHandoffService::class, $handoff);
    }

    private function expectAutoReplies(int $times): void
    {
        $autoReplies = Mockery::mock(AutoReplyService::class);
        $autoReplies->shouldReceive('checkAutoReply')->times($times);
        $this->app->instance(AutoReplyService::class, $autoReplies);
    }

    private function contactContext(): array
    {
        [, $organization] = $this->createOwnerContext(['ai' => ['active' => true]], true);

        $contact = Contact::create([
            'first_name' => 'Customer',
            'phone' => '+201001234567',
            'organization_id' => $organization->id,
            'created_by' => 0,
        ]);

        return [$organization, $contact];
    }

    private function inboundChat(Organization $organization, Contact $contact, string $wamId, string $body): Chat
    {
        return Chat::create([
            'organization_id' => $organization->id,
            'wam_id' => $wamId,
            'contact_id' => $contact->id,
            'type' => 'inbound',
            'metadata' => json_encode(['type' => 'text', 'text' => ['body' => $body]], JSON_UNESCAPED_UNICODE),
            'status' => 'delivered',
            'created_at' => now(),
        ]);
    }
}
