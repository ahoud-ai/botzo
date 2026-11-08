<?php

namespace App\Services\Salla;

use App\Models\SallaProduct;
use App\Models\SallaStore;
use Illuminate\Support\Collection;

/**
 * Finds a store's products for the assistant, and describes them for the widget's product cards.
 */
class SallaProductCatalog
{
    private const CANDIDATES = 300;

    /** Product types a shopper can buy with one button. The rest (donations, bookings) need the product page. */
    private const CHAT_BUYABLE_TYPES = ['product', 'food', 'digital', 'service', 'codes'];

    /** Words that say nothing about the product, already normalized. */
    private const STOP_WORDS = [
        'ابغي', 'ابي', 'اريد', 'عايز', 'عايزه', 'عندك', 'عندكم', 'عندكو', 'في', 'فيه', 'هل', 'كم', 'سعر', 'سعره', 'سعرها', 'بكم', 'وش', 'ايش', 'اي', 'ايه',
        'من', 'علي', 'الي', 'لو', 'ممكن', 'ابحث', 'دور', 'هذا', 'هذي', 'هاذا', 'ده', 'دي', 'لي', 'لك', 'مع', 'او', 'شي', 'شيء', 'منتج', 'منتجات',
        'the', 'a', 'an', 'do', 'you', 'have', 'i', 'want', 'need', 'how', 'much', 'is', 'for', 'of', 'price', 'product', 'products',
    ];

    public function searchText(string $name, string $category, string $description, mixed $brand, array $tags, array $options): string
    {
        $tagNames = collect($tags)->map(fn ($tag) => is_array($tag) ? ($tag['name'] ?? '') : $tag)->filter(fn ($tag) => is_string($tag));
        $values = collect($options)->flatMap(fn ($option) => array_merge([$option['name'] ?? ''], array_column((array) ($option['values'] ?? []), 'name')));

        return $this->normalize(implode(' ', array_merge(
            [$name, $category, is_string($brand) ? $brand : ''],
            $tagNames->all(),
            $values->all(),
            [mb_substr($description, 0, 1000)]
        )));
    }

    /** Available products matching the text, best first. */
    public function search(SallaStore $store, string $text, int $limit = 5): Collection
    {
        $terms = $this->terms($text);

        if ($terms === []) {
            return collect();
        }

        $products = SallaProduct::query()
            ->where('salla_store_id', $store->id)
            ->where('is_available', true)
            ->where(function ($query) use ($terms) {
                foreach ($terms as $term) {
                    $query->orWhere('search_text', 'like', '%'.addcslashes($term, '%_\\').'%');
                }
            })
            ->limit(self::CANDIDATES)
            ->get();

        return $products
            ->map(fn (SallaProduct $product) => ['product' => $product, 'score' => $this->score($product, $terms)])
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('product')
            ->values();
    }

    public function byIds(SallaStore $store, array $ids): Collection
    {
        $products = SallaProduct::query()
            ->where('salla_store_id', $store->id)
            ->whereIn('salla_product_id', array_map('intval', $ids))
            ->get()
            ->keyBy('salla_product_id');

        return collect($ids)->map(fn ($id) => $products->get((int) $id))->filter()->values();
    }

    /** What the widget's product card shows. */
    public function card(SallaProduct $product): array
    {
        $options = collect((array) $product->options)->map(fn (array $option) => [
            'id' => (int) $option['id'],
            'name' => (string) $option['name'],
            'values' => array_values((array) ($option['values'] ?? [])),
        ])->all();

        return [
            'id' => $product->salla_product_id,
            'name' => $product->name,
            'price' => $product->price,
            'regular_price' => $product->regular_price,
            'currency' => $product->currency,
            'image' => $product->image_url,
            'url' => $product->url,
            'available' => $product->is_available,
            'options' => $options,
            'can_add' => $product->is_available && $this->canAddFromChat($product),
        ];
    }

    /** One line about a product for the assistant. */
    public function line(SallaProduct $product): string
    {
        $parts = [
            'id '.$product->salla_product_id,
            $product->name,
            rtrim(rtrim(number_format($product->price, 2, '.', ''), '0'), '.').' '.$product->currency,
        ];

        if ($product->regular_price) {
            $parts[] = 'on sale, was '.rtrim(rtrim(number_format($product->regular_price, 2, '.', ''), '0'), '.');
        }
        if ($product->category) {
            $parts[] = 'category: '.$product->category;
        }
        foreach ((array) $product->options as $option) {
            $values = collect((array) ($option['values'] ?? []))->reject(fn ($value) => $value['out_of_stock'] ?? false)->pluck('name')->implode(', ');
            if ($values !== '') {
                $parts[] = $option['name'].': '.$values;
            }
        }
        if ($product->description) {
            $parts[] = 'details: '.mb_substr($product->description, 0, 200);
        }

        return '- '.implode(' | ', $parts);
    }

    public function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $text);
        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ي']);

        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text));
    }

    /** @return list<string> */
    private function terms(string $text): array
    {
        $terms = [];

        foreach (explode(' ', $this->normalize($text)) as $word) {
            $word = mb_strlen($word) > 4 && str_starts_with($word, 'ال') ? mb_substr($word, 2) : $word;

            if (mb_strlen($word) >= 2 && ! in_array($word, self::STOP_WORDS, true)) {
                $terms[$word] = true;
            }
        }

        return array_slice(array_keys($terms), 0, 8);
    }

    private function score(SallaProduct $product, array $terms): int
    {
        $name = $this->normalize($product->name);
        $score = 0;

        foreach ($terms as $term) {
            $score += str_contains($name, $term) ? 3 : (str_contains((string) $product->search_text, $term) ? 1 : 0);
        }

        return $score;
    }

    /** One choice per option can be made in the card. Anything else needs the product page. */
    private function canAddFromChat(SallaProduct $product): bool
    {
        if (! in_array($product->type, self::CHAT_BUYABLE_TYPES, true)) {
            return false;
        }

        foreach ((array) $product->options as $option) {
            if (($option['type'] ?? '') === 'checkbox' || empty($option['values'])) {
                return false;
            }
        }

        return true;
    }
}
