<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_verification_requests', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('business_name');
            $table->string('commercial_register_number')->nullable();
            $table->string('phone');
            $table->string('email');
            $table->text('notes')->nullable();
            $table->string('status')->default('new');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_verification_requests');
    }
};
