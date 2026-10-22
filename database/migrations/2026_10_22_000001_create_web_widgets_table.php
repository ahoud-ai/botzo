<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One website chat widget per organization. public_key identifies it in the embed code
     * (it is not a secret); the widget only answers on allowed_domains.
     */
    public function up(): void
    {
        if (Schema::hasTable('web_widgets')) {
            return;
        }

        Schema::create('web_widgets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id')->unique();
            $table->string('public_key', 64)->unique();
            $table->boolean('is_enabled')->default(false);
            $table->json('allowed_domains')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedInteger('daily_reply_limit')->default(300);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_widgets');
    }
};
