<?php

namespace App\Services\Salla;

use App\Exceptions\SallaAuthorizeWithoutTokens;
use App\Models\Organization;
use App\Models\SallaStore;
use App\Models\User;
use App\Services\OrganizationService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Keeps a Salla store and its Botzo account in step with install and uninstall events.
 * Callers hold the store's lock (SallaStore::lockKey).
 */
class SallaStoreInstaller
{
    public const RETENTION_DAYS = 30;

    public function __construct(private readonly OrganizationService $organizations)
    {
    }

    /**
     * Handles app.store.authorize. Returns null when the event is older than the store's last
     * uninstall: Salla retries failed deliveries for about 15 minutes, so tokens can arrive late.
     */
    public function authorize(int $merchantId, array $data, CarbonInterface $occurredAt): ?SallaStore
    {
        $accessToken = trim((string) ($data['access_token'] ?? ''));
        $refreshToken = trim((string) ($data['refresh_token'] ?? ''));

        if ($accessToken === '' || $refreshToken === '') {
            throw new SallaAuthorizeWithoutTokens();
        }

        $store = SallaStore::query()->where('merchant_id', $merchantId)->first();

        if ($store?->uninstalled_at && $occurredAt->lte($store->uninstalled_at)) {
            return null;
        }

        $details = $this->fetchDetails($accessToken, $merchantId);

        $store = DB::transaction(function () use ($store, $merchantId, $accessToken, $refreshToken, $data, $occurredAt, $details) {
            $store ??= new SallaStore([
                'merchant_id' => $merchantId,
                'organization_id' => $this->createAccount($merchantId, $details['store_name'] ?? null)->id,
            ]);

            $store->fill([
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_expires_at' => $this->expiry($data['expires'] ?? null),
                'scope' => isset($data['scope']) ? (string) $data['scope'] : $store->scope,
                'status' => SallaStore::STATUS_INSTALLED,
                'installed_at' => $store->status === SallaStore::STATUS_INSTALLED && $store->installed_at ? $store->installed_at : $occurredAt,
                'uninstalled_at' => null,
                'purge_after' => null,
            ] + $details);

            $store->save();

            return $store;
        });

        if (isset($details['store_name'])) {
            $this->renameAccount($store, $details['store_name']);
        }

        return $store;
    }

    /**
     * Handles app.uninstalled. Tokens go at once; the account's conversations go after the retention period.
     * Returns false for a late retry of an uninstall that a newer install has already replaced.
     */
    public function uninstall(int $merchantId, CarbonInterface $occurredAt): bool
    {
        $store = SallaStore::query()->where('merchant_id', $merchantId)->first();

        if (! $store || ($store->installed_at && $occurredAt->lt($store->installed_at))) {
            return false;
        }

        $store->forceFill([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'status' => SallaStore::STATUS_UNINSTALLED,
            'uninstalled_at' => $occurredAt,
            'purge_after' => now()->addDays(self::RETENTION_DAYS),
        ])->save();

        return true;
    }

    /** Fills in store details that could not be read during install. */
    public function backfillDetails(SallaStore $store): void
    {
        $details = $this->fetchDetails((string) $store->access_token, $store->merchant_id);

        if ($details === []) {
            return;
        }

        $store->fill($details)->save();

        if (isset($details['store_name'])) {
            $this->renameAccount($store, $details['store_name']);
        }
    }

    /**
     * A new user and organization for every store. Accounts are never joined by email: Salla's
     * email is not proof of owning a Botzo account, so the login address is a private placeholder
     * and the store's email stays on salla_stores.
     */
    private function createAccount(int $merchantId, ?string $storeName): Organization
    {
        $name = $storeName ?: 'Salla store '.$merchantId;

        $user = new User([
            'first_name' => Str::limit($name, 120, ''),
            'email' => 'salla-'.$merchantId.'@merchants.botzo.invalid',
            'password' => Hash::make(Str::random(64)),
            'role' => 'user',
        ]);
        $user->email_verified_at = now();
        $user->save();

        return $this->organizations->createOwnedMainOrganization($user, $name, (int) $user->id);
    }

    private function renameAccount(SallaStore $store, string $name): void
    {
        Organization::query()->whereKey($store->organization_id)->update(['name' => $name]);
    }

    /** Store details are a convenience: a failed lookup must not lose the tokens, so it only logs. */
    private function fetchDetails(string $accessToken, int $merchantId): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(10)
                ->get((string) config('services.salla.user_info_url'));

            if (! $response->successful()) {
                Log::warning('salla.user_info.failed', ['merchant_id' => $merchantId, 'status' => $response->status()]);

                return [];
            }
        } catch (Throwable $exception) {
            Log::warning('salla.user_info.failed', ['merchant_id' => $merchantId, 'error' => $exception::class]);

            return [];
        }

        $merchant = (array) $response->json('data.merchant', []);

        return array_filter([
            'store_name' => $this->clean($merchant['name'] ?? null, 255),
            'store_domain' => $this->clean($merchant['domain'] ?? null, 255),
            'store_email' => $this->clean($response->json('data.email'), 191),
        ], fn ($value) => $value !== null);
    }

    private function clean(mixed $value, int $max): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : Str::limit($value, $max, '');
    }

    /** Salla sends `expires` as a unix timestamp; the documented access token life is 14 days. */
    private function expiry(mixed $expires): Carbon
    {
        return is_numeric($expires) && (int) $expires > time()
            ? Carbon::createFromTimestamp((int) $expires, config('app.timezone'))
            : now()->addDays(14);
    }
}
