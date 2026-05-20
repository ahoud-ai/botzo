<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $plans = DB::table('subscription_plans')
            ->whereNull('deleted_at')
            ->orderBy('period')
            ->orderBy('price')
            ->orderBy('id')
            ->get()
            ->groupBy('period');

        $plans->each(function (Collection $periodPlans): void {
            $rank = 1;

            foreach ($periodPlans as $plan) {
                $metadata = json_decode((string) $plan->metadata, true);

                if (!is_array($metadata)) {
                    $metadata = [];
                }

                if (($metadata['tier_rank'] ?? null) !== null && (int) $metadata['tier_rank'] > 0) {
                    $rank++;
                    continue;
                }

                $metadata['tier_rank'] = $rank;

                DB::table('subscription_plans')
                    ->where('id', $plan->id)
                    ->update([
                        'metadata' => json_encode($metadata),
                        'updated_at' => now(),
                    ]);

                $rank++;
            }
        });
    }

    public function down(): void
    {
        // Intentionally left blank. Tier ranks may have been adjusted manually after the backfill.
    }
};
