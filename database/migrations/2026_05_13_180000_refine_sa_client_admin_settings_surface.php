<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const RETIRED_SETTING_KEYS = [
        'available_version',
        'is_update_available',
        'last_update_check',
        'release_date',
        'version',
    ];

    public function up(): void
    {
        $this->refineAdminPermissions();
        $this->refineSettings();
        $this->refineAddonSchema();
    }

    public function down(): void
    {
        //
    }

    private function refineAdminPermissions(): void
    {
        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->where('module', 'updates')->delete();
        }

        if (Schema::hasTable('modules')) {
            DB::table('modules')->where('name', 'updates')->delete();
        }
    }

    private function refineSettings(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->whereIn('key', self::RETIRED_SETTING_KEYS)->delete();
    }

    private function refineAddonSchema(): void
    {
        if (! Schema::hasTable('addons')) {
            return;
        }

        $columns = [];
        foreach (['update_available', 'version'] as $column) {
            if (Schema::hasColumn('addons', $column)) {
                $columns[] = $column;
            }
        }

        if ($columns === []) {
            return;
        }

        Schema::table('addons', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};
