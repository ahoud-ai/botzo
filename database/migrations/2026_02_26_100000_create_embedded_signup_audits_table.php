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
        Schema::create('embedded_signup_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event', 128);
            $table->string('status', 32);
            $table->string('error_code', 64)->nullable();
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at'], 'idx_embedded_audits_org_created');
            $table->index(['event', 'status'], 'idx_embedded_audits_event_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('embedded_signup_audits');
    }
};

