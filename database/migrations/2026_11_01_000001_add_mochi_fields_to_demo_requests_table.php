<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Demo requests can now come from Mochi on botzo.net, which asks only for a name and a WhatsApp
 * number: the email becomes optional, and each request records where it came from, the time the
 * visitor prefers, and the inbox contact of the conversation it was made in.
 * Also adds the "demo_requests" admin module so other admin roles can be given the new page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demo_requests', function (Blueprint $table) {
            $table->string('email')->nullable()->change();

            if (! Schema::hasColumn('demo_requests', 'source')) {
                $table->string('source', 20)->default('website')->after('notes');
            }
            if (! Schema::hasColumn('demo_requests', 'preferred_time')) {
                $table->string('preferred_time', 20)->nullable()->after('source');
            }
            if (! Schema::hasColumn('demo_requests', 'contact_id')) {
                $table->unsignedBigInteger('contact_id')->nullable()->after('preferred_time');
                $table->index('contact_id', 'demo_requests_contact_idx');
            }
            $table->index(['status', 'created_at'], 'demo_requests_status_created_idx');
        });

        if (! DB::table('modules')->where('name', 'demo_requests')->exists()) {
            DB::table('modules')->insert(['name' => 'demo_requests', 'actions' => 'view, edit']);
        }
    }

    public function down(): void
    {
        Schema::table('demo_requests', function (Blueprint $table) {
            $table->dropIndex('demo_requests_status_created_idx');
            $table->dropIndex('demo_requests_contact_idx');
            $table->dropColumn(['source', 'preferred_time', 'contact_id']);
        });
        // The email column stays nullable: rows without an email may exist by then.
    }
};
