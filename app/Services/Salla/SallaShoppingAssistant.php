<?php

namespace App\Services\Salla;

use App\Models\Chat;
use App\Models\Contact;
use App\Models\SallaProduct;
use App\Models\SallaStore;
use App\Models\WebWidget;

/**
 * What the assistant knows about a store's products when it answers a visitor, and the product
 * cards it asks for. Facts come from the store's catalog, so the model cannot invent a product.
 */
class SallaShoppingAssistant
{
    public const MAX_CARDS = 3;

    private const MAX_CATEGORIES = 20;

    public function __construct(
        private readonly SallaProductCatalog $catalog,
        private readonly SallaStoreFacts $facts,
    ) {
    }

    /** @return list<string> instructions for the assistant; empty when this is not a Salla store */
    public function instructions(Contact $contact, WebWidget $widget): array
    {
        $store = $this->store($widget);

        if (! $store) {
            return [];
        }

        $name = trim((string) data_get($widget->settings, 'assistant_name', ''));

        return array_merge(
            $name === '' ? [] : [sprintf('Your name in this store is "%s". Use it instead of any other name.', $name)],
            $this->storeLines($store),
            $this->productLines($store, $contact)
        );
    }

    /** @return list<string> */
    private function storeLines(SallaStore $store): array
    {
        return array_merge($this->facts->lines($store), [
            'When the visitor asks where their order is or wants to track it, write one short line and end your reply with the marker [[CARD:order]] to show a form. Do not ask for the order number or phone in the chat, and never guess an order\'s status.',
        ]);
    }

    /** @return list<string> */
    private function productLines(SallaStore $store, Contact $contact): array
    {
        if (! SallaProduct::query()->where('salla_store_id', $store->id)->exists()) {
            return [];
        }

        $products = $this->relevantProducts($store, $contact);
        $lines = ['This website is an online store. Its catalog is the only source for products, prices, sizes and availability.'];

        if ($products->isNotEmpty()) {
            $lines[] = 'Products that may match the conversation:';
            foreach ($products as $product) {
                $lines[] = $this->catalog->line($product);
            }
        } else {
            $lines[] = 'No product matches what the visitor asked. Say so plainly and ask what they are looking for. Store categories: '.$this->categories($store).'.';
        }

        $lines[] = 'Never mention a product, price, color, material or size that is not listed above. If a detail is not listed, say you do not have it.';
        $lines[] = 'To show products, write one short line and end your reply with one marker for each, for example [[PRODUCT:123]] [[PRODUCT:456]]. Show up to '.self::MAX_CARDS.' products when the visitor is browsing, and one when they asked about a specific product. Use only ids listed above.';
        $lines[] = 'The card under your message shows the photo, the price, the choices such as size, and the add to cart and pay buttons. The visitor presses them: never say you added anything to the cart yourself, and never ask for card details, because payment happens on the store\'s own page.';

        return $lines;
    }

    /** The cards for a reply, or null when none of its products can be shown. */
    public function cards(WebWidget $widget, array $ids): ?array
    {
        $store = $this->store($widget);

        if (! $store) {
            return null;
        }

        $items = $this->catalog->byIds($store, array_slice($ids, 0, self::MAX_CARDS))
            ->map(fn (SallaProduct $product) => $this->catalog->card($product))
            ->all();

        return $items === [] ? null : ['type' => 'products', 'items' => $items];
    }

    public function offersOrderTracking(WebWidget $widget): bool
    {
        return $this->store($widget) !== null;
    }

    private function store(WebWidget $widget): ?SallaStore
    {
        return SallaStore::query()->where('organization_id', $widget->organization_id)->first();
    }

    /** What the visitor asked just now, else just before, plus what they were last shown ("how much is it?"). */
    private function relevantProducts(SallaStore $store, Contact $contact)
    {
        $chats = Chat::query()->where('contact_id', $contact->id)->orderByDesc('id')->take(10)->get(['type', 'metadata']);
        $found = collect();

        foreach ($chats->where('type', 'inbound')->take(3) as $chat) {
            $found = $this->catalog->search($store, (string) data_get(json_decode((string) $chat->metadata, true), 'text.body', ''));

            if ($found->isNotEmpty()) {
                break;
            }
        }

        foreach ($chats->where('type', 'outbound') as $chat) {
            $keys = (array) data_get(json_decode((string) $chat->metadata, true), 'widget_action.keys', []);

            if ($keys !== []) {
                $found = $this->catalog->byIds($store, $keys)->merge($found)->unique('id')->values();
                break;
            }
        }

        return $found->take(8);
    }

    private function categories(SallaStore $store): string
    {
        return SallaProduct::query()
            ->where('salla_store_id', $store->id)
            ->where('is_available', true)
            ->whereNotNull('category')
            ->distinct()
            ->limit(self::MAX_CATEGORIES)
            ->pluck('category')
            ->implode(', ');
    }
}
