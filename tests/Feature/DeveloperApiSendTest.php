<?php

namespace Tests\Feature;

use App\Events\NewChatEvent;
use App\Models\Chat;
use App\Models\Contact;
use App\Models\OrganizationApiKey;
use App\Services\OrganizationApiTokenHasher;
use App\Services\WhatsappService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class DeveloperApiSendTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    private const PHONE = '+966501234111';

    /** @var array<int, array> Requests that reached the fake Meta API. */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    public function test_an_accepted_message_returns_its_id_and_is_saved_as_sent(): void
    {
        [$organization, $token] = $this->connectedOrganization();
        $this->fakeMeta([new Response(200, [], json_encode(['messages' => [['id' => 'wamid.OK1']]]))]);

        $response = $this->send($token, '/api/send', ['phone' => self::PHONE, 'message' => 'أهلًا'])
            ->assertOk()
            ->assertJson(['statusCode' => 200, 'message_id' => 'wamid.OK1', 'status' => 'sent']);

        $this->assertStringContainsString('meta;dur=', (string) $response->headers->get('Server-Timing'));
        $this->assertSame('sent', Chat::where('organization_id', $organization->id)->where('wam_id', 'wamid.OK1')->value('status'));
    }

    public function test_the_live_inbox_update_still_goes_out_but_after_the_api_has_answered(): void
    {
        [, $token] = $this->connectedOrganization();
        $this->fakeMeta([new Response(200, [], json_encode(['messages' => [['id' => 'wamid.LIVE']]]))]);
        Event::fake([NewChatEvent::class]);

        $this->send($token, '/api/send', ['phone' => self::PHONE, 'message' => 'Hi'])->assertOk();

        Event::assertDispatchedTimes(NewChatEvent::class, 1);
        $this->assertFalse(WhatsappService::$deferBroadcasts, 'Only developer API requests defer the update.');
    }

    public function test_a_message_meta_refuses_returns_a_clear_error_instead_of_success(): void
    {
        [$organization, $token] = $this->connectedOrganization();
        $this->fakeMeta([new Response(400, [], json_encode(['error' => [
            'message' => '(#131047) Re-engagement message',
            'type' => 'OAuthException',
            'code' => 131047,
            'error_data' => ['details' => 'Message failed to send because more than 24 hours have passed since the customer last replied to this number.'],
        ]]))]);

        $this->send($token, '/api/send', ['phone' => self::PHONE, 'message' => 'Hello'])
            ->assertStatus(422)
            ->assertJson(['statusCode' => 422, 'code' => 'outside_24h_window', 'meta_error' => ['code' => 131047]])
            ->assertJsonStructure(['request_id', 'message', 'meta_error' => ['details'], 'data']);

        $this->assertSame('failed', Chat::where('organization_id', $organization->id)->latest('id')->value('status'));
    }

    public function test_meta_being_unreachable_is_reported_as_a_retryable_error(): void
    {
        [, $token] = $this->connectedOrganization();
        $this->fakeMeta([new ConnectException('Connection timed out', new PsrRequest('POST', 'https://graph.facebook.com'))]);

        $this->send($token, '/api/send', ['phone' => self::PHONE, 'message' => 'Hello'])
            ->assertStatus(502)
            ->assertJson(['code' => 'meta_unreachable']);
    }

    public function test_media_links_must_be_public_https_and_are_never_fetched_by_the_server(): void
    {
        [, $token] = $this->connectedOrganization();
        $this->fakeMeta([new Response(200, [], json_encode(['messages' => [['id' => 'wamid.IMG1']]]))]);

        foreach (['/etc/passwd', '../../.env', 'http://example.com/a.jpg', 'https://localhost/a.jpg', 'https://127.0.0.1/a.jpg', 'https://10.0.0.5/a.jpg', 'https://[::1]/a.jpg', 'https://api.internal/a.jpg'] as $url) {
            $this->send($token, '/api/send/media', ['phone' => self::PHONE, 'media_type' => 'image', 'media_url' => $url])
                ->assertStatus(400)
                ->assertJsonPath('code', 'validation_error');
        }
        $this->assertCount(0, $this->sent);

        $this->send($token, '/api/send/media', ['phone' => self::PHONE, 'media_type' => 'image', 'media_url' => 'https://cdn.example.com/files/house.jpg', 'caption' => 'شقة للإيجار'])
            ->assertOk()
            ->assertJsonPath('message_id', 'wamid.IMG1');

        // Only Meta was called, with the caption, and the link was passed on as it is.
        Http::assertNothingSent();
        $this->assertCount(1, $this->sent);
        $this->assertSame('https://cdn.example.com/files/house.jpg', $this->sent[0]['image']['link']);
        $this->assertSame('شقة للإيجار', $this->sent[0]['image']['caption']);
    }

    public function test_retrying_with_the_same_idempotency_key_sends_only_once(): void
    {
        [$organization, $token] = $this->connectedOrganization();
        $this->fakeMeta([new Response(200, [], json_encode(['messages' => [['id' => 'wamid.ONCE']]]))]);
        $body = ['phone' => self::PHONE, 'message' => 'طلبك اتشحن'];

        $this->send($token, '/api/send', $body, ['Idempotency-Key' => 'order-55'])->assertOk()->assertJsonPath('message_id', 'wamid.ONCE');
        $this->send($token, '/api/send', $body, ['Idempotency-Key' => 'order-55'])
            ->assertOk()
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('message_id', 'wamid.ONCE');

        $this->assertCount(1, $this->sent);
        $this->assertSame(1, Chat::where('organization_id', $organization->id)->count());

        $this->send($token, '/api/send', ['phone' => self::PHONE, 'message' => 'Something else'], ['Idempotency-Key' => 'order-55'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'idempotency_key_reused');
    }

    public function test_the_status_of_a_sent_message_can_be_checked_only_by_its_organization(): void
    {
        [$organization, $token] = $this->connectedOrganization();
        $this->fakeMeta([new Response(200, [], json_encode(['messages' => [['id' => 'wamid.STATUS']]]))]);
        $this->send($token, '/api/send', ['phone' => self::PHONE, 'message' => 'Hello'])->assertOk();

        Chat::where('wam_id', 'wamid.STATUS')->update(['status' => 'read']);

        $this->getJson('/api/messages/wamid.STATUS', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('data.status', 'read')
            ->assertJsonPath('data.phone', self::PHONE)
            ->assertJsonPath('data.error', null);

        [, $otherToken] = $this->connectedOrganization();
        $this->getJson('/api/messages/wamid.STATUS', ['Authorization' => 'Bearer '.$otherToken])
            ->assertNotFound()
            ->assertJsonPath('code', 'message_not_found');
    }

    public function test_key_scopes_limit_what_a_key_can_do(): void
    {
        [$organization] = $this->connectedOrganization();
        $sendKey = $this->createApiToken($organization->id, OrganizationApiKey::SCOPE_SEND);
        $readKey = $this->createApiToken($organization->id, OrganizationApiKey::SCOPE_READ);
        $this->fakeMeta([new Response(200, [], json_encode(['messages' => [['id' => 'wamid.SCOPE']]]))]);

        $this->getJson('/api/contacts', ['Authorization' => 'Bearer '.$sendKey])->assertForbidden()->assertJsonPath('code', 'api_key_scope_denied');
        $this->send($sendKey, '/api/send', ['phone' => self::PHONE, 'message' => 'Hi'])->assertOk();

        $this->getJson('/api/contacts', ['Authorization' => 'Bearer '.$readKey])->assertOk();
        $this->send($readKey, '/api/send', ['phone' => self::PHONE, 'message' => 'Hi'])->assertForbidden()->assertJsonPath('code', 'api_key_scope_denied');
        $this->deleteJson('/api/contacts/any-uuid', [], ['Authorization' => 'Bearer '.$readKey])->assertForbidden();
    }

    public function test_the_stored_hash_of_a_key_does_not_work_as_a_key(): void
    {
        [$organization, $token] = $this->connectedOrganization();
        $storedHash = OrganizationApiKey::where('organization_id', $organization->id)->value('token');

        $this->getJson('/api/verify', ['Authorization' => 'Bearer '.$token])->assertOk();
        $this->getJson('/api/verify', ['Authorization' => 'Bearer '.$storedHash])->assertUnauthorized();
    }

    public function test_a_campaign_without_schedule_fields_starts_right_away(): void
    {
        [, $token] = $this->connectedOrganization();

        // Passing validation without "time" or "skip_schedule" reaches the template lookup.
        $this->send($token, '/api/campaigns', ['template' => 'missing-template', 'contacts' => 'all'])
            ->assertNotFound()
            ->assertJsonPath('code', 'template_not_found');
    }

    public function test_keys_are_created_with_a_name_and_permissions_from_the_dashboard(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);

        $this->withoutMiddleware(\App\Http\Middleware\CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/developer-tools/access-tokens', ['name' => 'تطبيق المتجر', 'scope' => 'send'])
            ->assertRedirect()
            ->assertSessionHas('generated_api_token');

        $key = OrganizationApiKey::where('organization_id', $organization->id)->sole();
        $this->assertSame('تطبيق المتجر', $key->name);
        $this->assertSame('send', $key->scope);
    }

    private function connectedOrganization(): array
    {
        [, $organization] = $this->createOwnerContext([
            'whatsapp' => [
                'app_id' => 'app-123',
                'phone_number_id' => 'phone-123',
                'waba_id' => 'waba-123',
                'access_token' => 'meta-test-token',
            ],
        ], false);
        $this->createActiveSubscription($organization->id, ['message_limit' => 1000, 'contacts_limit' => 1000, 'campaign_limit' => 10]);
        Contact::create([
            'organization_id' => $organization->id,
            'first_name' => 'Sara',
            'phone' => self::PHONE,
            'created_by' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$organization, $this->createApiToken($organization->id)];
    }

    private function fakeMeta(array $responses): void
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::mapRequest(function ($request) {
            $this->sent[] = json_decode((string) $request->getBody(), true);

            return $request;
        }));
        $this->app->instance(Client::class, new Client(['handler' => $stack]));
    }

    private function send(string $token, string $uri, array $body, array $headers = [])
    {
        return $this->postJson($uri, $body, ['Authorization' => 'Bearer '.$token] + $headers);
    }

    private function createApiToken(int $organizationId, string $scope = OrganizationApiKey::SCOPE_FULL): string
    {
        $plainToken = 'token-'.bin2hex(random_bytes(12));
        OrganizationApiKey::create([
            'organization_id' => $organizationId,
            'token' => app(OrganizationApiTokenHasher::class)->hashToken($plainToken),
            'token_last_four' => substr($plainToken, -4),
            'scope' => $scope,
        ]);

        return $plainToken;
    }
}
