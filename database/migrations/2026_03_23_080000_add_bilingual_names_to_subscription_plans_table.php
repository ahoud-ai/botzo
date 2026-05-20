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
        Schema::table('subscription_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('subscription_plans', 'name_ar')) {
                $table->string('name_ar', 100)->nullable()->after('name');
            }

            if (!Schema::hasColumn('subscription_plans', 'name_en')) {
                $table->string('name_en', 100)->nullable()->after('name_ar');
            }
        });

        DB::table('subscription_plans')
            ->whereNull('name_ar')
            ->update(['name_ar' => DB::raw('name')]);

        DB::table('subscription_plans')
            ->whereNull('name_en')
            ->update(['name_en' => DB::raw('name')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            if (Schema::hasColumn('subscription_plans', 'name_ar')) {
                $table->dropColumn('name_ar');
            }

            if (Schema::hasColumn('subscription_plans', 'name_en')) {
                $table->dropColumn('name_en');
            }
        });
    }
};
