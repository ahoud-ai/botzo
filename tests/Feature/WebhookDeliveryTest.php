<?php

namespace Tests\Feature;

use App\Events\NewChatEvent;
use App\Jobs\ProcessWebhookJob;
use App\Models\Chat;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookInspectorRequest;
use App\Services\AutomationFlows\AutomationFlowConversationHandoffService;
use App\Services\Webhooks\WebhookDispatcher;
use App\Services\Webhooks\WebhookEndpointService;
use App\Services\Webhooks\WebhookInspector;
use App\Services\Whatsapp\WhatsappTokenVault;
use App\Support\PublicHost;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class WebhookDeliveryTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    private const HOOK_URL = 'https://hooks.example.com/botzo';

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([NewChatEvent::class]);
        PublicHost::$resolver = fn (string $host) => ['93.184.216.34'];
    }

    protected function tearDown(): void
    {
        PublicHost::$resolver = null;
        parent::tearDown();
    }

    public function test_an_incoming_whatsapp_message_reaches_the_customer_server_signed(): void
    {
        Http::fake([self::HOOK_URL => Http::response(['ok' => true])]);
        [$organization, $secret] = $this->organizationWithWebhook();

        (new ProcessWebhookJob($this->inboundPayload('wamid.IN1', 'وش أسعاركم؟'), $organization->id))->handle();

        Http::assertSent(function (ClientRequest $request) use ($secret) {
            $payload = json_decode($request->body(), true);
            $expected = 'sha256='.hash_hmac('sha256', $request->header('X-Botzo-Timestamp')[0].'.'.$request->body(), $secret);

            return $request->url() === self::HOOK_URL
                && $request->header('X-Botzo-Event')[0] === 'message.received'
                && $request->header('X-Botzo-Signature')[0] === $expected
                && $payload['type'] === 'message.received'
                && $payload['data']['message_id'] === 'wamid.IN1'
                && $payload['data']['text'] === 'وش أسعاركم؟'
                && $payload['data']['from'] === '+966501234567'
                && $payload['data']['contact']['name'] === 'Sara';
        });
        $this->assertSame(WebhookDelivery::STATUS_SUCCEEDED, WebhookDelivery::where('organization_id', $organization->id)->value('status'));
    }

    public function test_status_changes_of_sent_messages_are_reported(): void
    {
        Http::fake([self::HOOK_URL => Http::response('', 204)]);
        [$organization] = $this->organizationWithWebhook();
        $contact = Contact::create(['organization_id' => $organization->id, 'first_name' => 'Ali', 'phone' => '+966500000009', 'created_by' => 0]);
        Chat::create(['organization_id' => $organization->id, 'wam_id' => 'wamid.OUT1', 'contact_id' => $contact->id, 'type' => 'outbound', 'metadata' => '{}', 'status' => 'sent', 'created_at' => now()]);

        (new ProcessWebhookJob($this->statusPayload('wamid.OUT1', 'failed', [['code' => 131047, 'title' => 'Re-engagement message', 'message' => 'Re-engagement message']]), $organization->id))->handle();

        Http::assertSent(function (ClientRequest $request) {
            $data = json_decode($request->body(), true)['data'];

            return $data['message_id'] === 'wamid.OUT1' && $data['status'] === 'failed' && $data['error']['code'] === 131047 && $data['to'] === '+966500000009';
        });
    }

    public function test_a_move_to_a_human_is_reported_and_endpoints_only_get_their_events(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);
        [$organization] = $this->organizationWithWebhook([WebhookEndpoint::EVENT_CONVERSATION_HANDOFF]);
        $contact = Contact::create(['organization_id' => $organization->id, 'first_name' => 'Nora', 'phone' => '+966500000010', 'created_by' => 0]);

        app(AutomationFlowConversationHandoffService::class)->assignToAgent($contact, 'auto_assign', null, true, 'human_handoff');
        (new ProcessWebhookJob($this->inboundPayload('wamid.IN2', 'hello'), $organization->id))->handle();

        Http::assertSentCount(1);
        Http::assertSent(fn (ClientRequest $request) => json_decode($request->body(), true)['type'] === 'conversation.handoff'
            && json_decode($request->body(), true)['data']['reason'] === 'human_handoff');
    }

    public function test_failures_are_retried_on_schedule_and_a_dead_url_is_switched_off(): void
    {
        Http::fake([self::HOOK_URL => Http::response('down', 500)]);
        [$organization] = $this->organizationWithWebhook();
        $dispatcher = app(WebhookDispatcher::class);

        $dispatcher->dispatch($organization->id, WebhookEndpoint::EVENT_MESSAGE_RECEIVED, ['text' => 'x']);
        $delivery = WebhookDelivery::where('organization_id', $organization->id)->sole();
        $this->assertSame(WebhookDelivery::STATUS_PENDING, $delivery->status);
        $this->assertSame('http_500', $delivery->error);
        $this->assertEqualsWithDelta(now()->addMinute()->timestamp, $delivery->next_attempt_at->timestamp, 5);

        // Nothing is due yet; then each retry waits longer, and the fifth attempt is the last.
        $this->assertSame(0, $dispatcher->retryDue());
        foreach ([1, 5, 30, 120] as $minutes) {
            $this->travel($minutes + 1)->minutes();
            $this->artisan('webhooks:retry')->assertSuccessful();
        }
        $delivery->refresh();
        $this->assertSame(5, $delivery->attempts);
        $this->assertSame(WebhookDelivery::STATUS_FAILED, $delivery->status);
        $this->assertNull($delivery->next_attempt_at);

        $endpoint = WebhookEndpoint::where('organization_id', $organization->id)->sole();
        $endpoint->update(['consecutive_failures' => WebhookEndpoint::DISABLE_AFTER_FAILURES - 1]);
        $dispatcher->dispatch($organization->id, WebhookEndpoint::EVENT_MESSAGE_RECEIVED, ['text' => 'y']);
        $endpoint->refresh();
        $this->assertFalse($endpoint->is_active);
        $this->assertNotNull($endpoint->disabled_at);
    }

    public function test_urls_that_point_inside_the_hosting_network_are_never_called(): void
    {
        Http::fake();
        PublicHost::$resolver = fn (string $host) => ['10.0.0.7'];
        [$organization] = $this->organizationWithWebhook();

        app(WebhookDispatcher::class)->dispatch($organization->id, WebhookEndpoint::EVENT_MESSAGE_RECEIVED, ['text' => 'x']);

        Http::assertNothingSent();
        $this->assertSame('blocked_address', WebhookDelivery::where('organization_id', $organization->id)->value('error'));
    }

    public function test_the_test_receiver_stores_requests_and_checks_their_signature(): void
    {
        [, $organization] = $this->createOwnerContext([], true);
        $inspector = app(WebhookInspector::class);
        $url = $inspector->url($organization->id);
        [, $secret] = app(WebhookEndpointService::class)->create($organization->id, $url, WebhookEndpoint::EVENTS, null);
        $path = parse_url($url, PHP_URL_PATH);
        $body = json_encode(['id' => 'evt_1', 'type' => 'webhook.test', 'data' => []]);
        $timestamp = (string) time();

        $this->call('POST', $path, [], [], [], $this->signedServer('webhook.test', $timestamp, 'sha256='.WebhookDispatcher::sign($secret, $timestamp, $body)), $body)
            ->assertOk()
            ->assertJson(['received' => true, 'signature_valid' => true]);
        $this->call('POST', $path, [], [], [], $this->signedServer('webhook.test', $timestamp, 'sha256=forged'), $body)
            ->assertJson(['signature_valid' => false]);
        $this->postJson('/api/webhook-inspector/'.$organization->id.'.forgedtoken', [])->assertNotFound();

        for ($i = 0; $i < 25; $i++) {
            $this->call('POST', $path, [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
        }
        $this->assertSame(20, WebhookInspectorRequest::where('organization_id', $organization->id)->count());
    }

    public function test_webhooks_are_managed_from_the_dashboard_and_the_secret_is_shown_once(): void
    {
        Http::fake([self::HOOK_URL => Http::response(['ok' => true])]);
        [$user, $organization] = $this->createOwnerContext([], true);
        $dashboard = $this->withoutMiddleware(\App\Http\Middleware\CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id]);

        $dashboard->post('/developer-tools/event-hooks', ['url' => 'http://insecure.example.com/hook', 'events' => ['message.received']])
            ->assertSessionHasErrors('url');
        $dashboard->post('/developer-tools/event-hooks', ['url' => self::HOOK_URL, 'events' => ['message.received', 'not.an.event']])
            ->assertSessionHasErrors('events.1');

        $dashboard->post('/developer-tools/event-hooks', ['url' => self::HOOK_URL, 'events' => ['message.received', 'message.status']])
            ->assertRedirect()
            ->assertSessionHas('generated_webhook_secret');
        $secret = session('generated_webhook_secret');
        $endpoint = WebhookEndpoint::where('organization_id', $organization->id)->sole();
        $this->assertStringStartsWith('whsec_', $secret);
        $this->assertStringNotContainsString($secret, $endpoint->getRawOriginal('secret_encrypted'));
        $this->assertSame($secret, Crypt::decryptString($endpoint->secret_encrypted));

        $dashboard->post("/developer-tools/event-hooks/{$endpoint->uuid}/test")->assertSessionHas('status.type', 'success');
        Http::assertSent(fn (ClientRequest $request) => $request->header('X-Botzo-Event')[0] === 'webhook.test');

        $dashboard->put("/developer-tools/event-hooks/{$endpoint->uuid}", ['events' => ['message.status'], 'is_active' => false])->assertRedirect();
        $this->assertSame(['message.status'], $endpoint->fresh()->events);
        $this->assertFalse($endpoint->fresh()->is_active);

        // Another organization cannot touch it.
        [$other, $otherOrganization] = $this->createOwnerContext([], true);
        $this->withoutMiddleware(\App\Http\Middleware\CheckSubscriptionStatus::class)
            ->actingAs($other, 'user')
            ->withSession(['current_organization' => $otherOrganization->id])
            ->delete("/developer-tools/event-hooks/{$endpoint->uuid}")
            ->assertNotFound();
    }

    private function organizationWithWebhook(array $events = WebhookEndpoint::EVENTS): array
    {
        [, $organization] = $this->createOwnerContext([
            'whatsapp' => [
                'is_embedded_signup' => 0,
                'app_id' => 'own-app',
                'phone_number_id' => 'own-phone',
                'waba_id' => 'own-waba',
                'app_secret_encrypted' => (new WhatsappTokenVault())->encryptAppSecret('own-secret'),
            ],
        ], true);
        [, $secret] = app(WebhookEndpointService::class)->create($organization->id, self::HOOK_URL, $events, null);

        return [$organization, $secret];
    }

    private function signedServer(string $event, string $timestamp, string $signature): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_BOTZO_EVENT' => $event,
            'HTTP_X_BOTZO_TIMESTAMP' => $timestamp,
            'HTTP_X_BOTZO_SIGNATURE' => $signature,
        ];
    }

    private function inboundPayload(string $wamId, string $text): array
    {
        return ['entry' => [['id' => 'own-waba', 'changes' => [['field' => 'messages', 'value' => [
            'metadata' => ['phone_number_id' => 'own-phone'],
            'contacts' => [['wa_id' => '966501234567', 'profile' => ['name' => 'Sara']]],
            'messages' => [['from' => '966501234567', 'id' => $wamId, 'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => $text]]],
        ]]]]]];
    }

    private function statusPayload(string $wamId, string $status, array $errors = []): array
    {
        return ['entry' => [['id' => 'own-waba', 'changes' => [['field' => 'messages', 'value' => [
            'metadata' => ['phone_number_id' => 'own-phone'],
            'statuses' => [array_filter(['id' => $wamId, 'status' => $status, 'timestamp' => (string) now()->timestamp, 'errors' => $errors ?: null])],
        ]]]]]];
    }
}
