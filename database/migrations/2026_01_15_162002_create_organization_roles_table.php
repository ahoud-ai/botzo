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
        Schema::create('organization_roles', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 50)->unique();
            $table->unsignedBigInteger('organization_id')->nullable(); // NULL for universal owner role
            $table->string('name'); // e.g., "Owner", "Manager", "Agent", "Custom Role"
            $table->text('description')->nullable();
            $table->json('permissions')->nullable(); // JSON array of permission strings
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unique(['organization_id', 'name']); // Prevent duplicate role names per org
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_roles');
    }
};
