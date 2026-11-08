<?php

namespace App\Services\Salla;

use App\Models\SallaProduct;
use App\Models\SallaStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Copies a store's products from Salla. A product that is gone from Salla is removed only after
 * every page was read, so a failure half way cannot empty the store's catalog.
 */
class SallaProductSync
{
    private const PER_PAGE = 50;

    private const MAX_PAGES = 400;

    public function __construct(private readonly SallaProductCatalog $catalog)
    {
    }

    /** @return bool false when Salla did not answer every page */
    public function sync(SallaStore $store): bool
    {
        if (! $store->access_token) {
            return false;
        }

        $seen = [];

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $body = $this->page($store, $page);

            if ($body === null) {
                return false;
            }

            foreach ((array) ($body['data'] ?? []) as $product) {
                if (is_array($product) && ($saved = $this->save($store, $product))) {
                    $seen[] = $saved;
                }
            }

            if ($page >= (int) data_get($body, 'pagination.totalPages', 1)) {
                SallaProduct::query()
                    ->where('salla_store_id', $store->id)
                    ->whereNotIn('salla_product_id', $seen === [] ? [0] : $seen)
                    ->delete();
                $store->forceFill(['products_synced_at' => now()])->save();

                return true;
            }
        }

        Log::warning('salla.products.too_many_pages', ['merchant_id' => $store->merchant_id]);

        return false;
    }

    private function page(SallaStore $store, int $page): ?array
    {
        try {
            $response = Http::withToken((string) $store->access_token)
                ->acceptJson()
                ->timeout(30)
                ->get(rtrim((string) config('services.salla.api_url'), '/').'/products', [
                    'per_page' => self::PER_PAGE,
                    'page' => $page,
                ]);
        } catch (Throwable $exception) {
            Log::warning('salla.products.failed', ['merchant_id' => $store->merchant_id, 'error' => $exception::class]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('salla.products.failed', ['merchant_id' => $store->merchant_id, 'status' => $response->status()]);

            return null;
        }

        return (array) $response->json();
    }

    private function save(SallaStore $store, array $product): ?int
    {
        $id = (int) ($product['id'] ?? 0);
        $name = trim((string) ($product['name'] ?? ''));

        if ($id <= 0 || $name === '') {
            return null;
        }

        $category = collect((array) ($product['categories'] ?? []))->pluck('name')->filter()->first();
        $description = trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], ' ', (string) ($product['description'] ?? '')))));
        $options = $this->options((array) ($product['options'] ?? []));
        $price = $this->amount($product['taxed_price'] ?? null) ?? $this->amount($product['price'] ?? null) ?? 0.0;
        $regular = $this->amount($product['regular_price'] ?? null);

        SallaProduct::query()->updateOrCreate(
            ['salla_store_id' => $store->id, 'salla_product_id' => $id],
            [
                'name' => Str::limit($name, 250, ''),
                'type' => Str::limit((string) ($product['type'] ?? 'product'), 32, ''),
                'category' => $category ? Str::limit((string) $category, 250, '') : null,
                'description' => $description === '' ? null : Str::limit($description, 1500, ''),
                'price' => $price,
                'regular_price' => $regular !== null && $regular > $price ? $regular : null,
                'currency' => Str::limit((string) data_get($product, 'price.currency', 'SAR'), 8, ''),
                'is_available' => ($product['status'] ?? null) === 'sale' && (bool) ($product['is_available'] ?? false),
                'image_url' => $this->url($product['main_image'] ?? $product['thumbnail'] ?? null),
                'url' => $this->url($product['url'] ?? data_get($product, 'urls.customer')),
                'options' => $options,
                'search_text' => $this->catalog->searchText($name, (string) $category, $description, data_get($product, 'brand.name'), (array) ($product['tags'] ?? []), $options),
            ]
        );

        return $id;
    }

    /** Only what the card needs, not Salla's whole option. */
    private function options(array $options): array
    {
        return collect($options)->filter(fn ($option) => is_array($option))->map(fn (array $option) => [
            'id' => (int) ($option['id'] ?? 0),
            'name' => Str::limit(trim((string) ($option['name'] ?? '')), 80, ''),
            'type' => (string) ($option['type'] ?? ''),
            'values' => collect((array) ($option['values'] ?? []))->filter(fn ($value) => is_array($value))->map(fn (array $value) => [
                'id' => (int) ($value['id'] ?? 0),
                'name' => Str::limit(trim((string) ($value['name'] ?? '')), 80, ''),
                'out_of_stock' => (bool) ($value['is_out_of_stock'] ?? false),
            ])->values()->all(),
        ])->values()->all();
    }

    private function amount(mixed $value): ?float
    {
        $amount = is_array($value) ? ($value['amount'] ?? null) : $value;

        return is_numeric($amount) ? (float) $amount : null;
    }

    private function url(mixed $value): ?string
    {
        $url = is_string($value) ? trim($value) : '';

        return str_starts_with($url, 'https://') && strlen($url) <= 1000 ? $url : null;
    }
}
