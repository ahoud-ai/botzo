<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A website visitor's conversation. Only a hash of the visitor's token and of their IP is kept.
     * The contact is created with the visitor's first message, so opening the widget adds nothing to the inbox.
     */
    public function up(): void
    {
        if (Schema::hasTable('web_widget_sessions')) {
            return;
        }

        Schema::create('web_widget_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('web_widget_id');
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->char('token_hash', 64)->unique();
            $table->char('ip_hash', 64)->nullable();
            $table->string('origin', 255)->nullable();
            $table->string('page_url', 500)->nullable();
            $table->string('locale', 10)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('contact_id', 'web_widget_sessions_contact_idx');
            $table->index(['web_widget_id', 'created_at'], 'web_widget_sessions_widget_created_idx');
            $table->index(['ip_hash', 'created_at'], 'web_widget_sessions_ip_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_widget_sessions');
    }
};
