<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TransferContactGroupSeeder extends Seeder
{
    public function run(): void
    {
        // Check if the contacts table exists first
        if (!Schema::hasTable('contacts')) {
            $this->command->info('contacts table does not exist. Migration skipped.');
            return;
        }

        // Check if the contact_contact_group pivot table exists
        if (!Schema::hasTable('contact_contact_group')) {
            $this->command->info('contact_contact_group table does not exist. Migration skipped.');
            return;
        }

        // Check if the contact_group_id column exists before attempting the migration
        try {
            if (!Schema::hasColumn('contacts', 'contact_group_id')) {
                $this->command->info('contact_group_id column does not exist on contacts table. Migration skipped.');
                return;
            }
        } catch (\Exception $e) {
            // If there's any error checking the column, skip the migration
            $this->command->info('Error checking contact_group_id column. Migration skipped.');
            return;
        }

        try {
            DB::beginTransaction();

            // Step 1: Insert into pivot table only if the pair does not already exist
            DB::statement("
                INSERT INTO contact_contact_group (contact_id, contact_group_id)
                SELECT c.id, c.contact_group_id
                FROM contacts c
                WHERE c.contact_group_id IS NOT NULL
                AND NOT EXISTS (
                    SELECT 1
                    FROM contact_contact_group ccg
                    WHERE ccg.contact_id = c.id AND ccg.contact_group_id = c.contact_group_id
                )
            ");

            // Step 2: Nullify contact_group_id in contacts table
            DB::table('contacts')->whereNotNull('contact_group_id')->update(['contact_group_id' => null]);

            // Step 3: Drop the column only if it exists and contains only NULLs
            if (
                Schema::hasColumn('contacts', 'contact_group_id') &&
                DB::table('contacts')->whereNotNull('contact_group_id')->doesntExist()
            ) {
                Schema::table('contacts', function ($table) {
                    $table->dropColumn('contact_group_id');
                });
            }

            DB::commit();
        } catch (\Exception $e) {
            // If transaction fails, rollback and log
            DB::rollBack();
            $this->command->warn('Error during contact group migration: ' . $e->getMessage());
        }
    }
}