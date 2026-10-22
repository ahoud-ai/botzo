<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 50)->unique();
            $table->unsignedBigInteger('organization_id')->index();
            $table->string('url', 500);
            $table->json('events');
            // Signing secret, encrypted with the app key; shown to the customer once.
            $table->text('secret_encrypted');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            // The event id the customer sees (evt_...), also sent as X-Botzo-Delivery.
            $table->string('event_id', 40)->unique();
            $table->unsignedBigInteger('webhook_endpoint_id');
            $table->unsignedBigInteger('organization_id');
            $table->string('event', 40);
            $table->longText('payload');
            $table->string('status', 16)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->unsignedInteger('response_ms')->nullable();
            $table->string('error', 255)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_attempt_at']);
            $table->index(['organization_id', 'id']);
            $table->index(['webhook_endpoint_id', 'id']);
        });

        Schema::create('webhook_inspector_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->string('event', 40)->nullable();
            $table->json('headers');
            $table->longText('body');
            $table->boolean('signature_valid')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_inspector_requests');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
    }
};
