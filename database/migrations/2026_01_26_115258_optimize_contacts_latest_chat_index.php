<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        $result = DB::select(
            "SELECT COUNT(*) as count 
             FROM information_schema.statistics 
             WHERE table_schema = ? 
             AND table_name = ? 
             AND index_name = ?",
            [$databaseName, $table, $indexName]
        );
        
        return $result[0]->count > 0;
    }

    /**
     * Run the migrations.
     * 
     * Optimizes the index for pagination queries by reordering columns:
     * Old: (organization_id, deleted_at, latest_chat_created_at)
     * New: (organization_id, latest_chat_created_at, deleted_at)
     * 
     * This allows MySQL to efficiently:
     * 1. Filter by organization_id (equality)
     * 2. Order by latest_chat_created_at (range/ordering)
     * 3. Filter by deleted_at (equality on remaining rows)
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Drop the old index if it exists
            if ($this->indexExists('contacts', 'idx_contacts_org_deleted_latest')) {
                $table->dropIndex('idx_contacts_org_deleted_latest');
            }
            
            // Create optimized index for pagination queries
            // Order: organization_id (filter) -> latest_chat_created_at (order) -> deleted_at (filter)
            if (!$this->indexExists('contacts', 'idx_contacts_org_latest_deleted')) {
                $table->index(
                    ['organization_id', 'latest_chat_created_at', 'deleted_at'],
                    'idx_contacts_org_latest_deleted'
                );
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Drop the optimized index
            if ($this->indexExists('contacts', 'idx_contacts_org_latest_deleted')) {
                $table->dropIndex('idx_contacts_org_latest_deleted');
            }
            
            // Restore the old index
            if (!$this->indexExists('contacts', 'idx_contacts_org_deleted_latest')) {
                $table->index(
                    ['organization_id', 'deleted_at', 'latest_chat_created_at'],
                    'idx_contacts_org_deleted_latest'
                );
            }
        });
    }
};
