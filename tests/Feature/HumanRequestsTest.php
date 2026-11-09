<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckSubscriptionStatus;
use App\Models\Chat;
use App\Models\ChatTicket;
use App\Models\Contact;
use App\Models\ConversationHumanRequest;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\SallaStore;
use App\Models\Team;
use App\Models\User;
use App\Models\WebWidget;
use App\Services\AutoReplyDispatchService;
use App\Services\AutoReplyService;
use App\Services\AutomationFlows\AutomationFlowConversationHandoffService;
use App\Services\ContactService;
use App\Services\IntelliReply\AiConversationPauseService;
use App\Services\IntelliReply\AiHumanHandoffService;
use App\Services\IntelliReply\AiReplyFallbackService;
use App\Services\IntelliReply\HumanRequestDetector;
use App\Services\IntelliReply\HumanRequestService;
use App\Services\Salla\SallaPanelConversations;
use App\Services\WebWidget\WebWidgetReplyService;
use App\Services\WebWidget\WebWidgetVoiceService;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Modules\IntelliReply\Services\AIResponseService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class HumanRequestsTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    private const KEYWORDS = ['موظف', 'انسان', 'بشري', 'مندوب', 'ممثل خدمة', 'خدمة العملاء', 'agent', 'human', 'customer service'];

    #[DataProvider('requests')]
    public function test_it_recognizes_a_request_for_a_person(string $message): void
    {
        $this->assertTrue(app(HumanRequestDetector::class)->asksForPerson($message, self::KEYWORDS), $message);
    }

    public static function requests(): array
    {
        return array_map(fn ($message) => [$message], [
            'موظف', 'موظف لو سمحت', 'عايز موظف', 'أبي إنسان', 'ممكن خدمة العملاء؟', 'مندوب', 'ممثل خدمة',
            'أبغى أكلم أحد', 'ابغا اكلم موظف', 'أبغى أتكلم مع موظف', 'ابي اكلم احد من الفريق',
            'عايز أكلم حد', 'عاوز اتكلم مع حد', 'ممكن اكلم موظف من فضلك', 'عايز اكلم الموظف',
            'حولني للموظف', 'حولوني لخدمة العملاء', 'وصلني بموظف', 'عايز حد يكلمني', 'ممكن حد يرد',
            'I want to talk to a human', 'talk to agent', 'connect me to a person', 'customer service please',
        ]);
    }

    #[DataProvider('ordinarySentences')]
    public function test_it_does_not_hand_over_on_a_sentence_that_only_mentions_a_person(string $message): void
    {
        $this->assertFalse(app(HumanRequestDetector::class)->asksForPerson($message, self::KEYWORDS), $message);
    }

    public static function ordinarySentences(): array
    {
        return array_map(fn ($message) => [$message], [
            'الموظف كان محترم', 'الموظفين عندكم ممتازين', 'كان موظف محترم جدا', 'شكرا للموظف', 'كلمت الموظف امبارح',
            'انا موظف عندكم', 'كنت عايز اشكر الموظف', 'ما ابغى اكلم احد', 'مش عايز موظف', 'مش عايز اكلم حد',
            'بكام الخدمة؟', 'خدمة العملاء عندكم حلوة', 'the agent was great', 'I am not a human',
        ]);
    }

    public function test_an_organization_list_of_words_counts_on_any_mention(): void
    {
        $detector = app(HumanRequestDetector::class);

        $this->assertTrue($detector->asksForPerson('اريد قسم المبيعات لو عندكم', ['المبيعات'], true));
        $this->assertFalse($detector->asksForPerson('اريد قسم المبيعات لو عندكم', ['المبيعات']));
    }

    public function test_a_request_opens_once_and_only_explicit_ones_block_the_assistant(): void
    {
        [, , $contact] = $this->conversation();
        $requests = app(HumanRequestService::class);

        $first = $requests->open($contact, ConversationHumanRequest::REASON_KNOWLEDGE_GAP);
        $this->assertTrue($first['created']);
        $this->assertFalse($requests->blocksAssistant($contact));

        $second = $requests->open($contact, ConversationHumanRequest::REASON_CUSTOMER_REQUEST);
        $this->assertFalse($second['created']);
        $this->assertTrue($requests->blocksAssistant($contact));
        $this->assertSame(1, ConversationHumanRequest::where('contact_id', $contact->id)->count());
        $this->assertSame(ConversationHumanRequest::REASON_CUSTOMER_REQUEST, $first['request']->fresh()->reason);

        // A softer reason never lowers it.
        $requests->open($contact, ConversationHumanRequest::REASON_AI_UNAVAILABLE);
        $this->assertTrue($requests->blocksAssistant($contact));
    }

    public function test_the_open_contact_column_refuses_a_second_open_request(): void
    {
        [, $organization, $contact] = $this->conversation();
        app(HumanRequestService::class)->open($contact, ConversationHumanRequest::REASON_ASSISTANT_OFF);

        $this->expectException(\Illuminate\Database\QueryException::class);

        ConversationHumanRequest::create([
            'organization_id' => $organization->id, 'contact_id' => $contact->id, 'open_contact_id' => $contact->id,
            'reason' => ConversationHumanRequest::REASON_ASSISTANT_OFF, 'opened_at' => now(),
        ]);
    }

    public function test_a_new_request_can_open_after_the_last_one_was_closed(): void
    {
        [, , $contact] = $this->conversation();
        $requests = app(HumanRequestService::class);

        $requests->open($contact, ConversationHumanRequest::REASON_CUSTOMER_REQUEST);
        $requests->close($contact, ConversationHumanRequest::RESOLUTION_MANUAL_CLOSE, 7);
        $closed = ConversationHumanRequest::where('contact_id', $contact->id)->sole();

        $this->assertNull($closed->open_contact_id);
        $this->assertNotNull($closed->resolved_at);
        $this->assertSame(7, (int) $closed->resolved_by);
        $this->assertSame('manual_close', $closed->resolution);
        $this->assertFalse($requests->blocksAssistant($contact));

        $this->assertTrue($requests->open($contact, ConversationHumanRequest::REASON_CUSTOMER_REQUEST)['created']);
        $this->assertSame(2, ConversationHumanRequest::where('contact_id', $contact->id)->count());
    }

    public function test_a_written_request_opens_a_blocking_request_and_confirms_once(): void
    {
        config(['intellireply.handoff.confirmation_text' => 'حوّلناك لفريقنا.']);
        [, $organization, $contact] = $this->conversation();
        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendMessage')->once()->andReturn((object) ['success' => true]);
        $this->bindHandoff($whatsapp);
        $this->expectAutoReplies(0);
        $dispatch = app(AutoReplyDispatchService::class);

        $dispatch->run($this->inbound($organization, $contact, 'أبغى أكلم أحد'));
        $dispatch->run($this->inbound($organization, $contact, 'لو سمحت موظف'));

        $request = ConversationHumanRequest::where('contact_id', $contact->id)->sole();
        $this->assertSame(ConversationHumanRequest::REASON_CUSTOMER_REQUEST, $request->reason);
        $this->assertTrue($request->blocks_assistant);
    }

    public function test_a_request_during_a_pause_reaches_the_team_without_a_repeated_message(): void
    {
        [, $organization, $contact] = $this->conversation();
        app(AiConversationPauseService::class)->pauseFor($contact, 60);
        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldNotReceive('sendMessage');
        $this->bindHandoff($whatsapp);
        $this->expectAutoReplies(0);

        app(AutoReplyDispatchService::class)->run($this->inbound($organization, $contact, 'عايز موظف'));

        $this->assertTrue(app(HumanRequestService::class)->blocksAssistant($contact));
    }

    public function test_a_request_followed_by_another_message_is_not_lost(): void
    {
        [, $organization, $contact] = $this->conversation();
        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendMessage')->andReturn((object) ['success' => true]);
        $this->bindHandoff($whatsapp);
        $this->expectAutoReplies(0);

        $this->inbound($organization, $contact, 'عايز اكلم حد');
        app(AutoReplyDispatchService::class)->run($this->inbound($organization, $contact, 'لو سمحت بسرعة'));

        $this->assertTrue(app(HumanRequestService::class)->blocksAssistant($contact));
    }

    public function test_an_old_request_already_answered_does_not_count_again(): void
    {
        [, $organization, $contact] = $this->conversation();
        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldNotReceive('sendMessage');
        $this->bindHandoff($whatsapp);
        $this->expectAutoReplies(1);

        $this->inbound($organization, $contact, 'عايز اكلم حد');
        Chat::create(['organization_id' => $organization->id, 'wam_id' => Str::random(12), 'contact_id' => $contact->id, 'type' => 'outbound', 'metadata' => json_encode(['type' => 'text', 'text' => ['body' => 'أهلا']]), 'status' => 'sent', 'created_at' => now()]);
        app(AutoReplyDispatchService::class)->run($this->inbound($organization, $contact, 'شكرا'));

        $this->assertFalse(app(HumanRequestService::class)->hasOpen($contact));
    }

    public function test_a_tapped_button_counts_as_a_request(): void
    {
        [, $organization, $contact] = $this->conversation();
        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendMessage')->andReturn((object) ['success' => true]);
        $this->bindHandoff($whatsapp);
        $this->expectAutoReplies(0);

        $chat = Chat::create(['organization_id' => $organization->id, 'wam_id' => Str::random(12), 'contact_id' => $contact->id, 'type' => 'inbound', 'status' => 'delivered', 'created_at' => now(), 'metadata' => json_encode(['type' => 'interactive', 'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => 'x', 'title' => 'اكلم موظف']]], JSON_UNESCAPED_UNICODE)]);
        app(AutoReplyDispatchService::class)->run($chat);

        $this->assertTrue(app(HumanRequestService::class)->blocksAssistant($contact));
    }

    public function test_a_transcribed_voice_note_can_ask_for_a_person(): void
    {
        [, $organization, $contact] = $this->conversation();
        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendMessage')->andReturn((object) ['success' => true]);
        $this->bindHandoff($whatsapp);

        $handled = app(AiHumanHandoffService::class)->handleTranscribedRequest($this->inbound($organization, $contact, ''), 'أبغى أكلم أحد');

        $this->assertTrue($handled);
        $this->assertTrue(app(HumanRequestService::class)->blocksAssistant($contact));
    }

    public function test_the_assistant_stays_out_of_a_conversation_with_an_open_request(): void
    {
        [, $organization, $contact] = $this->conversation();
        $this->enableAssistantAddon($organization);
        app(HumanRequestService::class)->open($contact, ConversationHumanRequest::REASON_CUSTOMER_REQUEST);
        $chat = $this->inbound($organization, $contact, 'بكام الخدمة؟');

        $reasons = [];
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('info')->andReturnUsing(function (string $message, array $context = []) use (&$reasons) {
            $reasons[] = $context['reason'] ?? null;
        });

        $this->assertFalse(app(AIResponseService::class)->handleAIResponse($chat, ''));
        $this->assertContains('open_human_request', $reasons);
    }

    public function test_handovers_from_the_model_and_flows_say_why(): void
    {
        config(['intellireply.handoff.confirmation_text' => '']);
        [, , $contact] = $this->conversation();
        $fallback = Mockery::mock(AiReplyFallbackService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $channel = Mockery::mock(\App\Services\IntelliReply\Channels\AiReplyChannel::class);
        $channel->shouldReceive('sendText')->andReturn(true);
        $fallback->shouldReceive('replyChannel')->andReturn($channel);

        $this->assertTrue($fallback->handOff($contact, 'سيرد الفريق', 15, 'ai_unavailable'));
        $this->assertSame('ai_unavailable', ConversationHumanRequest::where('open_contact_id', $contact->id)->value('reason'));

        [, , $second] = $this->conversation();
        $this->assertTrue($fallback->handOff($second, 'سيرد الفريق', 15, 'ai_knowledge_gap'));
        $this->assertSame('knowledge_gap', ConversationHumanRequest::where('open_contact_id', $second->id)->value('reason'));

        // With ticketing off the assignment fails, and the team must still hear about it.
        [, , $third] = $this->conversation();
        app(AutomationFlowConversationHandoffService::class)->startHumanHandoff($third);
        $this->assertSame('flow_handoff', ConversationHumanRequest::where('open_contact_id', $third->id)->value('reason'));
    }

    public function test_a_team_reply_and_resuming_the_assistant_close_the_request(): void
    {
        [, , $contact] = $this->conversation();
        $requests = app(HumanRequestService::class);
        $pause = app(AiConversationPauseService::class);

        $requests->open($contact, ConversationHumanRequest::REASON_CUSTOMER_REQUEST);
        $pause->pauseForHumanReply($contact, 9);
        $this->assertSame('team_reply', ConversationHumanRequest::where('contact_id', $contact->id)->latest('id')->value('resolution'));

        $requests->open($contact, ConversationHumanRequest::REASON_CUSTOMER_REQUEST);
        $pause->resume($contact);
        $this->assertSame('assistant_resumed', ConversationHumanRequest::where('contact_id', $contact->id)->latest('id')->value('resolution'));
        $this->assertFalse($requests->hasOpen($contact));
    }

    public function test_turning_the_assistant_back_on_in_a_conversation_closes_the_request(): void
    {
        [$owner, $organization, $contact] = $this->conversation();
        $this->enableAssistantAddon($organization);
        app(HumanRequestService::class)->open($contact, ConversationHumanRequest::REASON_CUSTOMER_REQUEST);

        $this->signedIn($owner, $organization)->post('/automation/contact/'.$contact->uuid, ['ai_assistant' => false]);
        $this->assertTrue(app(HumanRequestService::class)->hasOpen($contact));

        $this->signedIn($owner, $organization)->post('/automation/contact/'.$contact->uuid, ['ai_assistant' => true]);
        $this->assertFalse(app(HumanRequestService::class)->hasOpen($contact));
    }

    public function test_salla_panel_reply_closes_the_request_like_the_inbox(): void
    {
        [, $organization, $contact] = $this->conversation(['channel' => Contact::CHANNEL_WEB]);
        $store = $this->sallaStore($organization);
        app(HumanRequestService::class)->open($contact, ConversationHumanRequest::REASON_CUSTOMER_REQUEST);
        $panel = app(SallaPanelConversations::class);

        $this->assertTrue($panel->show($store, (int) $contact->id)['assistant_paused']);
        $this->assertTrue($panel->reply($store, (int) $contact->id, 'أهلا، معاك الفريق'));

        $request = ConversationHumanRequest::where('contact_id', $contact->id)->sole();
        $this->assertSame('team_reply', $request->resolution);
        $this->assertNull($request->open_contact_id);
    }

    public function test_salla_panel_resuming_the_assistant_closes_the_request(): void
    {
        [, $organization, $contact] = $this->conversation(['channel' => Contact::CHANNEL_WEB]);
        $store = $this->sallaStore($organization);
        app(HumanRequestService::class)->open($contact, ConversationHumanRequest::REASON_CUSTOMER_REQUEST);
        $panel = app(SallaPanelConversations::class);

        $this->assertTrue($panel->resumeAssistant($store, (int) $contact->id));

        $this->assertSame('assistant_resumed', ConversationHumanRequest::where('contact_id', $contact->id)->value('resolution'));
        $this->assertFalse($panel->show($store, (int) $contact->id)['assistant_paused']);
    }

    public function test_salla_panel_cannot_close_another_stores_request(): void
    {
        [, $organization] = $this->conversation(['channel' => Contact::CHANNEL_WEB]);
        [, , $otherContact] = $this->conversation(['channel' => Contact::CHANNEL_WEB]);
        app(HumanRequestService::class)->open($otherContact, ConversationHumanRequest::REASON_CUSTOMER_REQUEST);
        $panel = app(SallaPanelConversations::class);
        $store = $this->sallaStore($organization);

        $this->assertFalse($panel->reply($store, (int) $otherContact->id, 'x'));
        $this->assertFalse($panel->resumeAssistant($store, (int) $otherContact->id));
        $this->assertTrue(app(HumanRequestService::class)->hasOpen($otherContact));
    }

    public function test_salla_purge_removes_the_requests_of_the_purged_store_only(): void
    {
        [, $organization, $contact] = $this->conversation();
        [, , $otherContact] = $this->conversation();
        $requests = app(HumanRequestService::class);
        $requests->open($contact, ConversationHumanRequest::REASON_CUSTOMER_REQUEST);
        $requests->open($otherContact, ConversationHumanRequest::REASON_CUSTOMER_REQUEST);
        $requests->close($contact, ConversationHumanRequest::RESOLUTION_MANUAL_CLOSE);
        $requests->open($contact, ConversationHumanRequest::REASON_ASSISTANT_OFF);

        SallaStore::create(['merchant_id' => random_int(1000000, 99999999), 'organization_id' => $organization->id, 'store_name' => 'x', 'status' => SallaStore::STATUS_UNINSTALLED, 'purge_after' => now()->subDay()]);

        $this->artisan('salla:purge')->assertSuccessful();

        $this->assertSame(0, ConversationHumanRequest::where('organization_id', $organization->id)->count());
        $this->assertSame(1, ConversationHumanRequest::where('contact_id', $otherContact->id)->count());
    }

    public function test_deleting_contacts_or_clearing_a_chat_removes_their_requests(): void
    {
        [$owner, $organization, $one] = $this->conversation();
        $two = $this->contact($organization);
        $three = $this->contact($organization);
        $requests = app(HumanRequestService::class);
        foreach ([$one, $two, $three] as $contact) {
            $requests->open($contact, ConversationHumanRequest::REASON_CUSTOMER_REQUEST);
        }

        (new ContactService($organization->id))->delete([$one->uuid]);
        $this->assertSame(0, ConversationHumanRequest::where('contact_id', $one->id)->count());
        $this->assertSame(1, ConversationHumanRequest::where('contact_id', $two->id)->count());

        $this->signedIn($owner, $organization)->delete('/chats/'.$two->uuid);
        $this->assertSame(0, ConversationHumanRequest::where('contact_id', $two->id)->count());
        $this->assertSame(1, ConversationHumanRequest::where('contact_id', $three->id)->count());

        (new ContactService($organization->id))->delete([]);
        $this->assertSame(0, ConversationHumanRequest::where('organization_id', $organization->id)->count());
    }

    public function test_the_notifications_page_shows_each_open_request_and_where_to_act(): void
    {
        [$owner, $organization, $contact] = $this->conversation();
        app(HumanRequestService::class)->open($contact, ConversationHumanRequest::REASON_VOICE_REQUEST);

        $this->signedIn($owner, $organization)->getJson('/human-requests')
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('count', 1);

        $this->signedIn($owner, $organization)->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('User/Notifications/Index')
                ->has('open', 1)
                ->where('open.0.reason', 'voice_request')
                ->where('open.0.action_url', '/chats/'.$contact->uuid)
                ->has('open.0.opened_at')
                ->missing('open.0.text')
                ->has('closed', 0));
    }

    public function test_the_closed_tab_shows_how_and_by_whom_for_the_last_30_days(): void
    {
        [$owner, $organization, $recent] = $this->conversation();
        $old = $this->contact($organization);
        $requests = app(HumanRequestService::class);

        $requests->open($old, ConversationHumanRequest::REASON_CUSTOMER_REQUEST);
        $requests->close($old, ConversationHumanRequest::RESOLUTION_TEAM_REPLY, $owner->id);
        ConversationHumanRequest::where('contact_id', $old->id)->update(['resolved_at' => now()->subDays(31)]);

        $requests->open($recent, ConversationHumanRequest::REASON_KNOWLEDGE_GAP);
        $requests->close($recent, ConversationHumanRequest::RESOLUTION_MANUAL_CLOSE, $owner->id);

        $this->signedIn($owner, $organization)->get('/alerts')
            ->assertInertia(fn (Assert $page) => $page
                ->has('open', 0)
                ->has('closed', 1)
                ->where('closed.0.reason', 'knowledge_gap')
                ->where('closed.0.resolution', 'manual_close')
                ->where('closed.0.resolved_by_name', trim($owner->first_name.' '.$owner->last_name))
                ->where('closed.0.action_url', '/chats/'.$recent->uuid));
    }

    public function test_an_assigned_only_member_gets_the_notifications_item_and_page_without_chat_list_access(): void
    {
        [, $organization, $contact] = $this->conversation();
        $agent = $this->member($organization, ['chats.view_assigned_only']);
        ChatTicket::create(['contact_id' => $contact->id, 'assigned_to' => $agent->id, 'status' => 'open']);
        app(HumanRequestService::class)->open($contact, ConversationHumanRequest::REASON_CUSTOMER_REQUEST);

        // The menu item and the mobile bell show when "available" is true, which follows who can be
        // notified, not the permission behind the "Chats" item.
        $this->signedIn($agent, $organization)->getJson('/human-requests')
            ->assertJsonPath('available', true)
            ->assertJsonPath('count', 1);
        $this->signedIn($agent, $organization)->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('open', 1)->where('open.0.action_url', '/chats/'.$contact->uuid));
    }

    public function test_someone_who_cannot_be_notified_gets_no_item_and_no_page(): void
    {
        [, $organization] = $this->conversation();
        $member = $this->member($organization, ['contacts.view_all']);

        $this->signedIn($member, $organization)->getJson('/human-requests')->assertJsonPath('available', false);
        $this->signedIn($member, $organization)->get('/alerts')->assertNotFound();
    }

    public function test_the_bell_stays_inside_the_organization(): void
    {
        [$owner, $organization] = $this->conversation();
        [, , $otherContact] = $this->conversation();
        $request = app(HumanRequestService::class)->open($otherContact, ConversationHumanRequest::REASON_CUSTOMER_REQUEST)['request'];

        $this->signedIn($owner, $organization)->getJson('/human-requests')->assertJsonPath('count', 0);
        $this->signedIn($owner, $organization)->postJson('/human-requests/'.$request->uuid.'/close')->assertNotFound();
        $this->assertTrue(app(HumanRequestService::class)->hasOpen($otherContact));
    }

    public function test_an_assigned_only_member_sees_and_closes_their_conversations_only(): void
    {
        [, $organization, $mine] = $this->conversation();
        $others = $this->contact($organization);
        $agent = $this->member($organization, ['chats.view_assigned_only']);
        ChatTicket::create(['contact_id' => $mine->id, 'assigned_to' => $agent->id, 'status' => 'open']);
        $requests = app(HumanRequestService::class);
        $mineRequest = $requests->open($mine, ConversationHumanRequest::REASON_CUSTOMER_REQUEST)['request'];
        $otherRequest = $requests->open($others, ConversationHumanRequest::REASON_CUSTOMER_REQUEST)['request'];

        $this->signedIn($agent, $organization)->getJson('/human-requests')->assertJsonPath('count', 1);
        $this->signedIn($agent, $organization)->get('/alerts')
            ->assertInertia(fn (Assert $page) => $page->has('open', 1)->where('open.0.uuid', (string) $mineRequest->uuid));

        $this->signedIn($agent, $organization)->postJson('/human-requests/'.$otherRequest->uuid.'/close')->assertNotFound();
        $this->assertTrue($requests->hasOpen($others));

        $this->signedIn($agent, $organization)->postJson('/human-requests/'.$mineRequest->uuid.'/close')->assertOk()->assertJsonPath('count', 0);
        $this->assertFalse($requests->hasOpen($mine));
    }

    public function test_a_member_who_can_see_all_chats_sees_all_requests_and_others_see_none(): void
    {
        [, $organization, $contact] = $this->conversation();
        app(HumanRequestService::class)->open($contact, ConversationHumanRequest::REASON_CUSTOMER_REQUEST);

        $this->signedIn($this->member($organization, ['chats.view_all']), $organization)->getJson('/human-requests')->assertJsonPath('count', 1);
        $this->signedIn($this->member($organization, ['contacts.view_all']), $organization)->getJson('/human-requests')
            ->assertJsonPath('available', false)
            ->assertJsonPath('count', 0);
    }

    public function test_guests_get_nothing(): void
    {
        $this->getJson('/human-requests')->assertUnauthorized();
        $this->get('/alerts')->assertRedirect();
    }

    public function test_a_visitor_gets_one_message_and_one_request_while_the_assistant_is_off(): void
    {
        config(['intellireply.assistant_off.text' => 'وصلتنا رسالتك، وفريقنا هيرد عليك هنا.']);
        [, $organization, $contact] = $this->conversation(['channel' => Contact::CHANNEL_WEB], ['ai' => ['active' => false]]);
        $this->enableAssistantAddon($organization);
        $widget = WebWidget::create(['organization_id' => $organization->id, 'public_key' => WebWidget::generatePublicKey(), 'is_enabled' => true, 'allowed_domains' => ['x.test'], 'settings' => []]);
        $replies = app(WebWidgetReplyService::class);

        // Not counted as answered: the visitor is still waiting for the team.
        $this->assertFalse($replies->reply($widget, $this->inbound($organization, $contact, 'بكام الخدمة؟')));
        $this->assertFalse($replies->reply($widget, $this->inbound($organization, $contact, 'في حد هنا؟ مساء الخير')));

        $request = ConversationHumanRequest::where('contact_id', $contact->id)->sole();
        $this->assertSame('assistant_off', $request->reason);
        $this->assertFalse($request->blocks_assistant);
        $this->assertSame(1, Chat::where('contact_id', $contact->id)->where('type', 'outbound')->count());
    }

    public function test_a_visitor_who_asks_for_a_person_while_off_blocks_the_assistant_later(): void
    {
        config(['intellireply.handoff.confirmation_text' => 'حوّلناك لفريقنا.']);
        [, $organization, $contact] = $this->conversation(['channel' => Contact::CHANNEL_WEB], ['ai' => ['active' => false]]);
        $this->enableAssistantAddon($organization);
        $widget = WebWidget::create(['organization_id' => $organization->id, 'public_key' => WebWidget::generatePublicKey(), 'is_enabled' => true, 'allowed_domains' => ['x.test'], 'settings' => []]);
        $replies = app(WebWidgetReplyService::class);

        $replies->reply($widget, $this->inbound($organization, $contact, 'مساء الخير'));
        $this->assertFalse(app(HumanRequestService::class)->blocksAssistant($contact));

        $replies->reply($widget, $this->inbound($organization, $contact, 'عايز اكلم موظف'));
        $this->assertTrue(app(HumanRequestService::class)->blocksAssistant($contact));
        $this->assertSame(1, ConversationHumanRequest::where('contact_id', $contact->id)->count());
    }

    public function test_voice_calls_stop_with_the_assistant(): void
    {
        [, $organization] = $this->conversation([], ['ai' => ['active' => false]]);
        $widget = WebWidget::create(['organization_id' => $organization->id, 'public_key' => WebWidget::generatePublicKey(), 'is_enabled' => true, 'allowed_domains' => ['x.test'], 'settings' => ['voice_enabled' => true]]);

        $availability = app(WebWidgetVoiceService::class)->availability($widget);

        $this->assertFalse($availability['available']);
        $this->assertSame(WebWidgetVoiceService::UNAVAILABLE_OFF, $availability['reason']);
    }

    /** @return array{0: User, 1: Organization, 2: Contact} */
    private function conversation(array $contact = [], array $metadata = ['ai' => ['active' => true]]): array
    {
        [$owner, $organization] = $this->createOwnerContext($metadata, true);

        return [$owner, $organization, $this->contact($organization, $contact)];
    }

    private function contact(Organization $organization, array $attributes = []): Contact
    {
        return Contact::create($attributes + [
            'first_name' => 'Customer',
            'phone' => '+2010'.random_int(10000000, 99999999),
            'organization_id' => $organization->id,
            'created_by' => 0,
        ]);
    }

    private function inbound(Organization $organization, Contact $contact, string $body): Chat
    {
        return Chat::create([
            'organization_id' => $organization->id,
            'wam_id' => Str::random(16),
            'contact_id' => $contact->id,
            'type' => 'inbound',
            'metadata' => json_encode(['type' => 'text', 'text' => ['body' => $body]], JSON_UNESCAPED_UNICODE),
            'status' => 'delivered',
            'created_at' => now(),
        ]);
    }

    private function member(Organization $organization, array $permissions): User
    {
        $user = User::create(['first_name' => 'Team', 'last_name' => 'Member', 'email' => 'member+'.Str::random(8).'@example.com', 'password' => 'password123', 'role' => 'user', 'email_verified_at' => now()]);
        $role = OrganizationRole::create(['organization_id' => $organization->id, 'name' => 'Role '.Str::random(6), 'permissions' => $permissions]);
        Team::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'organization_role_id' => $role->id, 'status' => 'active', 'created_by' => $user->id]);

        return $user;
    }

    private function signedIn(User $user, Organization $organization): self
    {
        return $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id]);
    }

    private function sallaStore(Organization $organization): SallaStore
    {
        return SallaStore::create(['merchant_id' => random_int(1000000, 99999999), 'organization_id' => $organization->id, 'store_name' => 'متجر', 'status' => SallaStore::STATUS_INSTALLED, 'installed_at' => now()->subDay()]);
    }

    private function enableAssistantAddon(Organization $organization): void
    {
        $addons = Mockery::mock(\App\Services\AddonStateService::class)->makePartial();
        $addons->shouldReceive('isModuleEnabledForOrganization')->andReturn(true);
        $this->app->instance(\App\Services\AddonStateService::class, $addons);
    }

    private function bindHandoff(WhatsappService $whatsapp): void
    {
        $handoff = Mockery::mock(AiHumanHandoffService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $handoff->shouldReceive('assistantModuleEnabled')->andReturn(true);
        $handoff->shouldReceive('whatsapp')->andReturn($whatsapp);
        $this->app->instance(AiHumanHandoffService::class, $handoff);
    }

    private function expectAutoReplies(int $times): void
    {
        $autoReplies = Mockery::mock(AutoReplyService::class);
        $autoReplies->shouldReceive('checkAutoReply')->times($times);
        $this->app->instance(AutoReplyService::class, $autoReplies);
    }
}
