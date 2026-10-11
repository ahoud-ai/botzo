<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * While a team member is talking to a customer, automatic replies for that
 * contact are paused until this time (null = not paused).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contacts', 'ai_paused_until')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->timestamp('ai_paused_until')->nullable()->after('ai_assistance_enabled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contacts', 'ai_paused_until')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->dropColumn('ai_paused_until');
            });
        }
    }
};
