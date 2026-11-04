<?php

namespace App\Services\Salla;

use App\Models\SallaStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Salla refresh tokens work once: using one twice revokes the tokens and forces a reinstall.
 * Every refresh therefore runs under the store's lock, re-reads the store first and saves the
 * new pair immediately.
 */
class SallaTokenManager
{
    public const REFRESH_BEFORE_DAYS = 3;

    /** Returns true when the store ends up with a token that is not due for refresh. */
    public function refreshIfDue(int $merchantId): bool
    {
        return (bool) Cache::lock(SallaStore::lockKey($merchantId), 60)->block(10, function () use ($merchantId) {
            $store = SallaStore::query()->where('merchant_id', $merchantId)->first();

            if (! $store || $store->status !== SallaStore::STATUS_INSTALLED || ! $store->refresh_token) {
                return false;
            }

            if ($store->token_expires_at && $store->token_expires_at->gt(now()->addDays(self::REFRESH_BEFORE_DAYS))) {
                return true;
            }

            return $this->refresh($store);
        });
    }

    private function refresh(SallaStore $store): bool
    {
        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(20)
                ->post((string) config('services.salla.token_url'), [
                    'grant_type' => 'refresh_token',
                    'client_id' => (string) config('services.salla.client_id'),
                    'client_secret' => (string) config('services.salla.client_secret'),
                    'refresh_token' => (string) $store->refresh_token,
                ]);
        } catch (Throwable $exception) {
            // Salla may have used the token even though the answer was lost, so this is logged for follow-up.
            Log::error('salla.token_refresh.no_response', ['merchant_id' => $store->merchant_id, 'error' => $exception::class]);

            return false;
        }

        $accessToken = (string) $response->json('access_token', '');
        $refreshToken = (string) $response->json('refresh_token', '');

        if ($response->successful() && $accessToken !== '' && $refreshToken !== '') {
            $store->forceFill([
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_expires_at' => now()->addSeconds(max(60, (int) $response->json('expires_in', 1209600))),
                'scope' => $response->json('scope', $store->scope),
            ])->save();

            return true;
        }

        Log::error('salla.token_refresh.refused', ['merchant_id' => $store->merchant_id, 'status' => $response->status()]);

        // A client error means Salla rejected this token. Trying it again could revoke the store's access.
        if ($response->clientError()) {
            $store->forceFill(['status' => SallaStore::STATUS_REAUTH_REQUIRED])->save();
        }

        return false;
    }
}
