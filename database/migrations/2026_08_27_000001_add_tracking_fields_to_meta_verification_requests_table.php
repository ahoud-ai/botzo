<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_verification_requests', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')
                ->constrained('organizations')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('status');
        });

        DB::table('meta_verification_requests')->where('status', 'new')->update(['status' => 'requested']);
    }

    public function down(): void
    {
        Schema::table('meta_verification_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn('rejection_reason');
        });
    }
};
