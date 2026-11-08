<?php

namespace App\Console\Commands;

use App\Models\SallaStore;
use App\Services\Salla\SallaProductSync;
use Illuminate\Console\Command;
use Throwable;

class SyncSallaProductsCommand extends Command
{
    private const STORES_PER_RUN = 20;

    protected $signature = 'salla:sync-products';

    protected $description = 'Copy the products of new stores and of stores not synced in the last hour.';

    public function handle(SallaProductSync $sync): int
    {
        $synced = 0;
        $failed = 0;

        SallaStore::query()
            ->where('status', SallaStore::STATUS_INSTALLED)
            ->whereNotNull('access_token')
            ->where(fn ($query) => $query
                ->whereNull('products_synced_at')
                ->orWhere('products_synced_at', '<', now()->subHour()))
            ->orderByRaw('products_synced_at is not null, products_synced_at')
            ->limit(self::STORES_PER_RUN)
            ->get()
            ->each(function (SallaStore $store) use ($sync, &$synced, &$failed) {
                try {
                    $sync->sync($store) ? $synced++ : $failed++;
                } catch (Throwable $exception) {
                    report($exception);
                    $failed++;
                }
            });

        $this->info("Synced {$synced}, failed {$failed}.");

        return self::SUCCESS;
    }
}
