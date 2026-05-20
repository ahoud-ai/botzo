<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ensure all teams have organization_role_id set (safety check)
        // If any teams don't have organization_role_id, assign owner role as fallback
        $ownerRoleId = DB::table('organization_roles')
            ->where('name', 'Owner')
            ->whereNull('organization_id')
            ->value('id');

        if ($ownerRoleId) {
            DB::table('teams')
                ->whereNull('organization_role_id')
                ->update(['organization_role_id' => $ownerRoleId]);
        }

        // Make organization_role_id required and add foreign key
        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_role_id')->nullable(false)->change();
            $table->foreign('organization_role_id')->references('id')->on('organization_roles')->onDelete('restrict');
        });

        // Drop the old enum role column
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the enum role column
        Schema::table('teams', function (Blueprint $table) {
            $table->enum('role', ['owner', 'manager', 'agent'])->default('manager')->after('user_id');
        });

        // Migrate data back from organization_roles to enum
        $ownerRoleId = DB::table('organization_roles')
            ->where('name', 'Owner')
            ->whereNull('organization_id')
            ->value('id');

        if ($ownerRoleId) {
            DB::table('teams')
                ->where('organization_role_id', $ownerRoleId)
                ->update(['role' => 'owner']);
        }

        $managerRoleIds = DB::table('organization_roles')
            ->where('name', 'Manager')
            ->whereNotNull('organization_id')
            ->pluck('id');

        if ($managerRoleIds->isNotEmpty()) {
            DB::table('teams')
                ->whereIn('organization_role_id', $managerRoleIds)
                ->update(['role' => 'manager']);
        }

        $agentRoleIds = DB::table('organization_roles')
            ->where('name', 'Agent')
            ->whereNotNull('organization_id')
            ->pluck('id');

        if ($agentRoleIds->isNotEmpty()) {
            DB::table('teams')
                ->whereIn('organization_role_id', $agentRoleIds)
                ->update(['role' => 'agent']);
        }

        // Drop foreign key and column
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['organization_role_id']);
            $table->dropColumn('organization_role_id');
        });
    }
};
