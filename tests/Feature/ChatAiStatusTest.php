<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckSubscriptionStatus;
use App\Models\Chat;
use App\Models\Contact;
use App\Models\ConversationHumanRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\IntelliReply\ConversationAiStatus;
use App\Services\IntelliReply\HumanRequestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

/** The strip above a conversation must say what the assistant will really do there. */
class ChatAiStatusTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    private const ON = ['ai' => ['active' => true]];
    private const OFF = ['ai' => ['active' => false]];

    public function test_the_master_switch_off_wins_over_this_conversations_own_switch(): void
    {
        $contact = $this->contact(['ai_assistance_enabled' => 1]);

        $this->assertSame('off_everywhere', $this->state($contact, self::OFF));
        $this->assertSame('off_everywhere', $this->state($contact, []));
    }

    public function test_an_open_request_for_a_person_shows_as_waiting_for_the_team(): void
    {
        $contact = $this->contact(['ai_assistance_enabled' => 1]);
        app(HumanRequestService::class)->open($contact, ConversationHumanRequest::REASON_CUSTOMER_REQUEST);

        $status = app(ConversationAiStatus::class)->for($contact->fresh(), self::ON);
        $this->assertSame('waiting_team', $status['state']);
        $this->assertSame('customer_request', $status['request_reason']);
    }

    public function test_a_request_that_does_not_block_the_assistant_does_not_show_as_stopped(): void
    {
        $contact = $this->contact(['ai_assistance_enabled' => 1]);
        app(HumanRequestService::class)->open($contact, ConversationHumanRequest::REASON_ASSISTANT_OFF);

        $this->assertSame('on', $this->state($contact, self::ON));
    }

    public function test_a_team_reply_pause_shows_when_the_assistant_comes_back(): void
    {
        $contact = $this->contact(['ai_assistance_enabled' => 1, 'ai_paused_until' => now()->addMinutes(30)]);

        $status = app(ConversationAiStatus::class)->for($contact->fresh(), self::ON);
        $this->assertSame('team_replied', $status['state']);
        $this->assertNotNull($status['paused_until']);

        $contact->forceFill(['ai_paused_until' => now()->subMinute()])->save();
        $this->assertSame('on', $this->state($contact, self::ON));
    }

    public function test_this_conversations_own_switch_off_and_on(): void
    {
        $this->assertSame('off_here', $this->state($this->contact(['ai_assistance_enabled' => 0]), self::ON));
        $this->assertSame('on', $this->state($this->contact(['ai_assistance_enabled' => 1]), self::ON));
    }

    public function test_the_chat_page_sends_the_real_state_and_the_refresh_keeps_it_current(): void
    {
        [$owner, $organization] = $this->createOwnerContext(self::OFF, true);
        $this->enableAssistantAddon();
        $contact = $this->contact(['ai_assistance_enabled' => 1], $organization);
        $this->inbound($organization, $contact);

        $this->signedIn($owner, $organization)->get('/chats/'.$contact->uuid)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('User/Chat/Index')
                ->where('aiStatus.state', 'off_everywhere'));

        // Turning the master switch back on shows up on the next refresh of the open conversation.
        $organization->forceFill(['metadata' => json_encode(self::ON)])->save();
        $this->signedIn($owner, $organization)->getJson('/chats/'.$contact->uuid)
            ->assertOk()
            ->assertJsonPath('contact.uuid', (string) $contact->uuid)
            ->assertJsonPath('aiStatus.state', 'on');
    }

    public function test_without_the_assistant_addon_there_is_no_strip(): void
    {
        [$owner, $organization] = $this->createOwnerContext(self::ON, true);
        $contact = $this->contact(['ai_assistance_enabled' => 1], $organization);
        $this->inbound($organization, $contact);

        $addons = Mockery::mock(\App\Services\AddonStateService::class)->makePartial();
        $addons->shouldReceive('isModuleEnabledForOrganization')->andReturn(false);
        $this->app->instance(\App\Services\AddonStateService::class, $addons);

        $this->signedIn($owner, $organization)->get('/chats/'.$contact->uuid)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('aiStatus', null));
    }

    public function test_turning_this_conversation_on_while_paused_shows_it_replying_again(): void
    {
        [$owner, $organization] = $this->createOwnerContext(self::ON, true);
        $this->enableAssistantAddon();
        $contact = $this->contact(['ai_assistance_enabled' => 1, 'ai_paused_until' => now()->addHour()], $organization);
        app(HumanRequestService::class)->open($contact, ConversationHumanRequest::REASON_CUSTOMER_REQUEST);
        $this->inbound($organization, $contact);

        $this->assertSame('waiting_team', $this->state($contact, self::ON));

        $this->signedIn($owner, $organization)->post('/automation/contact/'.$contact->uuid, ['ai_assistant' => true])
            ->assertRedirect();

        $this->assertSame('on', $this->state($contact, self::ON));
    }

    private function state(Contact $contact, array $metadata): string
    {
        return app(ConversationAiStatus::class)->for($contact->fresh(), $metadata)['state'];
    }

    private function contact(array $attributes = [], ?Organization $organization = null): Contact
    {
        $organization ??= $this->createOwnerContext(self::ON, true)[1];

        return Contact::create($attributes + [
            'first_name' => 'Customer',
            'phone' => '+2010'.random_int(10000000, 99999999),
            'organization_id' => $organization->id,
            'created_by' => 0,
        ]);
    }

    private function inbound(Organization $organization, Contact $contact): void
    {
        Chat::create([
            'organization_id' => $organization->id,
            'wam_id' => Str::random(16),
            'contact_id' => $contact->id,
            'type' => 'inbound',
            'metadata' => json_encode(['type' => 'text', 'text' => ['body' => 'hello']]),
            'status' => 'delivered',
            'created_at' => now(),
        ]);
    }

    private function enableAssistantAddon(): void
    {
        $addons = Mockery::mock(\App\Services\AddonStateService::class)->makePartial();
        $addons->shouldReceive('isModuleEnabledForOrganization')->andReturn(true);
        $this->app->instance(\App\Services\AddonStateService::class, $addons);
    }

    private function signedIn(User $user, Organization $organization): self
    {
        return $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id]);
    }
}
