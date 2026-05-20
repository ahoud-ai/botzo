<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('subscription_plans')
            ->select(['id', 'metadata'])
            ->orderBy('id')
            ->chunkById(100, function ($plans): void {
                foreach ($plans as $plan) {
                    $metadata = json_decode($plan->metadata ?? '', true);
                    if (!is_array($metadata)) {
                        $metadata = [];
                    }

                    if (!isset($metadata['addons']) || !is_array($metadata['addons'])) {
                        $metadata['addons'] = [];
                    }

                    if (!array_key_exists('AI Assistant', $metadata['addons'])) {
                        $metadata['addons']['AI Assistant'] = true;

                        DB::table('subscription_plans')
                            ->where('id', $plan->id)
                            ->update([
                                'metadata' => json_encode($metadata),
                                'updated_at' => now(),
                            ]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('subscription_plans')
            ->select(['id', 'metadata'])
            ->orderBy('id')
            ->chunkById(100, function ($plans): void {
                foreach ($plans as $plan) {
                    $metadata = json_decode($plan->metadata ?? '', true);
                    if (!is_array($metadata) || !isset($metadata['addons']) || !is_array($metadata['addons'])) {
                        continue;
                    }

                    if (!array_key_exists('AI Assistant', $metadata['addons'])) {
                        continue;
                    }

                    unset($metadata['addons']['AI Assistant']);

                    DB::table('subscription_plans')
                        ->where('id', $plan->id)
                        ->update([
                            'metadata' => json_encode($metadata),
                            'updated_at' => now(),
                        ]);
                }
            });
    }
};
