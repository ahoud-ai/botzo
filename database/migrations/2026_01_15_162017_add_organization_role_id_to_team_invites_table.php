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
        // Step 1: Add organization_role_id column (nullable initially)
        Schema::table('team_invites', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_role_id')->nullable()->after('organization_id');
        });

        // Step 2: Migrate existing role values to organization_role_id
        // Get universal owner role
        $ownerRoleId = DB::table('organization_roles')
            ->where('name', 'Owner')
            ->whereNull('organization_id')
            ->value('id');

        // Update owner invites
        if ($ownerRoleId) {
            DB::table('team_invites')
                ->where('role', 'owner')
                ->update(['organization_role_id' => $ownerRoleId]);
        }

        // Update manager invites
        $managerRoles = DB::table('organization_roles')
            ->where('name', 'Manager')
            ->whereNotNull('organization_id')
            ->get()
            ->keyBy('organization_id');

        DB::table('team_invites')
            ->where('role', 'manager')
            ->get()
            ->each(function ($invite) use ($managerRoles) {
                if (isset($managerRoles[$invite->organization_id])) {
                    DB::table('team_invites')
                        ->where('id', $invite->id)
                        ->update(['organization_role_id' => $managerRoles[$invite->organization_id]->id]);
                }
            });

        // Update agent invites
        $agentRoles = DB::table('organization_roles')
            ->where('name', 'Agent')
            ->whereNotNull('organization_id')
            ->get()
            ->keyBy('organization_id');

        DB::table('team_invites')
            ->where('role', 'agent')
            ->get()
            ->each(function ($invite) use ($agentRoles) {
                if (isset($agentRoles[$invite->organization_id])) {
                    DB::table('team_invites')
                        ->where('id', $invite->id)
                        ->update(['organization_role_id' => $agentRoles[$invite->organization_id]->id]);
                }
            });

        // Step 3: Make organization_role_id required and add foreign key
        Schema::table('team_invites', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_role_id')->nullable(false)->change();
            $table->foreign('organization_role_id')->references('id')->on('organization_roles')->onDelete('restrict');
        });

        // Step 4: Drop the old role column
        Schema::table('team_invites', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the role column
        Schema::table('team_invites', function (Blueprint $table) {
            $table->enum('role', ['owner', 'manager', 'agent'])->default('manager')->after('organization_id');
        });

        // Migrate data back
        $ownerRoleId = DB::table('organization_roles')
            ->where('name', 'Owner')
            ->whereNull('organization_id')
            ->value('id');

        if ($ownerRoleId) {
            DB::table('team_invites')
                ->where('organization_role_id', $ownerRoleId)
                ->update(['role' => 'owner']);
        }

        $managerRoleIds = DB::table('organization_roles')
            ->where('name', 'Manager')
            ->whereNotNull('organization_id')
            ->pluck('id');

        if ($managerRoleIds->isNotEmpty()) {
            DB::table('team_invites')
                ->whereIn('organization_role_id', $managerRoleIds)
                ->update(['role' => 'manager']);
        }

        $agentRoleIds = DB::table('organization_roles')
            ->where('name', 'Agent')
            ->whereNotNull('organization_id')
            ->pluck('id');

        if ($agentRoleIds->isNotEmpty()) {
            DB::table('team_invites')
                ->whereIn('organization_role_id', $agentRoleIds)
                ->update(['role' => 'agent']);
        }

        // Drop foreign key and column
        Schema::table('team_invites', function (Blueprint $table) {
            $table->dropForeign(['organization_role_id']);
            $table->dropColumn('organization_role_id');
        });
    }
};
