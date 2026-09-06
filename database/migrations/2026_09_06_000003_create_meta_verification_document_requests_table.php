<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_verification_document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_verification_request_id');
            $table->foreign('meta_verification_request_id', 'mvdr_request_id_fk')
                ->references('id')->on('meta_verification_requests')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable();
            $table->foreign('requested_by', 'mvdr_requested_by_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->string('label');
            $table->text('note')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('fulfilled_document_id')->nullable();
            $table->foreign('fulfilled_document_id', 'mvdr_fulfilled_document_fk')
                ->references('id')->on('meta_verification_documents')->nullOnDelete();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_verification_document_requests');
    }
};
