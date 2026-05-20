<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTypeAndParentToOrganizationsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('organization_type', 20)->default('main')->after('name');
            $table->unsignedBigInteger('parent_organization_id')->nullable()->after('organization_type');

            $table->index('organization_type', 'organizations_type_idx');
            $table->index('parent_organization_id', 'organizations_parent_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropIndex('organizations_type_idx');
            $table->dropIndex('organizations_parent_idx');

            $table->dropColumn('parent_organization_id');
            $table->dropColumn('organization_type');
        });
    }
}
