<?php

namespace App\Services\Salla;

use App\Models\SallaStore;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sign-in for the panel inside Salla's dashboard. Salla's short token is checked with Salla, then
 * the page gets an encrypted token of its own. No cookie is used, so the rest of Botzo keeps its
 * stricter cookie and frame settings.
 */
class SallaPanelAuth
{
    private const TOKEN_MINUTES = 120;

    /** The store Salla says this token belongs to, or null when Salla refuses it or the store has no app. */
    public function storeFromSallaToken(string $token): ?SallaStore
    {
        $appId = (string) config('services.salla.app_id');

        if ($appId === '' || $token === '') {
            return null;
        }

        try {
            $response = Http::withHeaders(['S-Source' => $appId])
                ->acceptJson()
                ->timeout(10)
                ->post((string) config('services.salla.introspect_url'), ['token' => $token]);
        } catch (Throwable $exception) {
            Log::warning('salla.panel.introspect_failed', ['error' => $exception::class]);

            return null;
        }

        $merchantId = (int) $response->json('data.merchant_id', 0);

        if (! $response->successful() || $response->json('success') !== true || $merchantId <= 0) {
            Log::warning('salla.panel.introspect_refused', ['status' => $response->status()]);

            return null;
        }

        return SallaStore::query()
            ->where('merchant_id', $merchantId)
            ->where('status', '!=', SallaStore::STATUS_UNINSTALLED)
            ->first();
    }

    public function issue(SallaStore $store): string
    {
        return Crypt::encryptString(json_encode(['store' => $store->id, 'exp' => now()->addMinutes(self::TOKEN_MINUTES)->timestamp]));
    }

    public function storeFromPanelToken(string $token): ?SallaStore
    {
        try {
            $data = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($data) || (int) ($data['exp'] ?? 0) < now()->timestamp) {
            return null;
        }

        return SallaStore::query()
            ->whereKey((int) ($data['store'] ?? 0))
            ->where('status', '!=', SallaStore::STATUS_UNINSTALLED)
            ->first();
    }
}
