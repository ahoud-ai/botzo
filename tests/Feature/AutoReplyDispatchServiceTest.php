<?php

namespace Tests\Feature;

use App\Events\NewChatEvent;
use App\Jobs\RunAutoReplyJob;
use App\Models\Chat;
use App\Models\Contact;
use App\Models\Organization;
use App\Services\AutoReplyDispatchService;
use App\Services\AutoReplyService;
use App\Services\AutomationFlows\AutomationFlowRuntimeService;
use App\Services\Whatsapp\WhatsappTokenVault;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Mockery;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class AutoReplyDispatchServiceTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([NewChatEvent::class]);
        Sleep::fake();
        config(['auto_replies.debounce_seconds' => 4]);
    }

    public function test_an_older_message_does_not_reply_once_a_newer_one_arrived(): void
    {
        [$organization, $contact] = $this->contactContext();
        $first = $this->inboundChat($organization, $contact, 'wamid.B1', 'text');
        $second = $this->inboundChat($organization, $contact, 'wamid.B2', 'text');

        $replies = $this->expectAutoReplies(1);
        $dispatcher = app(AutoReplyDispatchService::class);

        // Both messages were registered (second one last) before either woke up from the debounce wait.
        Cache::put(sprintf('auto-reply:latest:%d:%d', $organization->id, $contact->id), $second->id, 60);
        $dispatcher->run($first);
        $dispatcher->run($second);

        $this->assertSame([$second->id], $replies->chatIds);
    }

    public function test_a_burst_in_one_delivery_gets_a_single_reply_from_the_latest_message(): void
    {
        [$organization] = $this->contactContext();
        $replies = $this->expectAutoReplies(1);

        $this->postMessages($organization, [
            $this->textMessage('wamid.D1', 'السلام عليكم', 12),
            $this->textMessage('wamid.D2', 'عندي سؤال', 11),
            $this->textMessage('wamid.D3', 'بكام الخدمة؟', 10),
        ])->assertOk();

        $this->assertSame([Chat::where('wam_id', 'wamid.D3')->value('id')], $replies->chatIds);
        Sleep::assertSleptTimes(1);
    }

    public function test_button_taps_are_answered_without_waiting(): void
    {
        [$organization, $contact] = $this->contactContext();
        $this->expectAutoReplies(1);

        app(AutoReplyDispatchService::class)->dispatch($this->inboundChat($organization, $contact, 'wamid.BTN', 'interactive'), false);

        Sleep::assertNeverSlept();
    }

    public function test_a_second_responder_for_the_same_contact_waits_for_the_lock_and_gives_up(): void
    {
        config(['auto_replies.lock_wait_seconds' => 1]);
        [$organization, $contact] = $this->contactContext();
        $chat = $this->inboundChat($organization, $contact, 'wamid.LOCK', 'text');
        $this->expectAutoReplies(0);

        $held = Cache::lock(sprintf('auto-reply:%d:%d', $organization->id, $contact->id), 30);
        $this->assertTrue($held->get());

        app(AutoReplyDispatchService::class)->run($chat);

        $held->release();
    }

    public function test_queue_mode_defers_the_reply_to_a_delayed_job(): void
    {
        config(['graph.webhook_processing' => 'queue']);
        Queue::fake();
        [$organization, $contact] = $this->contactContext();

        app(AutoReplyDispatchService::class)->dispatch($this->inboundChat($organization, $contact, 'wamid.Q1', 'text'), false);

        Queue::assertPushed(RunAutoReplyJob::class, fn ($job) => $job->delay !== null);
        Sleep::assertNeverSlept();
    }

    public function test_the_new_contact_flag_survives_a_burst(): void
    {
        [$organization, $contact] = $this->contactContext();
        $first = $this->inboundChat($organization, $contact, 'wamid.N1', 'text');
        $second = $this->inboundChat($organization, $contact, 'wamid.N2', 'text');

        $autoReplies = Mockery::mock(AutoReplyService::class);
        $autoReplies->shouldReceive('checkAutoReply')->once()->with(Mockery::on(fn ($chat) => $chat->id === $second->id), true);
        $this->app->instance(AutoReplyService::class, $autoReplies);
        config(['auto_replies.debounce_seconds' => 0]);

        $dispatcher = app(AutoReplyDispatchService::class);
        Cache::put(sprintf('auto-reply:new-contact:%d:%d', $organization->id, $contact->id), true, 60);
        Cache::put(sprintf('auto-reply:latest:%d:%d', $organization->id, $contact->id), $second->id, 60);
        $dispatcher->run($first);
        $dispatcher->run($second);
    }

    public function test_a_first_burst_still_counts_as_the_first_message_in_the_conversation(): void
    {
        [$organization, $contact] = $this->contactContext();
        $this->inboundChat($organization, $contact, 'wamid.F1', 'text');
        $latest = $this->inboundChat($organization, $contact, 'wamid.F2', 'text');

        $this->assertTrue($this->isFirstInboundMessage($latest));

        Chat::where('wam_id', 'wamid.F1')->update(['created_at' => now()->subHour()]);
        $this->assertFalse($this->isFirstInboundMessage($latest->fresh()));
    }

    private function isFirstInboundMessage(Chat $chat): bool
    {
        $method = new \ReflectionMethod(AutomationFlowRuntimeService::class, 'isFirstInboundMessage');

        return $method->invoke(app(AutomationFlowRuntimeService::class), $chat);
    }

    private function expectAutoReplies(int $times): object
    {
        $recorder = new class { public array $chatIds = []; };
        $autoReplies = Mockery::mock(AutoReplyService::class);
        $autoReplies->shouldReceive('checkAutoReply')->times($times)->andReturnUsing(function ($chat) use ($recorder) {
            $recorder->chatIds[] = $chat->id;
        });
        $this->app->instance(AutoReplyService::class, $autoReplies);

        return $recorder;
    }

    private function contactContext(): array
    {
        [, $organization] = $this->createOwnerContext([
            'whatsapp' => [
                'is_embedded_signup' => 0,
                'waba_id' => 'waba-dispatch',
                'phone_number_id' => 'phone-dispatch',
                'app_secret_encrypted' => (new WhatsappTokenVault())->encryptAppSecret('dispatch-secret'),
            ],
        ], true);

        $contact = Contact::create([
            'first_name' => 'Customer',
            'phone' => '+201001234567',
            'organization_id' => $organization->id,
            'created_by' => 0,
        ]);

        return [$organization, $contact];
    }

    private function inboundChat(Organization $organization, Contact $contact, string $wamId, string $type): Chat
    {
        return Chat::create([
            'organization_id' => $organization->id,
            'wam_id' => $wamId,
            'contact_id' => $contact->id,
            'type' => 'inbound',
            'metadata' => json_encode(['type' => $type, 'text' => ['body' => 'hi']]),
            'status' => 'delivered',
            'created_at' => now(),
        ]);
    }

    private function postMessages(Organization $organization, array $messages)
    {
        $payload = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'waba-dispatch',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => 'phone-dispatch'],
                        'contacts' => [['wa_id' => '201001234567', 'profile' => ['name' => 'Customer']]],
                        'messages' => $messages,
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return $this->call('POST', '/webhook/whatsapp/'.$organization->identifier, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, 'dispatch-secret'),
        ], $payload);
    }

    private function textMessage(string $wamId, string $body, int $secondsAgo): array
    {
        return [
            'from' => '201001234567',
            'id' => $wamId,
            'timestamp' => (string) now()->subSeconds($secondsAgo)->timestamp,
            'type' => 'text',
            'text' => ['body' => $body],
        ];
    }
}
