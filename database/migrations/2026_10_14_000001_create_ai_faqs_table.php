<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manual question/answer pairs the AI assistant answers from, per organization.
     */
    public function up(): void
    {
        if (Schema::hasTable('ai_faqs')) {
            return;
        }

        Schema::create('ai_faqs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('question', 300);
            $table->text('answer');
            $table->json('embedding')->nullable();
            $table->string('embedding_model', 128)->nullable();
            $table->timestamps();

            $table->index('organization_id', 'ai_faqs_organization_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_faqs');
    }
};
