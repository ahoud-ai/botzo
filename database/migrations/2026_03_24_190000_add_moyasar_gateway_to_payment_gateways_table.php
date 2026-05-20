<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('payment_gateways')) {
            return;
        }

        $exists = DB::table('payment_gateways')
            ->where('name', 'Moyasar')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('payment_gateways')->insert([
            'name' => 'Moyasar',
            'metadata' => null,
            'is_active' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Preserve user-configured gateway data on rollback.
    }
};

