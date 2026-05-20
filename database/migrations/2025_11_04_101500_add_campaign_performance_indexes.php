<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Indexes for campaigns table
        Schema::table('campaigns', function (Blueprint $table) {
            // Organization ID is heavily used in WHERE clauses
            if (!$this->indexExists('campaigns', 'idx_campaigns_organization_id')) {
                $table->index('organization_id', 'idx_campaigns_organization_id');
            }
            
            // Composite index for the main query: organization_id + deleted_at
            if (!$this->indexExists('campaigns', 'idx_campaigns_org_deleted')) {
                $table->index(['organization_id', 'deleted_at'], 'idx_campaigns_org_deleted');
            }
            
            // Contact group ID for joins
            if (!$this->indexExists('campaigns', 'idx_campaigns_contact_group_id')) {
                $table->index('contact_group_id', 'idx_campaigns_contact_group_id');
            }
            
            // Template ID for joins
            if (!$this->indexExists('campaigns', 'idx_campaigns_template_id')) {
                $table->index('template_id', 'idx_campaigns_template_id');
            }
        });

        // Indexes for campaign_logs table
        Schema::table('campaign_logs', function (Blueprint $table) {
            // Campaign ID is the most critical index for aggregation queries
            if (!$this->indexExists('campaign_logs', 'idx_campaign_logs_campaign_id')) {
                $table->index('campaign_id', 'idx_campaign_logs_campaign_id');
            }
            
            // Composite index for campaign_id + status (used in WHERE clauses)
            if (!$this->indexExists('campaign_logs', 'idx_campaign_logs_campaign_status')) {
                $table->index(['campaign_id', 'status'], 'idx_campaign_logs_campaign_status');
            }
            
            // Chat ID for joins with chats table
            if (!$this->indexExists('campaign_logs', 'idx_campaign_logs_chat_id')) {
                $table->index('chat_id', 'idx_campaign_logs_chat_id');
            }
            
            // Composite index for campaign_id + status + chat_id (optimizes the join query)
            if (!$this->indexExists('campaign_logs', 'idx_campaign_logs_composite')) {
                $table->index(['campaign_id', 'status', 'chat_id'], 'idx_campaign_logs_composite');
            }
        });

        // Indexes for chats table (if not already exists)
        Schema::table('chats', function (Blueprint $table) {
            // Status index for filtering in joins
            if (!$this->indexExists('chats', 'idx_chats_status')) {
                $table->index('status', 'idx_chats_status');
            }
            
            // Composite index for id + status (used in campaign_logs join)
            if (!$this->indexExists('chats', 'idx_chats_id_status')) {
                $table->index(['id', 'status'], 'idx_chats_id_status');
            }
        });

        // Indexes for contact_contact_group table
        Schema::table('contact_contact_group', function (Blueprint $table) {
            // Composite index for contact_group_id joins with contacts
            if (!$this->indexExists('contact_contact_group', 'idx_ccg_group_contact')) {
                $table->index(['contact_group_id', 'contact_id'], 'idx_ccg_group_contact');
            }
        });

        // Fix the incorrect index on campaign_logs (id, status) - should be campaign_id
        if ($this->indexExists('campaign_logs', 'idx_campaign_id_status')) {
            Schema::table('campaign_logs', function (Blueprint $table) {
                $table->dropIndex('idx_campaign_id_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex('idx_campaigns_organization_id');
            $table->dropIndex('idx_campaigns_org_deleted');
            $table->dropIndex('idx_campaigns_contact_group_id');
            $table->dropIndex('idx_campaigns_template_id');
        });

        Schema::table('campaign_logs', function (Blueprint $table) {
            $table->dropIndex('idx_campaign_logs_campaign_id');
            $table->dropIndex('idx_campaign_logs_campaign_status');
            $table->dropIndex('idx_campaign_logs_chat_id');
            $table->dropIndex('idx_campaign_logs_composite');
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->dropIndex('idx_chats_status');
            $table->dropIndex('idx_chats_id_status');
        });

        Schema::table('contact_contact_group', function (Blueprint $table) {
            $table->dropIndex('idx_ccg_group_contact');
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
