<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Services\IntelliReply\AiConversationPauseService;
use App\Services\IntelliReply\AiDisclosureService;
use App\Services\IntelliReply\AiReplyFallbackService;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class AiReplyFallbackTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'intellireply.fallback.text' => 'عذراً، فريقنا هيرد عليك قريب.',
            'intellireply.fallback.cooldown_minutes' => 30,
            'intellireply.fallback.pause_minutes' => 15,
            'intellireply.disclosure.default_text' => 'أنا بوت {business}.',
        ]);
    }

    public function test_the_customer_gets_an_apology_once_and_automatic_replies_pause(): void
    {
        $contact = $this->contact();
        app(AiDisclosureService::class)->markDisclosed((int) $contact->organization_id, (int) $contact->id);
        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendMessage')->once()->with((string) $contact->uuid, 'عذراً، فريقنا هيرد عليك قريب.')->andReturn((object) ['success' => true]);
        $fallback = $this->fallbackWith($whatsapp);

        $this->assertTrue($fallback->handle($contact, 'timeout'));
        $this->assertTrue($fallback->handle($contact, 'timeout'), 'A second failure within the cooldown stays quiet but counts as handled.');

        $this->assertTrue(app(AiConversationPauseService::class)->isPaused($contact->fresh()));
    }

    public function test_the_apology_carries_the_disclosure_when_it_is_the_first_automatic_message(): void
    {
        $contact = $this->contact();
        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendMessage')->once()
            ->with((string) $contact->uuid, Mockery::on(fn ($message) => str_starts_with($message, 'أنا بوت ') && str_ends_with($message, 'عذراً، فريقنا هيرد عليك قريب.')))
            ->andReturn((object) ['success' => true]);

        $this->fallbackWith($whatsapp)->handle($contact, 'model_error');

        $this->assertNull(app(AiDisclosureService::class)->pendingFor((int) $contact->organization_id, (int) $contact->id, [], 'x'));
    }

    public function test_a_failed_send_is_not_treated_as_handled_and_does_not_pause(): void
    {
        $contact = $this->contact();
        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendMessage')->once()->andReturn((object) ['success' => false]);

        $this->assertFalse($this->fallbackWith($whatsapp)->handle($contact, 'timeout'));
        $this->assertFalse(app(AiConversationPauseService::class)->isPaused($contact->fresh()));
    }

    private function fallbackWith(WhatsappService $whatsapp): AiReplyFallbackService
    {
        $fallback = Mockery::mock(AiReplyFallbackService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $fallback->shouldReceive('whatsapp')->andReturn($whatsapp);

        return $fallback;
    }

    private function contact(): Contact
    {
        [, $organization] = $this->createOwnerContext(['ai' => ['active' => true]], true);

        return Contact::create([
            'first_name' => 'Customer',
            'phone' => '+201001234567',
            'organization_id' => $organization->id,
            'created_by' => 0,
        ]);
    }
}
