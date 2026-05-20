<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('campaign_logs', function (Blueprint $table) {
            $table->index('scheduled_at', 'idx_scheduled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_logs', function (Blueprint $table) {
            $table->dropIndex('idx_scheduled_at');
        });
    }
};

