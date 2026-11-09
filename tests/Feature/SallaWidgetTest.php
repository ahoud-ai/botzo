<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SallaStore;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\WebWidget;
use App\Services\Salla\SallaPlans;
use App\Services\Salla\SallaStoreInstaller;
use App\Services\Salla\SallaWidgetProvisioner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Installing the app gives the store its assistant and widget, and the script in the store finds
 * the widget from the store's id, only on the store's own domain and while its subscription runs.
 */
class SallaWidgetTest extends TestCase
{
    use DatabaseTransactions;

    private const MERCHANT = 1976195620;

    private const ORIGIN = 'https://demostore.salla.sa';

    /** What Salla's user info answers; null makes it fail. */
    private ?array $sallaAnswer = null;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array', 'services.salla.user_info_url' => 'https://salla.test/user/info']);
        SubscriptionPlan::query()->where('metadata', 'like', '%"salla_plan_key"%')->forceDelete();
        app(SallaPlans::class)->setup();
        Http::fake(['salla.test/*' => fn () => $this->sallaAnswer ? Http::response($this->sallaAnswer) : Http::response([], 500)]);
    }

    private function fakeSalla(string $domain = 'https://demostore.salla.sa/dev-abc', string $name = 'متجر تجريبي'): void
    {
        $this->sallaAnswer = ['data' => [
            'email' => 'owner@example.com',
            'merchant' => ['id' => self::MERCHANT, 'name' => $name, 'domain' => $domain],
        ]];
    }

    private function install(string $access = 'access', ?\DateTimeInterface $at = null): SallaStore
    {
        return app(SallaStoreInstaller::class)->authorize(self::MERCHANT, [
            'access_token' => $access,
            'refresh_token' => 'refresh',
            'expires' => now()->addDays(14)->timestamp,
            'scope' => 'settings.read offline_access',
        ], now()->parse($at ?? now()));
    }

    private function subscribe(SallaStore $store, ?\DateTimeInterface $until = null): void
    {
        Subscription::query()->where('organization_id', $store->organization_id)->delete();
        Subscription::create([
            'organization_id' => $store->organization_id,
            'plan_id' => app(SallaPlans::class)->forName(null, self::MERCHANT)->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => $until ?? now()->addMonth(),
        ]);
    }

    private function key(string $origin = self::ORIGIN, int|string $merchant = self::MERCHANT): \Illuminate\Testing\TestResponse
    {
        return $this->getJson('/api/salla/widget/'.$merchant, $origin === '' ? [] : ['Origin' => $origin]);
    }

    private function widget(SallaStore $store): WebWidget
    {
        return WebWidget::query()->where('organization_id', $store->organization_id)->firstOrFail();
    }

    private function ai(SallaStore $store): array
    {
        return (array) data_get(json_decode((string) Organization::find($store->organization_id)->metadata, true), 'ai', []);
    }

    public function test_install_creates_the_widget_and_assistant_for_the_store_domain(): void
    {
        $this->fakeSalla();

        $store = $this->install();
        $widget = $this->widget($store);

        $this->assertTrue($widget->is_enabled);
        $this->assertMatchesRegularExpression('/^wgt_[A-Za-z0-9]{40}$/', $widget->public_key);
        $this->assertSame(['demostore.salla.sa'], $widget->allowed_domains);

        $ai = $this->ai($store);
        $this->assertTrue($ai['active']);
        $this->assertTrue($ai['enable_automatic_responses']);
        $this->assertSame('global', $ai['key_source']);
        $this->assertSame('متجر تجريبي', $ai['persona']['business_name']);
        $this->assertSame('auto', $ai['persona']['dialect'], 'Mochi answers in the customer\'s own language and dialect.');
    }

    public function test_reinstall_keeps_the_key_and_what_the_merchant_changed(): void
    {
        $this->fakeSalla();
        $store = $this->install();
        $key = $this->widget($store)->public_key;

        $organization = Organization::find($store->organization_id);
        $metadata = json_decode((string) $organization->metadata, true);
        $metadata['ai']['persona']['extra_instructions'] = 'تعليمات التاجر';
        $metadata['ai']['enable_automatic_responses'] = false;
        $organization->forceFill(['metadata' => json_encode($metadata)])->save();

        $store = $this->install('access-2');

        $this->assertSame(1, WebWidget::query()->where('organization_id', $store->organization_id)->count());
        $this->assertSame($key, $this->widget($store)->public_key);
        $this->assertSame('تعليمات التاجر', $this->ai($store)['persona']['extra_instructions']);
        $this->assertFalse($this->ai($store)['enable_automatic_responses']);
    }

    public function test_store_details_that_arrive_late_fill_the_domain_and_name(): void
    {
        $store = $this->install();

        $this->assertSame([], $this->widget($store)->allowed_domains);
        $this->assertSame('', $this->ai($store)['persona']['business_name']);

        $this->fakeSalla();
        app(SallaStoreInstaller::class)->backfillDetails($store->refresh());

        $this->assertSame(['demostore.salla.sa'], $this->widget($store)->allowed_domains);
        $this->assertSame('متجر تجريبي', $this->ai($store)['persona']['business_name']);
    }

    public function test_the_store_gets_its_key_on_its_own_domain(): void
    {
        $this->fakeSalla();
        $store = $this->install();
        $this->subscribe($store);

        $this->key()
            ->assertOk()
            ->assertExactJson(['key' => $this->widget($store)->public_key])
            ->assertHeader('Access-Control-Allow-Origin');
    }

    public function test_the_key_and_widget_work_together_from_the_store(): void
    {
        $this->fakeSalla();
        $store = $this->install();
        $this->subscribe($store);
        $key = $this->key()->json('key');

        $this->getJson('/api/widget/'.$key.'/config', ['Origin' => self::ORIGIN])
            ->assertOk()
            ->assertJsonPath('settings.theme', 'store')
            ->assertJsonPath('settings.business_label', 'متجر تجريبي')
            ->assertJsonPath('settings.voice', false);
    }

    public function test_a_visitor_message_in_the_store_reaches_the_assistant(): void
    {
        $this->fakeSalla();
        $store = $this->install();
        $this->subscribe($store);
        $key = $this->key()->json('key');
        $assistant = $this->mock(\Modules\IntelliReply\Services\AIResponseService::class);
        $assistant->shouldReceive('handleAIResponse')->once()->andReturn(true);

        $token = $this->postJson('/api/widget/'.$key.'/session', ['locale' => 'ar'], ['Origin' => self::ORIGIN])
            ->assertOk()
            ->json('token');

        $this->postJson('/api/widget/'.$key.'/messages', ['text' => 'هلا'], ['Origin' => self::ORIGIN, 'X-Widget-Token' => $token])
            ->assertOk()
            ->assertJsonPath('awaiting_team', false);
    }

    public function test_shoppers_sharing_one_address_are_not_blocked_at_the_normal_limit(): void
    {
        config(['web_widget.limits.sessions_per_ip_per_hour' => 2, 'web_widget.limits.store_sessions_per_ip_per_hour' => 4]);
        $this->fakeSalla();
        $store = $this->install();
        $this->subscribe($store);
        $key = $this->widget($store)->public_key;

        foreach (range(1, 4) as $visitor) {
            $this->postJson('/api/widget/'.$key.'/session', [], ['Origin' => self::ORIGIN])->assertOk();
        }

        $this->postJson('/api/widget/'.$key.'/session', [], ['Origin' => self::ORIGIN])->assertStatus(429);
    }

    public function test_no_key_for_another_domain_without_an_origin_or_for_an_unknown_store(): void
    {
        $this->fakeSalla();
        $this->subscribe($this->install());

        $this->key('https://other.example.com')->assertForbidden()->assertExactJson(['error' => 'widget_unavailable']);
        $this->key('')->assertForbidden();
        $this->key(self::ORIGIN, 12345)->assertForbidden()->assertExactJson(['error' => 'widget_unavailable']);
        $this->getJson('/api/salla/widget/abc')->assertNotFound();
    }

    public function test_no_key_after_uninstall_or_when_the_subscription_ended_or_the_widget_is_off(): void
    {
        $this->fakeSalla();
        $store = $this->install();

        $this->key()->assertForbidden();

        $this->subscribe($store, now()->subMinute());
        $this->key()->assertForbidden();

        $this->subscribe($store);
        $this->key()->assertOk();

        $this->widget($store)->update(['is_enabled' => false]);
        $this->key()->assertForbidden();
        $this->widget($store)->update(['is_enabled' => true]);

        app(SallaStoreInstaller::class)->uninstall(self::MERCHANT, now()->addMinute());
        $this->key()->assertForbidden();
    }

    public function test_a_store_that_moved_to_a_new_domain_keeps_working(): void
    {
        $this->fakeSalla();
        $store = $this->install();
        $this->subscribe($store);

        $this->fakeSalla('https://www.my-shop.example');

        $this->key('https://www.my-shop.example')->assertOk();

        $this->assertSame(['www.my-shop.example'], $this->widget($store)->allowed_domains);
        $this->key()->assertForbidden();
    }

    public function test_an_unknown_domain_asks_salla_at_most_once_an_hour(): void
    {
        $this->fakeSalla();
        $store = $this->install();
        $this->subscribe($store);
        $this->sallaAnswer = null;
        $before = count(Http::recorded());

        $this->key('https://other.example.com')->assertForbidden();
        $this->key('https://other.example.com')->assertForbidden();
        $this->key('https://another.example.com')->assertForbidden();

        $this->assertSame(1, count(Http::recorded()) - $before);
        $this->assertSame(['demostore.salla.sa'], $this->widget($store->refresh())->allowed_domains);
    }

    public function test_the_domain_lookup_is_skipped_after_uninstall_deleted_the_tokens(): void
    {
        $this->fakeSalla();
        $store = $this->install();
        $store->forceFill(['access_token' => null])->save();
        $this->subscribe($store);
        $before = count(Http::recorded());

        $this->key('https://other.example.com')->assertForbidden();

        $this->assertSame(0, count(Http::recorded()) - $before);
    }

    public function test_running_it_twice_does_not_change_anything(): void
    {
        $this->fakeSalla();
        $store = $this->install();
        $widget = $this->widget($store);
        $metadata = Organization::find($store->organization_id)->metadata;

        app(SallaWidgetProvisioner::class)->ensure($store);

        $this->assertSame($widget->public_key, $this->widget($store)->public_key);
        $this->assertSame($metadata, Organization::find($store->organization_id)->metadata);
    }
}
