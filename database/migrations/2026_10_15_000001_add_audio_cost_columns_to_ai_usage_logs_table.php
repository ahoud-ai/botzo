<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Voice-note transcription is billed per minute, not per token.
     */
    public function up(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_usage_logs', 'audio_seconds')) {
                $table->decimal('audio_seconds', 8, 1)->nullable()->after('output_tokens');
            }

            if (! Schema::hasColumn('ai_usage_logs', 'cost_usd')) {
                $table->decimal('cost_usd', 12, 6)->nullable()->after('audio_seconds');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            foreach (['cost_usd', 'audio_seconds'] as $column) {
                if (Schema::hasColumn('ai_usage_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
