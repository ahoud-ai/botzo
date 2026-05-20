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
     */
    public function up(): void
    {
        // Contacts table indexes
        Schema::table('contacts', function (Blueprint $table) {
            // Composite index on organization_id, deleted_at, latest_chat_created_at
            if (!$this->indexExists('contacts', 'idx_contacts_org_deleted_latest')) {
                $table->index(['organization_id', 'deleted_at', 'latest_chat_created_at'], 'idx_contacts_org_deleted_latest');
            }
            
            // Index on phone
            if (!$this->indexExists('contacts', 'idx_contacts_phone')) {
                $table->index('phone', 'idx_contacts_phone');
            }
            
            // Index on email
            if (!$this->indexExists('contacts', 'idx_contacts_email')) {
                $table->index('email', 'idx_contacts_email');
            }
            
            // Composite index on first_name, last_name
            if (!$this->indexExists('contacts', 'idx_contacts_name')) {
                $table->index(['first_name', 'last_name'], 'idx_contacts_name');
            }
        });

        // Chat tickets table index
        Schema::table('chat_tickets', function (Blueprint $table) {
            // Index on contact_id
            if (!$this->indexExists('chat_tickets', 'idx_chat_tickets_contact')) {
                $table->index('contact_id', 'idx_chat_tickets_contact');
            }
        });

        // Chats table indexes
        Schema::table('chats', function (Blueprint $table) {
            // Composite index on contact_id, organization_id, deleted_at, created_at
            if (!$this->indexExists('chats', 'idx_chats_contact_org_deleted_created')) {
                $table->index(['contact_id', 'organization_id', 'deleted_at', 'created_at'], 'idx_chats_contact_org_deleted_created');
            }
            
            // Composite index on contact_id, organization_id, deleted_at, type, is_read
            if (!$this->indexExists('chats', 'idx_chats_unread')) {
                $table->index(['contact_id', 'organization_id', 'deleted_at', 'type', 'is_read'], 'idx_chats_unread');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop contacts table indexes
        Schema::table('contacts', function (Blueprint $table) {
            if ($this->indexExists('contacts', 'idx_contacts_org_deleted_latest')) {
                $table->dropIndex('idx_contacts_org_deleted_latest');
            }
            if ($this->indexExists('contacts', 'idx_contacts_phone')) {
                $table->dropIndex('idx_contacts_phone');
            }
            if ($this->indexExists('contacts', 'idx_contacts_email')) {
                $table->dropIndex('idx_contacts_email');
            }
            if ($this->indexExists('contacts', 'idx_contacts_name')) {
                $table->dropIndex('idx_contacts_name');
            }
        });

        // Drop chat_tickets table index
        Schema::table('chat_tickets', function (Blueprint $table) {
            if ($this->indexExists('chat_tickets', 'idx_chat_tickets_contact')) {
                $table->dropIndex('idx_chat_tickets_contact');
            }
        });

        // Drop chats table indexes
        Schema::table('chats', function (Blueprint $table) {
            if ($this->indexExists('chats', 'idx_chats_contact_org_deleted_created')) {
                $table->dropIndex('idx_chats_contact_org_deleted_created');
            }
            if ($this->indexExists('chats', 'idx_chats_unread')) {
                $table->dropIndex('idx_chats_unread');
            }
        });
    }
};
