<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_verification_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_verification_request_id');
            $table->foreign('meta_verification_request_id', 'mvd_request_id_fk')
                ->references('id')->on('meta_verification_requests')->cascadeOnDelete();
            $table->string('document_type');
            $table->string('label');
            $table->string('path');
            $table->string('original_name');
            $table->string('uploaded_by')->default('customer');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_verification_documents');
    }
};
