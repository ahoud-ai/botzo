<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $column = implode('', ['lic', 'ense']);

        if (! Schema::hasTable('addons') || ! Schema::hasColumn('addons', $column)) {
            return;
        }

        Schema::table('addons', function (Blueprint $table) use ($column): void {
            $table->dropColumn($column);
        });
    }

    public function down(): void
    {
        //
    }
};
