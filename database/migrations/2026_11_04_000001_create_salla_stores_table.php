<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salla_stores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id')->unique();
            $table->unsignedBigInteger('organization_id')->index();
            $table->string('store_name')->nullable();
            $table->string('store_domain')->nullable();
            $table->string('store_email', 191)->nullable();
            // Encrypted by the model; cleared as soon as the app is uninstalled.
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable()->index();
            $table->text('scope')->nullable();
            $table->string('status', 32)->default('installed')->index();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('uninstalled_at')->nullable();
            $table->timestamp('purge_after')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salla_stores');
    }
};
