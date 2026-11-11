<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebWidget;
use App\Services\AddonStateService;
use App\Services\WebWidget\SiteVisitorIdentity;
use App\Services\WebWidget\SiteWidget;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

/**
 * Mochi on botzo.net knows which signed-in customer it is talking to, from a token the server
 * signed. Forged, expired or foreign tokens are ignored, and a chat is never shown to another person.
 */
class WebWidgetSiteIdentityTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    private const ORIGIN = 'https://botzo.example.com';

    private WebWidget $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(AddonStateService::class)->shouldReceive('isModuleEnabledForOrganization')->andReturn(true);
        config([
            'logging.channels.intellireply' => ['driver' => 'null'],
            // Several messages in a row from one chat within a test.
            'web_widget.limits.message_min_interval_seconds' => 0,
        ]);

        [, $organization] = $this->createOwnerContext(['ai' => [
            'active' => true,
            'model' => 'gpt-4o-mini',
            'key_source' => 'organization',
            'api_key_encrypted' => Crypt::encryptString('sk-test'),
        ]], true);
        $this->site = $this->widgetFor($organization->id);
        app(SiteWidget::class)->remember($organization);
    }

    public function test_the_assistant_gets_the_signed_in_customers_account(): void
    {
        [$user, $organization] = $this->customer(['tickets' => ['active' => false], 'ai' => ['active' => true]]);
        $this->fakeReply('أهلاً');

        $token = $this->openSession($this->identityFor($user, $organization->id));
        $this->postJson($this->url('messages'), ['text' => 'وش باقتي؟', 'identity' => $this->identityFor($user, $organization->id)], $this->headers($token))->assertOk();

        Http::assertSent(function ($request) use ($organization) {
            $system = collect($request['messages'] ?? [])->where('role', 'system')->pluck('content')->implode("\n");

            return str_contains($system, 'signed-in Botzo customer')
                && str_contains($system, 'Name: Sara Test')
                && str_contains($system, 'Business: '.$organization->name)
                && str_contains($system, 'Ticketing turned on: no')
                && str_contains($system, 'AI assistant turned on: yes');
        });
    }

    public function test_a_guest_forged_or_expired_token_gives_no_account(): void
    {
        [$user, $organization] = $this->customer();
        $forged = 'bzid.'.base64_encode(json_encode(['u' => $user->id, 'o' => $organization->id, 'e' => now()->addHour()->timestamp]));
        $expired = 'bzid.'.Crypt::encryptString(json_encode(['u' => $user->id, 'o' => $organization->id, 'e' => now()->subMinute()->timestamp]));
        $identities = app(SiteVisitorIdentity::class);

        $this->assertNull($identities->resolve($this->site, $forged));
        $this->assertNull($identities->resolve($this->site, $expired));
        $this->assertNull($identities->resolve($this->site, ''));
        $this->assertNotNull($identities->resolve($this->site, $this->identityFor($user, $organization->id)));

        $this->fakeReply('أهلاً');
        $token = $this->openSession($forged);
        $this->postJson($this->url('messages'), ['text' => 'hi', 'identity' => $forged], $this->headers($token))->assertOk();
        Http::assertSent(fn ($request) => ! str_contains(json_encode($request['messages'] ?? [], JSON_UNESCAPED_UNICODE), 'signed-in Botzo customer'));
    }

    public function test_a_token_is_refused_by_any_other_widget_and_for_someone_outside_the_organization(): void
    {
        [$user, $organization] = $this->customer();
        [, $other] = $this->customer();
        $customerWidget = $this->widgetFor($organization->id);
        $identities = app(SiteVisitorIdentity::class);

        $this->assertNull($identities->resolve($customerWidget, $this->identityFor($user, $organization->id)), 'Only Botzo\'s own widget reads tokens.');
        $this->assertNull($identities->tokenFor($user, $other->id), 'No token for an organization the person is not in.');
    }

    public function test_signing_out_or_another_customer_gets_a_new_chat(): void
    {
        [$sara, $saraOrganization] = $this->customer();
        [$omar, $omarOrganization] = $this->customer();
        $this->fakeReply('أهلاً');

        $token = $this->openSession($this->identityFor($sara, $saraOrganization->id));
        $this->postJson($this->url('messages'), ['text' => 'سر سارة'], $this->headers($token))->assertOk();

        // Another customer on the same browser does not see Sara's chat.
        $response = $this->postJson($this->url('session'), ['token' => $token, 'identity' => $this->identityFor($omar, $omarOrganization->id)], ['Origin' => self::ORIGIN])->assertOk();
        $this->assertNotSame('', (string) $response->json('token'));
        $this->assertSame([], $response->json('messages') ?? []);

        // Sending on Sara's chat after signing out asks the widget to open a new one.
        $this->postJson($this->url('messages'), ['text' => 'hi', 'identity' => ''], $this->headers($token))->assertStatus(401);

        // An expired token after a long idle tab changes nothing.
        $expired = 'bzid.'.Crypt::encryptString(json_encode(['u' => $sara->id, 'o' => $saraOrganization->id, 'e' => now()->subMinute()->timestamp]));
        $this->travel(5)->seconds();
        $this->postJson($this->url('messages'), ['text' => 'hi again', 'identity' => $expired], $this->headers($token))->assertOk();
    }

    public function test_signing_in_keeps_the_guest_chat(): void
    {
        [$user, $organization] = $this->customer();
        $this->fakeReply('أهلاً');

        $token = $this->openSession('');
        $this->postJson($this->url('messages'), ['text' => 'hi', 'identity' => ''], $this->headers($token))->assertOk();

        $response = $this->postJson($this->url('session'), ['token' => $token, 'identity' => $this->identityFor($user, $organization->id)], ['Origin' => self::ORIGIN])->assertOk();
        $this->assertNull($response->json('token'), 'The same session continues.');
        $this->assertNotEmpty($response->json('messages'));
    }

    /**
     * @return array{0: User, 1: \App\Models\Organization}
     */
    private function customer(array $metadata = []): array
    {
        [$user, $organization] = $this->createOwnerContext($metadata, true);
        $user->forceFill(['first_name' => 'Sara', 'last_name' => 'Test'])->save();

        return [$user, $organization];
    }

    private function identityFor(User $user, int $organizationId): string
    {
        return (string) app(SiteVisitorIdentity::class)->tokenFor($user, $organizationId);
    }

    private function widgetFor(int $organizationId): WebWidget
    {
        return WebWidget::create([
            'organization_id' => $organizationId,
            'public_key' => WebWidget::generatePublicKey(),
            'is_enabled' => true,
            'allowed_domains' => ['botzo.example.com'],
            'settings' => [],
        ]);
    }

    private function fakeReply(string $text): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => $text]]]]),
            '*' => Http::response([], 500),
        ]);
    }

    private function openSession(string $identity): string
    {
        return (string) $this->postJson($this->url('session'), ['identity' => $identity], ['Origin' => self::ORIGIN])->json('token');
    }

    private function headers(string $token): array
    {
        return ['Origin' => self::ORIGIN, 'X-Widget-Token' => $token];
    }

    private function url(string $path): string
    {
        return '/api/widget/'.$this->site->public_key.'/'.$path;
    }
}
