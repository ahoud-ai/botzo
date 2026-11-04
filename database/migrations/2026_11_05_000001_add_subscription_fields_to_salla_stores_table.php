<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salla_stores', function (Blueprint $table) {
            $table->string('salla_subscription_id', 64)->nullable()->after('scope');
            // Salla retries late, so an older subscription event must not replace a newer one.
            $table->timestamp('subscription_event_at')->nullable()->after('salla_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('salla_stores', function (Blueprint $table) {
            $table->dropColumn(['salla_subscription_id', 'subscription_event_at']);
        });
    }
};
