<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\WebWidget;
use App\Services\AddonStateService;
use App\Services\WebWidget\WebWidgetMessageStore;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class WebWidgetApiTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    private const ORIGIN = 'https://shop.example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(AddonStateService::class)->shouldReceive('isModuleEnabledForOrganization')->andReturn(true);
        config(['logging.channels.intellireply' => ['driver' => 'null']]);
        config(['intellireply.handoff.confirmation_text' => 'هحوّلك لحد من الفريق.']);
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'أهلاً! أقدر أساعدك في إيه؟']]]]),
            '*' => Http::response([], 500),
        ]);
    }

    public function test_the_widget_only_answers_on_its_allowed_domains(): void
    {
        $widget = $this->widget();

        $this->postJson($this->url($widget, 'session'), [], ['Origin' => 'https://evil.example.net'])->assertForbidden();
        $this->postJson($this->url($widget, 'session'))->assertForbidden();
        $this->postJson('/api/widget/wgt_short/session', [], ['Origin' => self::ORIGIN])->assertForbidden();

        $widget->update(['is_enabled' => false]);
        $this->postJson($this->url($widget, 'session'), [], ['Origin' => self::ORIGIN])->assertForbidden();
    }

    public function test_opening_the_widget_returns_a_token_and_settings_without_creating_a_contact(): void
    {
        $widget = $this->widget(['welcome_text' => 'أهلاً بيك', 'suggestions' => ['الأسعار']]);
        $contacts = Contact::where('organization_id', $widget->organization_id)->count();

        $response = $this->postJson($this->url($widget, 'session'), ['locale' => 'ar'], ['Origin' => self::ORIGIN])->assertOk();

        $this->assertSame(64, strlen((string) $response->json('token')));
        $response->assertJsonPath('settings.welcome_text', 'أهلاً بيك')
            ->assertJsonPath('settings.suggestions.0', 'الأسعار')
            ->assertJsonPath('messages', []);
        $this->assertSame($contacts, Contact::where('organization_id', $widget->organization_id)->count());

        // Reopening with the token resumes the same session and issues no new token.
        $again = $this->postJson($this->url($widget, 'session'), ['token' => $response->json('token')], ['Origin' => self::ORIGIN])->assertOk();
        $this->assertNull($again->json('token'));
    }

    public function test_a_visitor_message_is_answered_in_the_widget_and_never_sent_to_whatsapp(): void
    {
        $widget = $this->widget();
        $token = $this->openSession($widget);

        $response = $this->postJson($this->url($widget, 'messages'), ['text' => 'بكام الكبسة؟'], $this->headers($token))->assertOk();

        $response->assertJsonPath('awaiting_team', false)
            ->assertJsonPath('messages.0.from', 'visitor')
            ->assertJsonPath('messages.0.text', 'بكام الكبسة؟')
            ->assertJsonPath('messages.1.from', 'assistant')
            ->assertJsonPath('messages.1.text', 'أهلاً! أقدر أساعدك في إيه؟');

        $contact = Contact::where('organization_id', $widget->organization_id)->where('channel', Contact::CHANNEL_WEB)->sole();
        $this->assertNull($contact->phone);
        $this->assertSame(2, Chat::where('contact_id', $contact->id)->count());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
    }

    public function test_requests_need_the_visitors_own_token(): void
    {
        $widget = $this->widget();
        $other = $this->widget();
        $otherToken = $this->openSession($other);

        $this->postJson($this->url($widget, 'messages'), ['text' => 'hi'], ['Origin' => self::ORIGIN])->assertUnauthorized();
        $this->postJson($this->url($widget, 'messages'), ['text' => 'hi'], $this->headers('not-a-real-token'))->assertUnauthorized();
        $this->postJson($this->url($widget, 'messages'), ['text' => 'hi'], $this->headers($otherToken))->assertUnauthorized();
        $this->getJson($this->url($widget, 'messages'), $this->headers($otherToken))->assertUnauthorized();
    }

    public function test_messages_too_fast_or_too_long_are_refused(): void
    {
        $widget = $this->widget();
        $token = $this->openSession($widget);

        $this->postJson($this->url($widget, 'messages'), ['text' => str_repeat('a', 501)], $this->headers($token))->assertUnprocessable();
        $this->postJson($this->url($widget, 'messages'), ['text' => 'first'], $this->headers($token))->assertOk();
        $this->postJson($this->url($widget, 'messages'), ['text' => 'second'], $this->headers($token))
            ->assertStatus(429)
            ->assertJsonPath('error', 'too_fast');
    }

    public function test_one_address_cannot_open_endless_sessions(): void
    {
        config(['web_widget.limits.sessions_per_ip_per_hour' => 2]);
        $widget = $this->widget();

        $this->postJson($this->url($widget, 'session'), [], ['Origin' => self::ORIGIN])->assertOk();
        $this->postJson($this->url($widget, 'session'), [], ['Origin' => self::ORIGIN])->assertOk();
        $this->postJson($this->url($widget, 'session'), [], ['Origin' => self::ORIGIN])->assertStatus(429);
    }

    public function test_over_the_daily_cap_the_message_still_reaches_the_team_without_an_ai_reply(): void
    {
        $widget = $this->widget();
        $widget->update(['daily_reply_limit' => 0]);
        $token = $this->openSession($widget);

        $this->postJson($this->url($widget, 'messages'), ['text' => 'مساء الخير'], $this->headers($token))
            ->assertOk()
            ->assertJsonPath('awaiting_team', true)
            ->assertJsonCount(1, 'messages');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'openai.com'));
    }

    public function test_with_the_assistant_off_messages_wait_for_the_team(): void
    {
        $widget = $this->widget([], aiActive: false);
        $token = $this->openSession($widget);

        $this->postJson($this->url($widget, 'messages'), ['text' => 'مساء الخير'], $this->headers($token))
            ->assertOk()
            ->assertJsonPath('awaiting_team', true);
    }

    public function test_asking_for_a_person_hands_over_inside_the_widget(): void
    {
        $widget = $this->widget();
        $token = $this->openSession($widget);

        $this->postJson($this->url($widget, 'messages'), ['text' => 'عايز اكلم موظف'], $this->headers($token))
            ->assertOk()
            ->assertJsonPath('messages.1.text', 'هحوّلك لحد من الفريق.');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'openai.com') || str_contains($request->url(), 'graph.facebook.com'));
    }

    public function test_polling_returns_team_replies_after_the_last_seen_message(): void
    {
        $widget = $this->widget();
        $token = $this->openSession($widget);
        $sent = $this->postJson($this->url($widget, 'messages'), ['text' => 'hello'], $this->headers($token))->json('messages');
        $lastId = (int) end($sent)['id'];

        $contact = Contact::where('organization_id', $widget->organization_id)->where('channel', Contact::CHANNEL_WEB)->sole();
        $agentId = (int) Organization::find($widget->organization_id)->created_by;
        app(WebWidgetMessageStore::class)->storeText($contact, 'أنا سارة من الفريق', 'outbound', $agentId);

        $this->getJson($this->url($widget, 'messages').'?after='.$lastId, $this->headers($token))
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.from', 'agent')
            ->assertJsonPath('messages.0.name', 'Owner')
            ->assertJsonPath('messages.0.text', 'أنا سارة من الفريق');
    }

    private function widget(array $settings = [], bool $aiActive = true): WebWidget
    {
        [, $organization] = $this->createOwnerContext(['ai' => [
            'active' => $aiActive,
            'model' => 'gpt-4o-mini',
            'key_source' => 'organization',
            'api_key_encrypted' => Crypt::encryptString('sk-test'),
        ]], true);

        return WebWidget::create([
            'organization_id' => $organization->id,
            'public_key' => WebWidget::generatePublicKey(),
            'is_enabled' => true,
            'allowed_domains' => ['shop.example.com'],
            'settings' => $settings,
        ]);
    }

    private function openSession(WebWidget $widget): string
    {
        return (string) $this->postJson($this->url($widget, 'session'), [], ['Origin' => self::ORIGIN])->json('token');
    }

    private function headers(string $token): array
    {
        return ['Origin' => self::ORIGIN, 'X-Widget-Token' => $token];
    }

    private function url(WebWidget $widget, string $path): string
    {
        return '/api/widget/'.$widget->public_key.'/'.$path;
    }
}
