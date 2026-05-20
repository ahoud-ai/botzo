<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('campaign_log_retries')) {
            return;
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        Schema::table('campaign_log_retries', function (Blueprint $table) use ($driver) {
            if ($driver === 'mysql') {
                $indexes = collect(DB::select("SHOW INDEX FROM campaign_log_retries"))
                    ->pluck('Key_name')
                    ->all();

                if (!in_array('clr_campaign_log_id_idx', $indexes, true)) {
                    $table->index('campaign_log_id', 'clr_campaign_log_id_idx');
                }

                if (!in_array('clr_status_created_at_idx', $indexes, true)) {
                    $table->index(['status', 'created_at'], 'clr_status_created_at_idx');
                }

                return;
            }

            // Fallback for non-MySQL drivers used in local testing.
            $table->index('campaign_log_id', 'clr_campaign_log_id_idx');
            $table->index(['status', 'created_at'], 'clr_status_created_at_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('campaign_log_retries')) {
            return;
        }

        Schema::table('campaign_log_retries', function (Blueprint $table) {
            $table->dropIndex('clr_campaign_log_id_idx');
            $table->dropIndex('clr_status_created_at_idx');
        });
    }
};

