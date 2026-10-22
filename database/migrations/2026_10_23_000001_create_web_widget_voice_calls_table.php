<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Voice calls of the website widget, so call minutes can be counted against the plan,
     * the per-call limit and the platform's daily cap.
     */
    public function up(): void
    {
        if (Schema::hasTable('web_widget_voice_calls')) {
            return;
        }

        Schema::create('web_widget_voice_calls', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('web_widget_id');
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('web_widget_session_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('model', 64);
            $table->string('voice', 32);
            $table->string('status', 16)->default('active');
            $table->unsignedInteger('max_seconds');
            $table->unsignedInteger('seconds')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'started_at'], 'web_widget_voice_calls_org_started_idx');
            $table->index('started_at', 'web_widget_voice_calls_started_idx');
            $table->index('web_widget_session_id', 'web_widget_voice_calls_session_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_widget_voice_calls');
    }
};
