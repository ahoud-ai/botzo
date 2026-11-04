<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salla_webhook_events', function (Blueprint $table) {
            $table->id();
            // SHA-256 of the raw body: Salla retries a failed delivery with the same body.
            $table->char('event_hash', 64)->unique();
            $table->string('event', 64)->index();
            $table->unsignedBigInteger('merchant_id')->nullable()->index();
            // Only for app events, with every token removed first.
            $table->json('payload')->nullable();
            $table->string('status', 16)->default('received');
            $table->string('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('created_at', 'salla_webhook_events_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salla_webhook_events');
    }
};
