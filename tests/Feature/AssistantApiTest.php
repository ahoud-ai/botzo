<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Contact;
use App\Models\OrganizationApiKey;
use App\Models\WebhookEndpoint;
use App\Models\WebWidget;
use App\Services\AddonStateService;
use App\Services\OrganizationApiService;
use App\Services\Webhooks\WebhookEndpointService;
use App\Services\WebWidget\WebWidgetMessageStore;
use App\Support\PublicHost;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class AssistantApiTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    private const REPLY = 'أهلاً! باقة النمو بـ 599 ريال في الشهر.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(AddonStateService::class)->shouldReceive('isModuleEnabledForOrganization')->andReturn(true);
        config(['logging.channels.intellireply' => ['driver' => 'null']]);
        config(['intellireply.handoff.confirmation_text' => 'هحوّلك لحد من الفريق.']);
        PublicHost::$resolver = fn (string $host) => ['93.184.216.34'];
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => self::REPLY]]]]),
            'hooks.example.com/*' => Http::response(['ok' => true]),
            '*' => Http::response([], 500),
        ]);
    }

    protected function tearDown(): void
    {
        PublicHost::$resolver = null;
        parent::tearDown();
    }

    public function test_a_server_opens_a_conversation_per_user_and_gets_mochis_reply_in_the_same_request(): void
    {
        [$organization, $key] = $this->organizationWithKey(OrganizationApiKey::SCOPE_ASSISTANT, 'تطبيق المتجر');

        $opened = $this->api($key)->postJson('/api/v1/assistant/conversations', ['user' => ['id' => 'customer-42', 'name' => 'سارة']])
            ->assertCreated()
            ->assertJsonStructure(['conversation_id', 'messages'])
            ->assertJsonMissingPath('conversation_token');
        $conversation = $opened->json('conversation_id');

        $this->api($key)->postJson("/api/v1/assistant/conversations/{$conversation}/messages", ['text' => 'كم سعر باقة النمو؟'])
            ->assertOk()
            ->assertJsonPath('awaiting_team', false)
            ->assertJsonPath('replies.0.from', 'assistant')
            ->assertJsonPath('replies.0.text', self::REPLY);

        // The same user from another device continues the same conversation, with its history.
        $this->api($key)->postJson('/api/v1/assistant/conversations', ['user' => ['id' => 'customer-42']])
            ->assertOk()
            ->assertJsonPath('conversation_id', $conversation)
            ->assertJsonPath('messages.0.from', 'user')
            ->assertJsonPath('messages.1.text', self::REPLY);

        // The inbox shows the app's name and the user's name.
        $contact = Contact::where('organization_id', $organization->id)->where('first_name', 'سارة')->sole();
        $this->assertSame(Contact::CHANNEL_WEB, $contact->channel);
        $this->assertSame('تطبيق المتجر', json_decode($contact->metadata, true)['source_app']);
    }

    public function test_without_waiting_the_reply_comes_later_and_can_be_fetched(): void
    {
        [, $key] = $this->organizationWithKey(OrganizationApiKey::SCOPE_ASSISTANT);
        $conversation = $this->api($key)->postJson('/api/v1/assistant/conversations', ['user' => ['id' => 'u-1']])->json('conversation_id');

        $sent = $this->api($key)->postJson("/api/v1/assistant/conversations/{$conversation}/messages", ['text' => 'مرحبا', 'wait' => false])
            ->assertStatus(202)
            ->assertJsonPath('replies', []);

        // The reply runs after the response (the test client ends the request); then it is there.
        $this->api($key)->getJson("/api/v1/assistant/conversations/{$conversation}/messages?after=".$sent->json('message_id'))
            ->assertOk()
            ->assertJsonPath('messages.0.from', 'assistant')
            ->assertJsonPath('messages.0.text', self::REPLY);
    }

    public function test_a_public_key_gets_one_conversation_per_device_and_nothing_else(): void
    {
        [$organization, $key] = $this->organizationWithKey(OrganizationApiKey::SCOPE_PUBLIC, 'تطبيق iOS');
        $this->assertStringStartsWith('pk_', $key);

        $first = $this->api($key)->postJson('/api/v1/assistant/conversations', [])->assertCreated();
        $token = $first->json('conversation_token');
        $this->assertSame(64, strlen((string) $token));
        $conversation = $first->json('conversation_id');

        // Without the device's token the conversation cannot be read or written.
        $this->api($key)->getJson("/api/v1/assistant/conversations/{$conversation}/messages")->assertNotFound();
        $this->api($key, ['X-Conversation-Token' => 'wrong'])->postJson("/api/v1/assistant/conversations/{$conversation}/messages", ['text' => 'hi'])->assertNotFound();
        $this->api($key, ['X-Conversation-Token' => $token])->postJson("/api/v1/assistant/conversations/{$conversation}/messages", ['text' => 'hi'])
            ->assertOk()
            ->assertJsonPath('replies.0.text', self::REPLY);

        // A second device gets its own conversation; the first one cannot be reached with its token.
        $second = $this->api($key)->postJson('/api/v1/assistant/conversations', [])->assertCreated();
        $this->assertNotSame($conversation, $second->json('conversation_id'));
        $this->api($key, ['X-Conversation-Token' => $second->json('conversation_token')])->getJson("/api/v1/assistant/conversations/{$conversation}/messages")->assertNotFound();

        // Resuming with the token returns the same conversation.
        $this->api($key, ['X-Conversation-Token' => $token])->postJson('/api/v1/assistant/conversations', [])
            ->assertOk()
            ->assertJsonPath('conversation_id', $conversation);

        // A public key reaches nothing outside Mochi.
        $this->api($key)->getJson('/api/contacts')->assertForbidden()->assertJsonPath('code', 'api_key_scope_denied');
        $this->api($key)->postJson('/api/send', ['phone' => '+966500000001', 'message' => 'x'])->assertForbidden();
        $this->api($key)->getJson('/api/verify')->assertForbidden();
    }

    public function test_public_keys_are_limited_per_device(): void
    {
        config(['web_widget.limits.sessions_per_ip_per_hour' => 2, 'web_widget.limits.message_min_interval_seconds' => 3]);
        [, $key] = $this->organizationWithKey(OrganizationApiKey::SCOPE_PUBLIC);

        $this->api($key)->postJson('/api/v1/assistant/conversations', [])->assertCreated();
        $opened = $this->api($key)->postJson('/api/v1/assistant/conversations', [])->assertCreated();
        $this->api($key)->postJson('/api/v1/assistant/conversations', [])->assertStatus(429)->assertJsonPath('code', 'too_many_conversations');

        $headers = ['X-Conversation-Token' => $opened->json('conversation_token')];
        $url = '/api/v1/assistant/conversations/'.$opened->json('conversation_id').'/messages';
        $this->api($key, $headers)->postJson($url, ['text' => 'one'])->assertOk();
        $this->api($key, $headers)->postJson($url, ['text' => 'two'])->assertStatus(429)->assertJsonPath('code', 'too_many_messages');
    }

    public function test_keys_and_conversations_stay_inside_their_organization(): void
    {
        [, $key] = $this->organizationWithKey(OrganizationApiKey::SCOPE_ASSISTANT);
        $conversation = $this->api($key)->postJson('/api/v1/assistant/conversations', ['user' => ['id' => 'same-id']])->json('conversation_id');

        [, $otherKey] = $this->organizationWithKey(OrganizationApiKey::SCOPE_FULL);
        $this->api($otherKey)->getJson("/api/v1/assistant/conversations/{$conversation}/messages")->assertNotFound();
        $other = $this->api($otherKey)->postJson('/api/v1/assistant/conversations', ['user' => ['id' => 'same-id']])->assertCreated();
        $this->assertNotSame($conversation, $other->json('conversation_id'));

        // A send-only key cannot use Mochi.
        [, $sendKey] = $this->organizationWithKey(OrganizationApiKey::SCOPE_SEND);
        $this->api($sendKey)->postJson('/api/v1/assistant/conversations', ['user' => ['id' => 'x']])->assertForbidden();
    }

    public function test_the_assistant_must_be_on_and_a_secret_key_must_name_the_user(): void
    {
        [, $key] = $this->organizationWithKey(OrganizationApiKey::SCOPE_ASSISTANT, null, aiActive: false);
        $this->api($key)->postJson('/api/v1/assistant/conversations', ['user' => ['id' => 'u']])->assertForbidden()->assertJsonPath('code', 'assistant_not_active');

        [, $activeKey] = $this->organizationWithKey(OrganizationApiKey::SCOPE_ASSISTANT);
        $this->api($activeKey)->postJson('/api/v1/assistant/conversations', [])->assertStatus(400)->assertJsonPath('code', 'validation_error');
    }

    public function test_replies_from_mochi_and_the_team_reach_the_apps_webhook(): void
    {
        [$organization, $key] = $this->organizationWithKey(OrganizationApiKey::SCOPE_ASSISTANT);
        app(WebhookEndpointService::class)->create($organization->id, 'https://hooks.example.com/botzo', [WebhookEndpoint::EVENT_CONVERSATION_REPLY], null);
        $conversation = $this->api($key)->postJson('/api/v1/assistant/conversations', ['user' => ['id' => 'customer-7']])->json('conversation_id');

        $this->api($key)->postJson("/api/v1/assistant/conversations/{$conversation}/messages", ['text' => 'سؤال'])->assertOk();
        $contact = Contact::where('organization_id', $organization->id)->latest('id')->first();
        app(WebWidgetMessageStore::class)->storeText($contact, 'معاك سالم من الفريق', 'outbound', 99);

        $events = collect(Http::recorded())
            ->map(fn ($pair) => $pair[0])
            ->filter(fn (ClientRequest $request) => str_contains($request->url(), 'hooks.example.com'))
            ->map(fn (ClientRequest $request) => json_decode($request->body(), true)['data'])
            ->values();

        $this->assertCount(2, $events);
        $this->assertSame(['assistant', 'agent'], $events->pluck('from')->all());
        $this->assertSame([$conversation, $conversation], $events->pluck('conversation_id')->all());
        $this->assertSame('customer-7', $events[0]['user_id']);
        $this->assertSame('api', $events[0]['source']);
    }

    private function organizationWithKey(string $scope, ?string $name = null, bool $aiActive = true): array
    {
        [, $organization] = $this->createOwnerContext(['ai' => [
            'active' => $aiActive,
            'model' => 'gpt-4o-mini',
            'key_source' => 'organization',
            'api_key_encrypted' => Crypt::encryptString('sk-test'),
        ]], true);

        return [$organization, app(OrganizationApiService::class)->generate($organization->id, $name, $scope)['token']];
    }

    private function api(string $key, array $headers = []): self
    {
        return $this->flushHeaders()->withHeaders(['Authorization' => 'Bearer '.$key] + $headers);
    }
}
