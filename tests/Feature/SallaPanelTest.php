<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SallaProduct;
use App\Models\SallaStore;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WebWidget;
use App\Services\Salla\SallaPanelAuth;
use App\Services\Salla\SallaPlans;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

/**
 * The panel opens inside Salla's dashboard. Salla vouches for the merchant, and a panel token can
 * only ever read its own store.
 */
class SallaPanelTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    private const MERCHANT = 1976195620;

    private User $owner;

    private Organization $organization;

    private SallaStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'services.salla.app_id' => '896353331',
            'services.salla.introspect_url' => 'https://salla.test/introspect',
        ]);
        SubscriptionPlan::query()->where('metadata', 'like', '%"salla_plan_key"%')->forceDelete();
        app(SallaPlans::class)->setup();
        [$this->owner, $this->organization] = $this->createOwnerContext();
        $this->store = $this->newStore($this->organization, self::MERCHANT, 'متجر تجريبي');
    }

    private function newStore(Organization $organization, int $merchant, string $name): SallaStore
    {
        return SallaStore::create([
            'merchant_id' => $merchant,
            'organization_id' => $organization->id,
            'store_name' => $name,
            'status' => SallaStore::STATUS_INSTALLED,
            'access_token' => 'secret-access',
            'refresh_token' => 'secret-refresh',
            'installed_at' => now()->subDay(),
        ]);
    }

    private function fakeSalla(int $merchant = self::MERCHANT, bool $success = true, int $status = 200): void
    {
        Http::fake(['salla.test/*' => Http::response([
            'status' => $status,
            'success' => $success,
            'data' => ['merchant_id' => $merchant, 'user_id' => 7, 'exp' => now()->addHour()->toIso8601String()],
        ], $status)]);
    }

    private function signIn(): string
    {
        $this->fakeSalla();

        return (string) $this->postJson('/api/salla/panel/session', ['token' => 'em_tok_x'])->assertOk()->json('token');
    }

    private function overview(string $token): \Illuminate\Testing\TestResponse
    {
        return $this->getJson('/api/salla/panel/overview', ['Authorization' => 'Bearer '.$token]);
    }

    private function subscribe(string $until = '+1 month'): void
    {
        Subscription::query()->where('organization_id', $this->organization->id)->delete();
        Subscription::create([
            'organization_id' => $this->organization->id,
            'plan_id' => app(SallaPlans::class)->forName(null, self::MERCHANT)->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->modify($until),
        ]);
    }

    public function test_the_page_opens_only_inside_salla_in_the_merchant_language(): void
    {
        $response = $this->get('/api/salla/panel?lang=ar&theme=dark')->assertOk();

        $this->assertStringContainsString('frame-ancestors https://s.salla.sa', (string) $response->headers->get('Content-Security-Policy'));
        $response->assertSee('lang="ar"', false)->assertSee('dir="rtl"', false)->assertSee('data-theme="dark"', false);

        $this->get('/api/salla/panel?lang=en&theme=%3Cx%3E')->assertSee('dir="ltr"', false)->assertSee('data-theme="light"', false);
    }

    public function test_the_token_is_checked_with_salla_under_our_app_id(): void
    {
        $this->signIn();

        Http::assertSent(fn ($request) => $request->url() === 'https://salla.test/introspect'
            && $request->hasHeader('S-Source', '896353331')
            && $request['token'] === 'em_tok_x');
    }

    public function test_salla_refusing_the_token_or_an_unknown_store_gets_nothing(): void
    {
        $this->fakeSalla(self::MERCHANT, false, 401);
        $this->postJson('/api/salla/panel/session', ['token' => 'bad'])->assertUnauthorized()->assertExactJson(['error' => 'not_allowed']);

        Http::swap(new \Illuminate\Http\Client\Factory());
        $this->fakeSalla(12345);
        $this->postJson('/api/salla/panel/session', ['token' => 'other'])->assertUnauthorized();

        Http::swap(new \Illuminate\Http\Client\Factory());
        $this->fakeSalla();
        $this->postJson('/api/salla/panel/session', [])->assertUnauthorized();
    }

    public function test_salla_being_down_refuses_without_an_error_page(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('down'));

        $this->postJson('/api/salla/panel/session', ['token' => 'x'])->assertUnauthorized();
    }

    public function test_no_app_id_refuses_everything(): void
    {
        config(['services.salla.app_id' => '']);
        $this->fakeSalla();

        $this->postJson('/api/salla/panel/session', ['token' => 'x'])->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_an_uninstalled_store_cannot_sign_in_or_use_an_old_token(): void
    {
        $token = $this->signIn();
        $this->store->forceFill(['status' => SallaStore::STATUS_UNINSTALLED])->save();

        $this->overview($token)->assertUnauthorized();
        $this->postJson('/api/salla/panel/session', ['token' => 'em_tok_x'])->assertUnauthorized();
    }

    public function test_expired_forged_or_missing_panel_tokens_are_refused(): void
    {
        $this->overview(Crypt::encryptString(json_encode(['store' => $this->store->id, 'exp' => now()->subMinute()->timestamp])))->assertUnauthorized();
        $this->overview(base64_encode(json_encode(['store' => $this->store->id, 'exp' => now()->addHour()->timestamp])))->assertUnauthorized();
        $this->overview(Crypt::encryptString('not json'))->assertUnauthorized();
        $this->getJson('/api/salla/panel/overview')->assertUnauthorized();
    }

    public function test_a_panel_token_lasts_two_hours(): void
    {
        $token = app(SallaPanelAuth::class)->issue($this->store);

        $this->travel(119)->minutes();
        $this->assertNotNull(app(SallaPanelAuth::class)->storeFromPanelToken($token));

        $this->travel(2)->minutes();
        $this->assertNull(app(SallaPanelAuth::class)->storeFromPanelToken($token));
    }

    public function test_the_overview_shows_this_store_and_never_its_secrets(): void
    {
        $this->subscribe();
        WebWidget::create(['organization_id' => $this->organization->id, 'public_key' => WebWidget::generatePublicKey(), 'is_enabled' => true, 'allowed_domains' => []]);
        SallaProduct::create(['salla_store_id' => $this->store->id, 'salla_product_id' => 1, 'name' => 'فستان']);
        $this->store->forceFill(['products_synced_at' => now()])->save();

        [, $other] = $this->createOwnerContext();
        $otherStore = $this->newStore($other, 555, 'متجر آخر');
        SallaProduct::create(['salla_store_id' => $otherStore->id, 'salla_product_id' => 2, 'name' => 'جاكيت']);

        $response = $this->overview($this->signIn())
            ->assertOk()
            ->assertJsonPath('store_name', 'متجر تجريبي')
            ->assertJsonPath('assistant.running', true)
            ->assertJsonPath('products.count', 1)
            ->assertJsonPath('replies.limit', 1000)
            ->assertJsonPath('replies.used', 0);

        $this->assertStringNotContainsString('secret-', $response->getContent());
        $this->assertStringNotContainsString('متجر آخر', $response->getContent());
    }

    public function test_the_overview_says_why_mochi_is_off(): void
    {
        $token = $this->signIn();
        WebWidget::create(['organization_id' => $this->organization->id, 'public_key' => WebWidget::generatePublicKey(), 'is_enabled' => true, 'allowed_domains' => []]);

        $this->overview($token)->assertOk()->assertJsonPath('assistant.running', false)->assertJsonPath('assistant.has_subscription', false)->assertJsonPath('subscription', null);

        $this->subscribe();
        WebWidget::query()->where('organization_id', $this->organization->id)->update(['is_enabled' => false]);

        $this->overview($token)->assertOk()->assertJsonPath('assistant.running', false)->assertJsonPath('assistant.widget_enabled', false)->assertJsonPath('assistant.has_subscription', true);
    }
}
