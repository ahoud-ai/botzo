<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds composite index (campaign_id, contact_id, status) to optimize lock queries
     * in ProcessSingleCampaignLogJob and other jobs that query by these three fields.
     */
    public function up(): void
    {
        Schema::table('campaign_logs', function (Blueprint $table) {
            // Composite index for campaign_id + contact_id + status
            // Optimizes queries like: WHERE campaign_id = X AND contact_id = Y AND status IN ('pending', 'failed')
            // Used in ProcessSingleCampaignLogJob lock queries
            if (!$this->indexExists('campaign_logs', 'idx_campaign_logs_campaign_contact_status')) {
                $table->index(['campaign_id', 'contact_id', 'status'], 'idx_campaign_logs_campaign_contact_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_logs', function (Blueprint $table) {
            if ($this->indexExists('campaign_logs', 'idx_campaign_logs_campaign_contact_status')) {
                $table->dropIndex('idx_campaign_logs_campaign_contact_status');
            }
        });
    }

    /**
     * Check if an index exists on a given table.
     */
    protected function indexExists(string $table, string $index): bool
    {
        $indexes = DB::select("SHOW INDEXES FROM `{$table}` WHERE Key_name = ?", [$index]);
        return count($indexes) > 0;
    }
};
