<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Contact;
use App\Services\AutoReplyDispatchService;
use App\Services\AutoReplyService;
use App\Services\ChatService;
use App\Services\IntelliReply\AiConversationPauseService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class AiHumanTakeoverTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_a_successful_agent_reply_pauses_automatic_replies_for_the_configured_time(): void
    {
        config(['intellireply.human_takeover_minutes' => 60]);
        $contact = $this->contact();

        $this->afterAgentReply($contact, (object) ['success' => true]);

        $pausedUntil = Carbon::parse($contact->fresh()->ai_paused_until);
        $this->assertTrue($pausedUntil->between(now()->addMinutes(59), now()->addMinutes(61)));
    }

    public function test_a_failed_agent_reply_does_not_pause(): void
    {
        $contact = $this->contact();

        $this->afterAgentReply($contact, (object) ['success' => false]);

        $this->assertNull($contact->fresh()->ai_paused_until);
    }

    public function test_zero_minutes_disables_the_takeover_pause(): void
    {
        config(['intellireply.human_takeover_minutes' => 0]);
        $contact = $this->contact();

        $this->afterAgentReply($contact, (object) ['success' => true]);

        $this->assertNull($contact->fresh()->ai_paused_until);
    }

    public function test_automatic_replies_are_skipped_while_paused_and_resume_after(): void
    {
        $contact = $this->contact();
        $pause = app(AiConversationPauseService::class);
        $pause->pauseFor($contact, 30);

        $autoReplies = Mockery::mock(AutoReplyService::class);
        $autoReplies->shouldReceive('checkAutoReply')->once();
        $this->app->instance(AutoReplyService::class, $autoReplies);

        $dispatcher = app(AutoReplyDispatchService::class);
        $dispatcher->run($this->inboundChat($contact, 'wamid.P1'));

        $this->travel(31)->minutes();
        $dispatcher->run($this->inboundChat($contact, 'wamid.P2'));
    }

    public function test_resume_clears_the_pause(): void
    {
        $contact = $this->contact();
        $pause = app(AiConversationPauseService::class);
        $pause->pauseFor($contact, 30);

        $pause->resume($contact);

        $this->assertFalse($pause->isPaused($contact->fresh()));
    }

    private function afterAgentReply(Contact $contact, object $response): void
    {
        $service = (new \ReflectionClass(ChatService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(ChatService::class, 'afterAgentReply');
        $method->invoke($service, $contact, $response);
    }

    private function contact(): Contact
    {
        [, $organization] = $this->createOwnerContext([], true);

        return Contact::create([
            'first_name' => 'Customer',
            'phone' => '+201001234567',
            'organization_id' => $organization->id,
            'created_by' => 0,
        ]);
    }

    private function inboundChat(Contact $contact, string $wamId): Chat
    {
        return Chat::create([
            'organization_id' => $contact->organization_id,
            'wam_id' => $wamId,
            'contact_id' => $contact->id,
            'type' => 'inbound',
            'metadata' => json_encode(['type' => 'text', 'text' => ['body' => 'hi']]),
            'status' => 'delivered',
            'created_at' => now(),
        ]);
    }
}
