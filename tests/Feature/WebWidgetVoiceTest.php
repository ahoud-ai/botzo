<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Subscription;
use App\Models\WebWidget;
use App\Models\WebWidgetVoiceCall;
use App\Services\AddonStateService;
use App\Services\IntelliReply\AiConversationPauseService;
use App\Services\WebWidget\WebWidgetDefaults;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Modules\IntelliReply\Models\Faq;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class WebWidgetVoiceTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    private const ORIGIN = 'https://shop.example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(AddonStateService::class)->shouldReceive('isModuleEnabledForOrganization')->andReturn(true);
        config(['logging.channels.intellireply' => ['driver' => 'null']]);
        Http::fake([
            'api.openai.com/v1/realtime/client_secrets' => Http::response(['value' => 'ek_test_secret', 'expires_at' => 1893456000]),
            'api.openai.com/v1/embeddings' => Http::response(['data' => [['embedding' => [0.1, 0.2]]]]),
            '*' => Http::response([], 500),
        ]);
    }

    public function test_voice_is_offered_only_when_the_widget_and_the_plan_allow_it(): void
    {
        $off = $this->widget(voiceEnabled: false, planMinutes: 30);
        $this->postJson($this->url($off, 'session'), [], ['Origin' => self::ORIGIN])->assertJsonPath('settings.voice', false);

        $noPlan = $this->widget(voiceEnabled: true, planMinutes: 0);
        $this->postJson($this->url($noPlan, 'session'), [], ['Origin' => self::ORIGIN])->assertJsonPath('settings.voice', false);

        $on = $this->widget(voiceEnabled: true, planMinutes: 30);
        $this->postJson($this->url($on, 'session'), [], ['Origin' => self::ORIGIN])->assertJsonPath('settings.voice', true);
    }

    public function test_starting_a_call_returns_a_short_lived_secret_and_never_the_api_key(): void
    {
        $widget = $this->widget(voiceEnabled: true, planMinutes: 30, pages: [['label' => 'الأسعار', 'url' => '/pricing']]);
        $token = $this->openSession($widget);

        $response = $this->postJson($this->url($widget, 'voice/start'), ['page_url' => self::ORIGIN.'/', 'locale' => 'ar'], $this->headers($token))
            ->assertOk()
            ->assertJsonPath('client_secret', 'ek_test_secret')
            ->assertJsonPath('max_seconds', 300)
            ->assertJsonPath('calls_url', 'https://api.openai.com/v1/realtime/calls');

        $this->assertStringNotContainsString('sk-test', $response->getContent());
        $this->assertSame(1, WebWidgetVoiceCall::where('organization_id', $widget->organization_id)->count());

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'realtime/client_secrets')) {
                return false;
            }
            $tools = array_column($request['session']['tools'], 'name');

            return $request->hasHeader('Authorization', 'Bearer sk-test')
                && $request['session']['model'] === 'gpt-realtime-2.1-mini'
                && $request['session']['audio']['output']['voice'] === 'alloy'
                && $request['session']['audio']['input']['transcription']['language'] === 'ar'
                && $tools === ['search_knowledge', 'request_human', 'navigate_to']
                && str_contains($request['session']['instructions'], 'live voice call');
        });
    }

    public function test_calls_stop_when_the_plan_minutes_or_the_platform_cap_run_out(): void
    {
        $widget = $this->widget(voiceEnabled: true, planMinutes: 10);
        $token = $this->openSession($widget);
        $this->pastCall($widget, 600);

        $this->postJson($this->url($widget, 'voice/start'), [], $this->headers($token))
            ->assertStatus(409)
            ->assertJsonPath('error', 'plan_minutes_used');

        $other = $this->widget(voiceEnabled: true, planMinutes: -1);
        app(WebWidgetDefaults::class)->save([], ['call_max_minutes' => 5, 'daily_minutes_cap' => 10]);
        $this->postJson($this->url($other, 'voice/start'), [], $this->headers($this->openSession($other)))
            ->assertStatus(409)
            ->assertJsonPath('error', 'platform_daily_cap');
    }

    public function test_a_call_is_counted_by_server_time_and_its_lines_reach_the_inbox(): void
    {
        $widget = $this->widget(voiceEnabled: true, planMinutes: 30);
        $token = $this->openSession($widget);
        $callId = $this->postJson($this->url($widget, 'voice/start'), [], $this->headers($token))->json('call_id');

        $this->postJson($this->url($widget, "voice/{$callId}/transcript"), ['role' => 'user', 'text' => 'كم الأسعار؟'], $this->headers($token))->assertJsonPath('stored', true);
        $this->postJson($this->url($widget, "voice/{$callId}/transcript"), ['role' => 'assistant', 'text' => 'عندنا ثلاث باقات'], $this->headers($token))->assertJsonPath('stored', true);

        // The widget claims a long call right after starting it: only the time the server saw counts.
        $this->postJson($this->url($widget, "voice/{$callId}/end"), ['seconds' => 280], $this->headers($token))->assertOk();
        $call = WebWidgetVoiceCall::where('uuid', $callId)->sole();
        $this->assertSame(WebWidgetVoiceCall::STATUS_ENDED, $call->status);
        $this->assertLessThanOrEqual(6, $call->seconds);

        $lines = Chat::where('contact_id', $call->contact_id)->orderBy('id')->get();
        $this->assertSame(['inbound', 'outbound'], $lines->pluck('type')->all());
        $this->assertSame($callId, json_decode($lines[0]->metadata, true)['voice_call']);
    }

    public function test_a_new_call_continues_from_the_earlier_conversation(): void
    {
        $widget = $this->widget(voiceEnabled: true, planMinutes: 30);
        $token = $this->openSession($widget);
        $first = $this->postJson($this->url($widget, 'voice/start'), [], $this->headers($token))->json('call_id');
        $this->postJson($this->url($widget, "voice/{$first}/transcript"), ['role' => 'user', 'text' => 'أبي أعرف باقة النمو'], $this->headers($token));
        $this->postJson($this->url($widget, "voice/{$first}/end"), ['seconds' => 5], $this->headers($token));

        $this->postJson($this->url($widget, 'voice/start'), [], $this->headers($token))->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'realtime/client_secrets')
            && str_contains($request['session']['instructions'], 'Visitor: أبي أعرف باقة النمو'));
    }

    public function test_tools_search_the_knowledge_and_hand_over_to_the_team(): void
    {
        $widget = $this->widget(voiceEnabled: true, planMinutes: 30);
        Faq::create(['organization_id' => $widget->organization_id, 'question' => 'كم سعر باقة النمو؟', 'answer' => '599 ريال في الشهر']);
        $token = $this->openSession($widget);
        $callId = $this->postJson($this->url($widget, 'voice/start'), [], $this->headers($token))->json('call_id');

        $this->postJson($this->url($widget, "voice/{$callId}/tool"), ['name' => 'search_knowledge', 'arguments' => ['query' => 'سعر النمو']], $this->headers($token))
            ->assertOk()
            ->assertJsonPath('result', fn ($result) => str_contains($result, '599'));

        $this->postJson($this->url($widget, "voice/{$callId}/tool"), ['name' => 'request_human'], $this->headers($token))->assertOk();
        $call = WebWidgetVoiceCall::where('uuid', $callId)->sole();
        $this->assertTrue(app(AiConversationPauseService::class)->isPaused(\App\Models\Contact::find($call->contact_id)));

        $this->postJson($this->url($widget, "voice/{$callId}/tool"), ['name' => 'delete_everything'], $this->headers($token))->assertUnprocessable();
    }

    public function test_another_visitor_cannot_touch_the_call(): void
    {
        $widget = $this->widget(voiceEnabled: true, planMinutes: 30);
        $callId = $this->postJson($this->url($widget, 'voice/start'), [], $this->headers($this->openSession($widget)))->json('call_id');

        $this->postJson($this->url($widget, "voice/{$callId}/end"), ['seconds' => 10], $this->headers($this->openSession($widget)))->assertNotFound();
    }

    private function widget(bool $voiceEnabled, int $planMinutes, array $pages = []): WebWidget
    {
        [, $organization] = $this->createOwnerContext(['ai' => [
            'active' => true,
            'model' => 'gpt-4o-mini',
            'key_source' => 'organization',
            'api_key_encrypted' => Crypt::encryptString('sk-test'),
        ]], true);

        $plan = Subscription::where('organization_id', $organization->id)->latest('id')->first()->plan;
        $metadata = $plan->metadataArray();
        $metadata['widget_voice_minutes_monthly'] = $planMinutes;
        $plan->update(['metadata' => json_encode($metadata)]);

        return WebWidget::create([
            'organization_id' => $organization->id,
            'public_key' => WebWidget::generatePublicKey(),
            'is_enabled' => true,
            'allowed_domains' => ['shop.example.com'],
            'settings' => ['voice_enabled' => $voiceEnabled, 'voice' => 'alloy', 'pages' => $pages],
        ]);
    }

    private function pastCall(WebWidget $widget, int $seconds): void
    {
        WebWidgetVoiceCall::create([
            'web_widget_id' => $widget->id,
            'organization_id' => $widget->organization_id,
            'web_widget_session_id' => 0,
            'model' => 'gpt-realtime-2.1-mini',
            'voice' => 'alloy',
            'status' => WebWidgetVoiceCall::STATUS_ENDED,
            'max_seconds' => $seconds,
            'seconds' => $seconds,
            'started_at' => now()->subHour(),
            'ended_at' => now()->subHour()->addSeconds($seconds),
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
