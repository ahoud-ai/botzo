<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_verification_requests', function (Blueprint $table) {
            $table->string('whatsapp_number')->nullable()->after('phone');
            $table->string('legal_company_name')->nullable()->after('business_name');
            $table->string('website_url')->nullable()->after('legal_company_name');
            $table->string('document_path')->nullable()->after('notes');
            $table->string('document_original_name')->nullable()->after('document_path');
            $table->text('admin_note')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('meta_verification_requests', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_number',
                'legal_company_name',
                'website_url',
                'document_path',
                'document_original_name',
                'admin_note',
            ]);
        });
    }
};
