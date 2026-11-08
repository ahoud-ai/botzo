<?php

namespace App\Services\Salla;

use App\Models\SallaStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Shipping and offers the assistant may mention, copied from Salla. Coupons are left out on
 * purpose: a code can be private to some customers, and a chat would give it to everyone.
 */
class SallaStoreFacts
{
    private const MAX_ITEMS = 30;

    /** A list Salla did not return keeps its last copy. */
    public function sync(SallaStore $store): void
    {
        $facts = (array) $store->store_facts;
        $zones = $this->list($store, 'shipping/zones');
        $offers = $this->list($store, 'specialoffers');

        if ($zones !== null) {
            $facts['shipping'] = array_map(fn (array $zone) => [
                'country' => $this->text(data_get($zone, 'country.name')),
                'city' => $this->text(data_get($zone, 'city.name')),
                'fees' => $this->text(data_get($zone, 'fees.amount')),
                'currency' => $this->text(data_get($zone, 'fees.currency')),
                'duration' => $this->text($zone['duration'] ?? null),
                'cash_on_delivery' => (bool) data_get($zone, 'cash_on_delivery.status', false),
            ], $zones);
        }

        if ($offers !== null) {
            $active = array_filter($offers, fn (array $offer) => ($offer['status'] ?? null) === 'active');
            $facts['offers'] = array_values(array_map(fn (array $offer) => [
                'name' => $this->text($offer['name'] ?? null),
                'message' => $this->text($offer['message'] ?? null),
                'ends' => $this->text($offer['expiry_date'] ?? null),
            ], $active));
        }

        $store->forceFill(['store_facts' => $facts])->save();
    }

    /** @return list<string> */
    public function lines(SallaStore $store): array
    {
        $facts = (array) $store->store_facts;
        $lines = [];

        if (! empty($facts['shipping'])) {
            $lines[] = 'Shipping options in this store:';
            foreach ($facts['shipping'] as $zone) {
                $lines[] = sprintf(
                    '- %s, %s: %s %s, delivery %s, cash on delivery %s',
                    $zone['country'],
                    $zone['city'],
                    $zone['fees'],
                    $zone['currency'],
                    $zone['duration'] ?: 'time not listed',
                    $zone['cash_on_delivery'] ? 'available' : 'not available'
                );
            }
        }

        if (! empty($facts['offers'])) {
            $lines[] = 'Current offers:';
            foreach ($facts['offers'] as $offer) {
                $lines[] = '- '.trim($offer['name'].' '.$offer['message']).($offer['ends'] ? ' (until '.$offer['ends'].')' : '');
            }
        }

        return $lines;
    }

    private function list(SallaStore $store, string $path): ?array
    {
        try {
            $response = Http::withToken((string) $store->access_token)
                ->acceptJson()
                ->timeout(20)
                ->get(rtrim((string) config('services.salla.api_url'), '/').'/'.$path);
        } catch (Throwable $exception) {
            Log::warning('salla.facts.failed', ['merchant_id' => $store->merchant_id, 'path' => $path, 'error' => $exception::class]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('salla.facts.failed', ['merchant_id' => $store->merchant_id, 'path' => $path, 'status' => $response->status()]);

            return null;
        }

        return array_slice(array_values(array_filter((array) $response->json('data', []), 'is_array')), 0, self::MAX_ITEMS);
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? Str::limit(trim((string) $value), 200, '') : '';
    }
}
