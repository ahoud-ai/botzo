<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SallaStore;
use App\Models\SallaWebhookEvent;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WebWidget;
use App\Services\IntelliReply\AiUsageLimiterService;
use App\Services\PaymentProcessorAvailabilityService;
use App\Services\Salla\SallaPlans;
use App\Services\Salla\SallaStoreAccess;
use App\Services\SubscriptionPlanLimitService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

/**
 * Salla collects the payment and tells Botzo through events. They set the store's plan and period,
 * which decide whether its assistant runs and how many replies Botzo pays for.
 */
class SallaSubscriptionTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    private const SECRET = 'test-webhook-secret';

    private const MERCHANT = 1976195620;

    private const ORIGIN = 'https://shop.example.com';

    private User $owner;

    private Organization $organization;

    private SallaStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array', 'services.salla.webhook_secret' => self::SECRET]);
        SubscriptionPlan::query()->where('metadata', 'like', '%"salla_plan_key"%')->forceDelete();
        app(SallaPlans::class)->setup();

        [$this->owner, $this->organization] = $this->createOwnerContext();
        $this->store = SallaStore::create([
            'merchant_id' => self::MERCHANT,
            'organization_id' => $this->organization->id,
            'status' => SallaStore::STATUS_INSTALLED,
            'access_token' => 'access',
            'refresh_token' => 'refresh',
            'installed_at' => now()->subDay(),
        ]);
    }

    public function test_setup_creates_hidden_plans_and_never_overwrites_them(): void
    {
        $plan = $this->plan(SallaPlans::FALLBACK);
        $this->assertSame('inactive', $plan->status);
        $this->assertSame(1000, $plan->metadataValue('ai_system_key_monthly_quota'));
        $this->assertSame(0, $plan->metadataValue('widget_voice_minutes_monthly'));
        $this->assertSame(100, $this->plan(SallaPlans::TRIAL)->metadataValue('ai_system_key_monthly_quota'));
        $this->assertSame(3000, $this->plan('growth')->metadataValue('ai_system_key_monthly_quota'));
        $this->assertSame(10000, $this->plan('pro')->metadataValue('ai_system_key_monthly_quota'));

        $metadata = $plan->metadataArray();
        $metadata['ai_system_key_monthly_quota'] = 1500;
        $plan->update(['metadata' => json_encode($metadata), 'price' => 149]);

        $this->artisan('salla:setup-plans')->assertSuccessful();

        $plan->refresh();
        $this->assertSame(1500, $plan->metadataValue('ai_system_key_monthly_quota'));
        $this->assertSame('149.00', (string) $plan->price);
        $this->assertSame(4, SubscriptionPlan::where('metadata', 'like', '%"salla_plan_key"%')->count());
    }

    public function test_a_trial_gives_the_assistant_on_the_trial_plan(): void
    {
        $this->assertFalse($this->access()->assistantAllowed($this->organization->id));

        $this->event('app.trial.started', ['plan_name' => 'Diamond Plan', 'start_date' => now()->toDateString(), 'end_date' => now()->addDays(6)->toDateString()]);

        $subscription = $this->subscription();
        $this->assertSame($this->plan(SallaPlans::TRIAL)->id, (int) $subscription->plan_id);
        $this->assertSame('active', $subscription->status, 'The trial status would fall under the site-wide trial_addons setting.');
        $this->assertSame(now()->addDays(6)->endOfDay()->toDateTimeString(), $subscription->getRawOriginal('valid_until'));
        $this->assertTrue($this->access()->assistantAllowed($this->organization->id));
        $this->assertTrue(app(SubscriptionPlanLimitService::class)->addonEnabledForOrganization($this->organization->id, 'AI Assistant', false));
    }

    public function test_a_trial_without_dates_still_ends(): void
    {
        $this->event('app.trial.started', []);

        $this->assertSame(now()->addDays(7)->toDateString(), substr((string) $this->subscription()->getRawOriginal('valid_until'), 0, 10));
    }

    public function test_the_end_of_a_trial_stops_the_assistant(): void
    {
        $this->event('app.trial.started', ['end_date' => now()->addDays(6)->toDateString()]);

        $this->event('app.trial.expired', []);

        $this->assertFalse($this->access()->assistantAllowed($this->organization->id));
    }

    public function test_a_paid_subscription_sets_the_plan_and_period(): void
    {
        $this->event('app.subscription.started', $this->paid(['plan_name' => 'النمو']));

        $subscription = $this->subscription();
        $this->assertSame($this->plan('growth')->id, (int) $subscription->plan_id);
        $this->assertSame('active', $subscription->status);
        $this->assertSame('2030-02-01 03:00:00', $subscription->getRawOriginal('valid_until'), 'ISO dates in UTC are saved in Riyadh time.');
        $this->assertSame('SUB-1', $this->store->fresh()->salla_subscription_id);
        $this->assertTrue($this->access()->assistantAllowed($this->organization->id));
    }

    public function test_an_unknown_plan_name_still_serves_the_merchant_and_is_logged(): void
    {
        Log::spy();

        $this->event('app.subscription.started', $this->paid(['plan_name' => null]));

        $this->assertSame($this->plan(SallaPlans::FALLBACK)->id, (int) $this->subscription()->plan_id);
        Log::shouldHaveReceived('warning')->with('salla.plan.unknown_name', ['merchant_id' => self::MERCHANT, 'plan_name' => ''])->once();
    }

    public function test_renewal_extends_and_expiry_stops(): void
    {
        $this->event('app.subscription.started', $this->paid(), '2029-12-30 10:00:00');
        $this->event('app.subscription.renewed', $this->paid(['end_date' => '2030-03-01T00:00:00.000000Z']), '2030-01-30 10:00:00');
        $this->assertSame('2030-03-01 03:00:00', $this->subscription()->getRawOriginal('valid_until'));

        $this->event('app.subscription.expired', $this->paid(), '2030-03-01 10:00:00');

        $this->assertFalse($this->access()->assistantAllowed($this->organization->id));
    }

    public function test_canceling_keeps_service_until_the_paid_period_ends(): void
    {
        $this->event('app.subscription.started', $this->paid());

        $this->event('app.subscription.canceled', $this->paid());

        $this->assertSame('2030-02-01 03:00:00', $this->subscription()->getRawOriginal('valid_until'));
        $this->assertTrue($this->access()->assistantAllowed($this->organization->id));

        $this->event('app.subscription.canceled', $this->paid(['end_date' => now()->subDay()->toIso8601String()]));

        $this->assertFalse($this->access()->assistantAllowed($this->organization->id));
    }

    public function test_addons_do_not_change_the_plan(): void
    {
        $this->event('app.subscription.started', $this->paid(['item_type' => 'addon', 'item_slug' => 'addon_chat_support']));

        $this->assertNull($this->subscription());
        $this->assertSame(SallaWebhookEvent::STATUS_IGNORED, SallaWebhookEvent::where('merchant_id', self::MERCHANT)->latest('id')->first()->status);
    }

    public function test_an_older_event_cannot_replace_a_newer_one(): void
    {
        $this->event('app.subscription.started', $this->paid(), '2030-01-10 10:00:00');
        $this->event('app.subscription.expired', $this->paid(), '2030-01-05 10:00:00');

        $this->assertSame('2030-02-01 03:00:00', $this->subscription()->getRawOriginal('valid_until'));
    }

    public function test_the_end_of_an_old_subscription_does_not_stop_an_upgrade(): void
    {
        $this->event('app.subscription.started', $this->paid(), '2030-01-01 10:00:00');
        $this->event('app.subscription.started', $this->paid(['subscription_id' => 'SUB-2', 'plan_name' => 'pro']), '2030-01-10 10:00:00');

        $this->event('app.subscription.expired', $this->paid(['subscription_id' => 'SUB-1']), '2030-01-10 10:00:01');
        $this->event('app.subscription.canceled', $this->paid(['subscription_id' => 'SUB-1', 'end_date' => null]), '2030-01-10 10:00:02');

        $this->assertSame($this->plan('pro')->id, (int) $this->subscription()->plan_id);
        $this->assertTrue($this->access()->assistantAllowed($this->organization->id));
    }

    public function test_a_trial_event_does_not_touch_a_paid_subscription(): void
    {
        $this->event('app.subscription.started', $this->paid());

        $this->event('app.trial.started', ['end_date' => now()->addDays(3)->toDateString()]);
        $this->event('app.trial.expired', []);

        $this->assertSame($this->plan(SallaPlans::FALLBACK)->id, (int) $this->subscription()->plan_id);
        $this->assertTrue($this->access()->assistantAllowed($this->organization->id));
    }

    public function test_a_subscription_before_the_install_event_is_retried(): void
    {
        $this->store->delete();

        $this->event('app.subscription.started', $this->paid(), '2030-01-01 10:00:00', 500);
        $this->assertSame(0, Subscription::where('organization_id', $this->organization->id)->count());

        $this->store = SallaStore::create(['merchant_id' => self::MERCHANT, 'organization_id' => $this->organization->id, 'status' => SallaStore::STATUS_INSTALLED]);

        // Salla delivers the same body again.
        $this->event('app.subscription.started', $this->paid(), '2030-01-01 10:00:00');

        $this->assertNotNull($this->subscription());
        $this->assertSame(SallaWebhookEvent::STATUS_PROCESSED, SallaWebhookEvent::where('merchant_id', self::MERCHANT)->sole()->status);
    }

    public function test_the_reply_limit_holds_even_when_ai_billing_is_off(): void
    {
        Setting::updateOrCreate(['key' => 'enable_ai_billing'], ['value' => '0']);
        $this->event('app.subscription.started', $this->paid());
        $metadata = $this->plan(SallaPlans::FALLBACK)->metadataArray();
        $metadata['ai_system_key_monthly_quota'] = 2;
        $this->plan(SallaPlans::FALLBACK)->update(['metadata' => json_encode($metadata)]);
        $limiter = app(AiUsageLimiterService::class);
        $id = $this->organization->id;

        $this->assertTrue($limiter->canUseText($id, 'global'));
        $this->assertTrue($limiter->consumeText($id, 'global'));
        $this->assertTrue($limiter->consumeText($id, 'global'));

        $this->assertFalse($limiter->canUseText($id, 'global'));
        $this->assertFalse($limiter->consumeText($id, 'global'));

        // The count starts again with the month.
        $this->travelTo(now()->addMonth()->startOfMonth()->addHour());
        \App\Support\RequestMemo::forget('subscription');
        $this->assertTrue($limiter->canUseText($id, 'global'));
    }

    public function test_voice_stays_off_on_salla_plans(): void
    {
        $this->event('app.subscription.started', $this->paid());

        $this->assertFalse(app(AiUsageLimiterService::class)->canUseAudio($this->organization->id, 'global'));
    }

    public function test_other_organizations_keep_their_ai_billing_behavior(): void
    {
        Setting::updateOrCreate(['key' => 'enable_ai_billing'], ['value' => '0']);
        [, $other] = $this->createOwnerContext([], true);

        $this->assertTrue(app(AiUsageLimiterService::class)->canUseText($other->id, 'global'));
    }

    public function test_the_widget_follows_the_subscription(): void
    {
        $widget = $this->widget($this->organization->id);

        $this->getJson($this->config($widget), ['Origin' => self::ORIGIN])->assertForbidden();

        $this->event('app.subscription.started', $this->paid());
        \App\Support\RequestMemo::forget('subscription');
        $this->getJson($this->config($widget), ['Origin' => self::ORIGIN])->assertOk();

        $this->store->update(['status' => SallaStore::STATUS_REAUTH_REQUIRED]);
        $this->getJson($this->config($widget), ['Origin' => self::ORIGIN])->assertOk();

        $this->store->update(['status' => SallaStore::STATUS_UNINSTALLED]);
        $this->getJson($this->config($widget), ['Origin' => self::ORIGIN])->assertForbidden();

        $this->store->update(['status' => SallaStore::STATUS_INSTALLED]);
        $this->subscription()->update(['valid_until' => now()->subMinute()]);
        \App\Support\RequestMemo::forget('subscription');
        $this->getJson($this->config($widget), ['Origin' => self::ORIGIN])->assertForbidden();
    }

    public function test_the_widget_of_other_organizations_is_not_affected(): void
    {
        [, $other] = $this->createOwnerContext();

        $this->getJson($this->config($this->widget($other->id)), ['Origin' => self::ORIGIN])->assertOk();
    }

    public function test_salla_merchants_cannot_pay_through_botzo(): void
    {
        $this->event('app.subscription.started', $this->paid());
        $publicPlan = SubscriptionPlan::create(['name' => 'Public', 'price' => 299, 'period' => 'monthly', 'status' => 'active', 'metadata' => json_encode(['tier_rank' => 9])]);
        $session = $this->actingAs($this->owner)->withSession(['current_organization' => $this->organization->id]);
        $this->mock(PaymentProcessorAvailabilityService::class)->shouldReceive('assertAvailable');

        $session->post('/subscription', ['plan' => $publicPlan->id])->assertForbidden();
        $session->post('/pay', ['amount' => 100, 'method' => 'moyasar'])->assertForbidden();
        $session->post('/subscription/scheduled-change/cancel')->assertForbidden();
        $session->delete('/subscription/coupon/remove/'.$publicPlan->uuid)->assertForbidden();

        $this->assertSame($this->plan(SallaPlans::FALLBACK)->id, (int) $this->subscription()->plan_id);
    }

    public function test_billing_pages_tell_salla_merchants_where_to_manage_the_plan(): void
    {
        $this->event('app.subscription.started', $this->paid());
        $session = $this->actingAs($this->owner)->withSession(['current_organization' => $this->organization->id]);

        $session->get('/subscription')->assertOk()->assertInertia(fn ($page) => $page
            ->component('User/Billing/Plan')
            ->where('subscriptionManagedBySalla', true)
            ->where('methods', [])
            ->has('plans.data', 0));

        $session->get('/billing')->assertOk()->assertInertia(fn ($page) => $page
            ->component('User/Billing/Index')
            ->where('subscriptionManagedBySalla', true)
            ->where('subscriptionManagedByParent', true)
            ->where('methods', []));
    }

    public function test_other_organizations_still_see_the_plans(): void
    {
        [$user, $other] = $this->createOwnerContext([], true);

        $this->actingAs($user)->withSession(['current_organization' => $other->id])
            ->get('/subscription')->assertOk()->assertInertia(fn ($page) => $page
                ->where('subscriptionManagedBySalla', false));
    }

    private function event(string $event, array $data, string $createdAt = '2030-01-01 10:00:00', int $status = 200): void
    {
        $body = json_encode(['event' => $event, 'merchant' => self::MERCHANT, 'created_at' => $createdAt, 'data' => $data]);

        $this->call('POST', '/api/salla/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SALLA_SIGNATURE' => hash_hmac('sha256', $body, self::SECRET),
        ], $body)->assertStatus($status);

        $this->store = $this->store->exists ? $this->store->fresh() : $this->store;
        \App\Support\RequestMemo::forget('subscription');
    }

    private function paid(array $overrides = []): array
    {
        return $overrides + [
            'subscription_id' => 'SUB-1',
            'item_type' => 'plan',
            'plan_type' => 'recurring',
            'plan_name' => 'basic',
            'plan_period' => '1',
            'start_date' => '2030-01-01T00:00:00.000000Z',
            'end_date' => '2030-02-01T00:00:00.000000Z',
            'store_type' => 'demo',
        ];
    }

    private function plan(string $key): SubscriptionPlan
    {
        return SubscriptionPlan::query()->where('metadata', 'like', '%"salla_plan_key":"'.$key.'"%')->sole();
    }

    private function subscription(): ?Subscription
    {
        return Subscription::where('organization_id', $this->organization->id)->first();
    }

    private function access(): SallaStoreAccess
    {
        return app(SallaStoreAccess::class);
    }

    private function widget(int $organizationId): WebWidget
    {
        return WebWidget::create([
            'organization_id' => $organizationId,
            'public_key' => WebWidget::generatePublicKey(),
            'is_enabled' => true,
            'allowed_domains' => ['shop.example.com'],
            'settings' => [],
        ]);
    }

    private function config(WebWidget $widget): string
    {
        return '/api/widget/'.$widget->public_key.'/config';
    }
}
