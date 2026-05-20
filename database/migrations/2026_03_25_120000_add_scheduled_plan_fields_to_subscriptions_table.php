<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('scheduled_plan_id')
                ->nullable()
                ->after('plan_id')
                ->constrained('subscription_plans')
                ->nullOnDelete();

            $table->dateTime('scheduled_plan_change_at')
                ->nullable()
                ->after('valid_until');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scheduled_plan_id');
            $table->dropColumn('scheduled_plan_change_at');
        });
    }
};
