<?php

namespace App\Console\Commands;

use App\Models\SallaStore;
use App\Services\Salla\SallaStoreInstaller;
use App\Services\Salla\SallaTokenManager;
use Illuminate\Console\Command;
use Throwable;

class RefreshSallaTokensCommand extends Command
{
    protected $signature = 'salla:refresh-tokens';

    protected $description = 'Refresh Salla tokens that are close to expiry and fill in missing store details';

    public function handle(SallaTokenManager $tokens, SallaStoreInstaller $installer): int
    {
        $refreshed = 0;
        $failed = 0;

        SallaStore::query()
            ->where('status', SallaStore::STATUS_INSTALLED)
            ->where(fn ($query) => $query
                ->whereNull('token_expires_at')
                ->orWhere('token_expires_at', '<=', now()->addDays(SallaTokenManager::REFRESH_BEFORE_DAYS)))
            ->select('merchant_id')
            ->chunkById(100, function ($stores) use ($tokens, &$refreshed, &$failed) {
                foreach ($stores as $store) {
                    try {
                        $tokens->refreshIfDue($store->merchant_id) ? $refreshed++ : $failed++;
                    } catch (Throwable $exception) {
                        // Usually a held lock. The next run retries this store; the others carry on.
                        report($exception);
                        $failed++;
                    }
                }
            }, 'merchant_id');

        SallaStore::query()
            ->where('status', SallaStore::STATUS_INSTALLED)
            ->whereNull('store_name')
            ->limit(50)
            ->get()
            ->each(fn (SallaStore $store) => $installer->backfillDetails($store));

        $this->info("Refreshed {$refreshed}, failed {$failed}.");

        return self::SUCCESS;
    }
}
