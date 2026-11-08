<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Organization;
use App\Models\SallaProduct;
use App\Models\SallaStore;
use App\Models\User;
use App\Models\WebWidget;
use App\Services\Salla\SallaProductCatalog;
use App\Services\Salla\SallaProductSync;
use App\Services\Salla\SallaShoppingAssistant;
use App\Services\WebWidget\WebWidgetAssistantContext;
use App\Services\WebWidget\WebWidgetMessageStore;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

/**
 * The assistant sells from a copy of the store's catalog: facts come from Salla, and cards can only
 * show products of the visitor's own store.
 */
class SallaProductsTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    private User $owner;

    private Organization $organization;

    private SallaStore $store;

    private WebWidget $widget;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array', 'services.salla.api_url' => 'https://salla.test/v2']);
        [$this->owner, $this->organization] = $this->createOwnerContext();
        $this->store = $this->newStore($this->organization, 1976195620);
        $this->widget = WebWidget::create([
            'organization_id' => $this->organization->id,
            'public_key' => WebWidget::generatePublicKey(),
            'is_enabled' => true,
            'allowed_domains' => ['demostore.salla.sa'],
        ]);
    }

    private function newStore(Organization $organization, int $merchant): SallaStore
    {
        return SallaStore::create([
            'merchant_id' => $merchant,
            'organization_id' => $organization->id,
            'status' => SallaStore::STATUS_INSTALLED,
            'access_token' => 'access',
            'refresh_token' => 'refresh',
            'installed_at' => now()->subDay(),
        ]);
    }

    private function product(int $id, array $overrides = []): array
    {
        return array_replace([
            'id' => $id,
            'name' => 'فستان',
            'type' => 'product',
            'status' => 'sale',
            'is_available' => true,
            'description' => '<p>قطن</p>',
            'price' => ['amount' => 174, 'currency' => 'SAR'],
            'taxed_price' => ['amount' => 174, 'currency' => 'SAR'],
            'regular_price' => ['amount' => 349, 'currency' => 'SAR'],
            'main_image' => 'https://cdn.example.com/a.jpg',
            'url' => 'https://demostore.salla.sa/dev/p'.$id,
            'categories' => [['name' => 'الفساتين']],
            'options' => [[
                'id' => 7,
                'name' => 'المقاس',
                'type' => 'radio',
                'values' => [
                    ['id' => 70, 'name' => 'S', 'is_out_of_stock' => false, 'ignored' => 'x'],
                    ['id' => 71, 'name' => 'M', 'is_out_of_stock' => true],
                ],
            ]],
        ], $overrides);
    }

    private function fakePages(array ...$pages): void
    {
        Http::fake(['salla.test/*' => function ($request) use ($pages) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $page = (int) ($query['page'] ?? 1);

            return Http::response(['data' => $pages[$page - 1] ?? [], 'pagination' => ['totalPages' => count($pages)]]);
        }]);
    }

    private function sync(array ...$pages): bool
    {
        $this->fakePages(...$pages);

        return app(SallaProductSync::class)->sync($this->store);
    }

    private function visitor(): Contact
    {
        return Contact::create([
            'organization_id' => $this->organization->id,
            'first_name' => 'Website visitor',
            'phone' => 'web:test-'.uniqid(),
            'created_by' => $this->owner->id,
        ]);
    }

    public function test_sync_copies_every_page_and_keeps_only_what_the_card_needs(): void
    {
        $this->assertTrue($this->sync([$this->product(1)], [$this->product(2, ['name' => 'جاكيت'])]));

        $product = SallaProduct::where('salla_product_id', 1)->firstOrFail();
        $this->assertSame(2, SallaProduct::where('salla_store_id', $this->store->id)->count());
        $this->assertSame(174.0, $product->price);
        $this->assertSame(349.0, $product->regular_price);
        $this->assertSame('قطن', $product->description);
        $this->assertSame('الفساتين', $product->category);
        $this->assertSame([['id' => 70, 'name' => 'S', 'out_of_stock' => false], ['id' => 71, 'name' => 'M', 'out_of_stock' => true]], $product->options[0]['values']);
        $this->assertNotNull($this->store->refresh()->products_synced_at);
    }

    public function test_sync_removes_a_deleted_product_and_marks_unavailable_ones(): void
    {
        $this->sync([$this->product(1), $this->product(2)]);
        Http::swap(new \Illuminate\Http\Client\Factory());
        $this->sync([$this->product(1, ['status' => 'out', 'is_available' => false])]);

        $this->assertFalse(SallaProduct::where('salla_product_id', 1)->value('is_available'));
        $this->assertNull(SallaProduct::where('salla_product_id', 2)->first());
    }

    public function test_a_failed_sync_keeps_the_catalog(): void
    {
        $this->sync([$this->product(1)]);
        $synced = $this->store->refresh()->products_synced_at;
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['salla.test/*' => Http::response([], 500)]);

        $this->assertFalse(app(SallaProductSync::class)->sync($this->store));
        $this->assertSame(1, SallaProduct::where('salla_store_id', $this->store->id)->count());
        $this->assertEquals($synced, $this->store->refresh()->products_synced_at);
    }

    public function test_a_failure_on_a_later_page_removes_nothing(): void
    {
        $this->sync([$this->product(1), $this->product(2)]);
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['salla.test/*' => Http::sequence()
            ->push(['data' => [$this->product(1)], 'pagination' => ['totalPages' => 2]])
            ->push([], 500)]);

        $this->assertFalse(app(SallaProductSync::class)->sync($this->store));
        $this->assertSame(2, SallaProduct::where('salla_store_id', $this->store->id)->count());
    }

    public function test_the_command_syncs_new_and_stale_stores_only(): void
    {
        $this->fakePages([$this->product(1)]);
        $this->store->forceFill(['products_synced_at' => null])->save();

        Artisan::call('salla:sync-products');
        $this->assertSame(1, SallaProduct::where('salla_store_id', $this->store->id)->count());

        $recorded = count(Http::recorded());
        Artisan::call('salla:sync-products');
        $this->assertSame($recorded, count(Http::recorded()));
    }

    public function test_search_understands_arabic_spelling_and_ignores_filler_words(): void
    {
        $this->sync([
            $this->product(1),
            $this->product(2, ['name' => 'جاكيت جلد', 'categories' => [['name' => 'الجاكيتات']], 'options' => []]),
            $this->product(3, ['name' => 'فستان سهرة', 'status' => 'out', 'is_available' => false]),
        ]);
        $catalog = app(SallaProductCatalog::class);

        $this->assertSame([2], $catalog->search($this->store, 'عندكم جاكيت؟')->pluck('salla_product_id')->all());
        $this->assertSame([1], $catalog->search($this->store, 'أبغى فُستان')->pluck('salla_product_id')->all());
        $this->assertSame([1], $catalog->search($this->store, 'الفساتين')->pluck('salla_product_id')->all());
        $this->assertSame([], $catalog->search($this->store, 'كم السعر؟')->all());
        $this->assertSame([], $catalog->search($this->store, '%')->all());
    }

    public function test_products_of_another_store_never_appear(): void
    {
        $this->sync([$this->product(1)]);
        [, $otherOrganization] = $this->createOwnerContext();
        $other = $this->newStore($otherOrganization, 555);
        SallaProduct::create(['salla_store_id' => $other->id, 'salla_product_id' => 9, 'name' => 'فستان آخر', 'search_text' => 'فستان اخر']);

        $this->assertSame([1], app(SallaProductCatalog::class)->search($this->store, 'فستان')->pluck('salla_product_id')->all());
        $this->assertNull(app(SallaShoppingAssistant::class)->cards($this->widget, ['9']));
    }

    public function test_a_marker_becomes_cards_and_leaves_the_text(): void
    {
        $reply = WebWidgetAssistantContext::extractActions('هذا الخيار [[PRODUCT:1]] [[ product : 2 ]][[PRODUCT:1]] [[PRODUCT:3]] [[PRODUCT:4]]');

        $this->assertSame('هذا الخيار', $reply['text']);
        $this->assertSame(['type' => 'products', 'keys' => ['1', '2', '3']], $reply['action']);
    }

    public function test_cards_use_the_catalog_and_skip_an_invented_id(): void
    {
        $this->sync([$this->product(1)]);

        $action = app(WebWidgetAssistantContext::class)->resolveAction($this->widget, ['type' => 'products', 'keys' => ['999', '1']]);

        $this->assertSame('products', $action['type']);
        $this->assertCount(1, $action['items']);
        $this->assertSame(174.0, $action['items'][0]['price']);
        $this->assertSame(7, $action['items'][0]['options'][0]['id']);
        $this->assertTrue($action['items'][0]['can_add']);
        $this->assertNull(app(WebWidgetAssistantContext::class)->resolveAction($this->widget, ['type' => 'products', 'keys' => ['999']]));
    }

    public function test_products_a_card_cannot_buy_send_the_visitor_to_the_product_page(): void
    {
        $this->sync([
            $this->product(1, ['type' => 'donating', 'options' => []]),
            $this->product(2, ['options' => [['id' => 8, 'name' => 'ملاحظة', 'type' => 'text', 'values' => []]]]),
            $this->product(3, ['options' => [['id' => 9, 'name' => 'إضافات', 'type' => 'checkbox', 'values' => [['id' => 90, 'name' => 'x']]]]]),
            $this->product(4, ['status' => 'out', 'is_available' => false, 'options' => []]),
            $this->product(5, ['options' => []]),
        ]);

        $cards = collect(app(SallaShoppingAssistant::class)->cards($this->widget, ['1', '2', '3'])['items'])
            ->merge(app(SallaShoppingAssistant::class)->cards($this->widget, ['4', '5'])['items'])
            ->pluck('can_add', 'id');

        $this->assertSame([1 => false, 2 => false, 3 => false, 4 => false, 5 => true], $cards->all());
    }

    public function test_the_assistant_is_told_the_matching_products_and_not_to_invent(): void
    {
        $this->sync([$this->product(1), $this->product(2, ['name' => 'جاكيت', 'categories' => [], 'description' => '', 'options' => []])]);
        $contact = $this->visitor();
        app(WebWidgetMessageStore::class)->storeText($contact, 'عندكم فساتين؟', 'inbound');

        $text = implode("\n", app(SallaShoppingAssistant::class)->instructions($contact, $this->widget));

        $this->assertStringContainsString('id 1 | فستان | 174 SAR | on sale, was 349 | category: الفساتين | المقاس: S', $text);
        $this->assertStringNotContainsString(', M', $text);
        $this->assertStringNotContainsString('جاكيت', $text);
        $this->assertStringContainsString('never ask for card details', $text);
    }

    public function test_a_follow_up_keeps_the_product_the_visitor_was_shown(): void
    {
        $this->sync([$this->product(1)]);
        $contact = $this->visitor();
        app(WebWidgetMessageStore::class)->storeText($contact, 'شوف', 'outbound', null, ['widget_action' => ['type' => 'products', 'keys' => ['1']]]);
        app(WebWidgetMessageStore::class)->storeText($contact, 'كم سعره؟', 'inbound');

        $this->assertStringContainsString('id 1 |', implode("\n", app(SallaShoppingAssistant::class)->instructions($contact, $this->widget)));
    }

    public function test_with_no_match_the_assistant_says_so_and_gets_the_categories(): void
    {
        $this->sync([$this->product(1)]);
        $contact = $this->visitor();
        app(WebWidgetMessageStore::class)->storeText($contact, 'عندكم سيارات؟', 'inbound');

        $text = implode("\n", app(SallaShoppingAssistant::class)->instructions($contact, $this->widget));

        $this->assertStringContainsString('No product matches', $text);
        $this->assertStringContainsString('الفساتين', $text);
        $this->assertStringNotContainsString('id 1 |', $text);
    }

    public function test_an_organization_without_a_store_gets_no_shopping_instructions_or_cards(): void
    {
        [, $plain] = $this->createOwnerContext();
        $widget = WebWidget::create(['organization_id' => $plain->id, 'public_key' => WebWidget::generatePublicKey(), 'is_enabled' => true, 'allowed_domains' => ['a.example.com']]);

        $this->assertSame([], app(SallaShoppingAssistant::class)->instructions($this->visitor(), $widget));
        $this->assertNull(app(SallaShoppingAssistant::class)->cards($widget, ['1']));
    }

    public function test_purge_deletes_the_store_products(): void
    {
        $this->sync([$this->product(1)]);
        $this->store->forceFill(['status' => SallaStore::STATUS_UNINSTALLED, 'purge_after' => now()->subDay()])->save();

        Artisan::call('salla:purge');

        $this->assertSame(0, SallaProduct::where('salla_store_id', $this->store->id)->count());
        $this->assertNull($this->store->refresh()->products_synced_at);
    }
}
