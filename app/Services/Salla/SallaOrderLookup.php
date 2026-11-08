<?php

namespace App\Services\Salla;

use App\Models\SallaStore;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Tells a shopper where their order is. An order number alone can be guessed, so the phone number
 * on the order must match too, and the answer holds only the status and tracking.
 */
class SallaOrderLookup
{
    private const PHONE_DIGITS_COMPARED = 9;

    /**
     * @return ?array{number:string, status:string, tracking_url:?string} null when no order matches both
     * @throws RuntimeException when Salla did not answer
     */
    public function find(SallaStore $store, string $orderNumber, string $phone): ?array
    {
        $number = $this->digits($orderNumber);
        $phoneDigits = $this->digits($phone);

        if ($number === '' || strlen($phoneDigits) < self::PHONE_DIGITS_COMPARED) {
            return null;
        }

        $order = collect((array) $this->get($store, 'orders', ['reference_id' => $number, 'per_page' => 1])->json('data', []))
            ->first(fn ($item) => is_array($item) && (string) ($item['reference_id'] ?? '') === $number);

        if (! $order || ! $this->samePhone($phoneDigits, $this->digits((string) data_get($order, 'customer.mobile', '')))) {
            return null;
        }

        return [
            'number' => $number,
            'status' => trim((string) (data_get($order, 'status.customized.name') ?: data_get($order, 'status.name', ''))),
            'tracking_url' => $this->trackingUrl($store, (int) ($order['id'] ?? 0)),
        ];
    }

    /** Tracking is a bonus: the status is still worth showing when it cannot be read. */
    private function trackingUrl(SallaStore $store, int $orderId): ?string
    {
        try {
            $url = (string) $this->get($store, 'shipments', ['order_id' => $orderId])->json('data.0.tracking_link', '');
        } catch (RuntimeException) {
            return null;
        }

        return str_starts_with($url, 'https://') ? $url : null;
    }

    private function get(SallaStore $store, string $path, array $query): Response
    {
        $response = Http::withToken((string) $store->access_token)
            ->acceptJson()
            ->timeout(15)
            ->get(rtrim((string) config('services.salla.api_url'), '/').'/'.$path, $query);

        if (! $response->successful()) {
            throw new RuntimeException('salla_unavailable');
        }

        return $response;
    }

    private function samePhone(string $given, string $onOrder): bool
    {
        return strlen($onOrder) >= self::PHONE_DIGITS_COMPARED
            && hash_equals(substr($onOrder, -self::PHONE_DIGITS_COMPARED), substr($given, -self::PHONE_DIGITS_COMPARED));
    }

    private function digits(string $value): string
    {
        $western = strtr($value, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);

        return (string) preg_replace('/\D+/', '', $western);
    }
}
