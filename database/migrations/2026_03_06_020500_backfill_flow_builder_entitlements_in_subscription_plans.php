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
        $plans = DB::table('subscription_plans')->select('id', 'metadata')->get();

        foreach ($plans as $plan) {
            $metadata = [];
            if (is_string($plan->metadata) && trim($plan->metadata) !== '') {
                $decoded = json_decode($plan->metadata, true);
                if (is_array($decoded)) {
                    $metadata = $decoded;
                }
            }

            $addons = $metadata['addons'] ?? null;
            if (!is_array($addons)) {
                continue;
            }

            if (!array_key_exists('AI Assistant', $addons)) {
                continue;
            }

            if (($addons['Flow builder'] ?? null) === true) {
                continue;
            }

            if (($addons['AI Assistant'] ?? false) !== true) {
                continue;
            }

            $metadata['addons']['Flow builder'] = true;

            DB::table('subscription_plans')
                ->where('id', $plan->id)
                ->update([
                    'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive backfill by design.
    }
};

