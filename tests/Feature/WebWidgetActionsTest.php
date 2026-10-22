<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\WebWidget;
use App\Services\AddonStateService;
use App\Services\WebWidget\SiteWidget;
use App\Services\WebWidget\WebWidgetAssistantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

/**
 * The assistant can take a website visitor to an allowed page or show the pricing card by
 * ending its reply with a marker; the marker never reaches the visitor or the inbox.
 */
class WebWidgetActionsTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    private const ORIGIN = 'https://shop.example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(AddonStateService::class)->shouldReceive('isModuleEnabledForOrganization')->andReturn(true);
        config(['logging.channels.intellireply' => ['driver' => 'null']]);
    }

    public function test_markers_are_taken_out_of_the_text(): void
    {
        $this->assertSame(
            ['text' => 'أبشر، خلني أوديك صفحة الأسعار 👇', 'action' => ['type' => 'navigate', 'key' => 'p1']],
            WebWidgetAssistantContext::extractActions('أبشر، خلني أوديك صفحة الأسعار 👇 [[GO:p1]]')
        );
        $this->assertSame(['text' => 'hello', 'action' => null], WebWidgetAssistantContext::extractActions('hello'));
    }

    public function test_the_assistant_knows_the_page_and_takes_the_visitor_to_an_allowed_page(): void
    {
        $this->fakeReply('أبشر، خلني أوديك صفحة الأسعار 👇 [[GO:p1]]');
        $widget = $this->widget([['label' => 'الأسعار', 'url' => '/pricing']]);
        $token = $this->openSession($widget);

        $response = $this->postJson($this->url($widget, 'messages'), [
            'text' => 'وريني الأسعار',
            'page_url' => self::ORIGIN.'/features',
            'page_title' => 'المميزات',
        ], $this->headers($token))->assertOk();

        $response->assertJsonPath('messages.1.text', 'أبشر، خلني أوديك صفحة الأسعار 👇')
            ->assertJsonPath('messages.1.action.type', 'navigate')
            ->assertJsonPath('messages.1.action.url', '/pricing');

        Http::assertSent(function ($request) {
            $system = collect($request['messages'] ?? [])->where('role', 'system')->pluck('content')->implode("\n");

            return str_contains($system, 'p1: الأسعار (/pricing)') && str_contains($system, '"المميزات" ('.self::ORIGIN.'/features)');
        });
    }

    public function test_an_unknown_page_key_does_nothing(): void
    {
        $this->fakeReply('خلني أوديك [[GO:p9]]');
        $widget = $this->widget([['label' => 'الأسعار', 'url' => '/pricing']]);

        $this->postJson($this->url($widget, 'messages'), ['text' => 'hi'], $this->headers($this->openSession($widget)))
            ->assertOk()
            ->assertJsonPath('messages.1.text', 'خلني أوديك')
            ->assertJsonMissingPath('messages.1.action');
    }

    public function test_the_pricing_card_shows_only_on_botzos_own_widget(): void
    {
        $this->fakeReply('عندنا ثلاث باقات، هذي الأكثر طلباً: [[CARD:pricing]]');
        foreach ([1 => 299, 2 => 599, 3 => 999] as $tier => $price) {
            SubscriptionPlan::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Plan '.$tier,
                'name_ar' => 'باقة '.$tier,
                'price' => $price,
                'period' => 'monthly',
                'status' => 'active',
                'metadata' => json_encode(['tier_rank' => 900 + $tier]),
            ]);
        }

        $customer = $this->widget([]);
        $this->postJson($this->url($customer, 'messages'), ['text' => 'كم الأسعار؟'], $this->headers($this->openSession($customer)))
            ->assertOk()
            ->assertJsonMissingPath('messages.1.action');

        $site = $this->widget([]);
        app(SiteWidget::class)->remember($site->organization);
        $this->postJson($this->url($site, 'messages'), ['text' => 'كم الأسعار؟'], $this->headers($this->openSession($site)))
            ->assertOk()
            ->assertJsonPath('messages.1.text', 'عندنا ثلاث باقات، هذي الأكثر طلباً:')
            ->assertJsonPath('messages.1.action.type', 'pricing')
            ->assertJsonPath('messages.1.action.currency', 'SAR');
    }

    private function fakeReply(string $text): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => $text]]]]),
            '*' => Http::response([], 500),
        ]);
    }

    private function widget(array $pages): WebWidget
    {
        [, $organization] = $this->createOwnerContext(['ai' => [
            'active' => true,
            'model' => 'gpt-4o-mini',
            'key_source' => 'organization',
            'api_key_encrypted' => Crypt::encryptString('sk-test'),
        ]], true);

        return WebWidget::create([
            'organization_id' => $organization->id,
            'public_key' => WebWidget::generatePublicKey(),
            'is_enabled' => true,
            'allowed_domains' => ['shop.example.com'],
            'settings' => ['pages' => $pages],
        ])->load('organization');
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
