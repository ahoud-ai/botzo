<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Create universal Owner role (organization_id = NULL)
        $ownerRoleId = DB::table('organization_roles')->insertGetId([
            'uuid' => Str::uuid(),
            'organization_id' => null,
            'name' => 'Owner',
            'description' => __('Organization owner with full access to all features'),
            'permissions' => json_encode(['*']), // All permissions
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Step 2: Get all unique organizations that have team members
        $organizations = DB::table('teams')
            ->select('organization_id')
            ->distinct()
            ->get();

        // Step 3: For each organization, check if it has non-owner team members
        foreach ($organizations as $org) {
            $hasManager = DB::table('teams')
                ->where('organization_id', $org->organization_id)
                ->where('role', 'manager')
                ->exists();

            $hasAgent = DB::table('teams')
                ->where('organization_id', $org->organization_id)
                ->where('role', 'agent')
                ->exists();

            // Create Manager role if organization has managers
            if ($hasManager) {
                DB::table('organization_roles')->insert([
                    'uuid' => Str::uuid(),
                    'organization_id' => $org->organization_id,
                    'name' => 'Manager',
                    'description' => __('Manager role with access to manage contacts, chats, and tickets'),
                    'permissions' => json_encode([
                        'contacts.view_all',
                        'contacts.view_unassigned',
                        'contacts.create',
                        'contacts.edit',
                        'chats.view_all',
                        'chats.send_message',
                        'tickets.view_all',
                        'tickets.assign',
                        'tickets.close',
                        'reports.view'
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Create Agent role if organization has agents
            if ($hasAgent) {
                DB::table('organization_roles')->insert([
                    'uuid' => Str::uuid(),
                    'organization_id' => $org->organization_id,
                    'name' => 'Agent',
                    'description' => __('Agent role with access to assigned contacts and chats only'),
                    'permissions' => json_encode([
                        'contacts.view_assigned',
                        'chats.view_assigned',
                        'chats.send_message',
                        'tickets.view_assigned'
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Step 4: Add temporary column to store organization_role_id
        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_role_id')->nullable()->after('user_id');
        });

        // Step 5: Update teams table to link to organization_roles
        // Update owner roles
        DB::table('teams')
            ->where('role', 'owner')
            ->update(['organization_role_id' => $ownerRoleId]);

        // Update manager roles
        $managerRoles = DB::table('organization_roles')
            ->where('name', 'Manager')
            ->whereNotNull('organization_id')
            ->get()
            ->keyBy('organization_id');

        DB::table('teams')
            ->where('role', 'manager')
            ->get()
            ->each(function ($team) use ($managerRoles) {
                if (isset($managerRoles[$team->organization_id])) {
                    DB::table('teams')
                        ->where('id', $team->id)
                        ->update(['organization_role_id' => $managerRoles[$team->organization_id]->id]);
                }
            });

        // Update agent roles
        $agentRoles = DB::table('organization_roles')
            ->where('name', 'Agent')
            ->whereNotNull('organization_id')
            ->get()
            ->keyBy('organization_id');

        DB::table('teams')
            ->where('role', 'agent')
            ->get()
            ->each(function ($team) use ($agentRoles) {
                if (isset($agentRoles[$team->organization_id])) {
                    DB::table('teams')
                        ->where('id', $team->id)
                        ->update(['organization_role_id' => $agentRoles[$team->organization_id]->id]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the temporary column
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('organization_role_id');
        });

        // Note: We don't delete organization_roles here as they might have been modified
        // The modify_teams_table migration will handle the full rollback
    }
};
