<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a contact talks to the organization: "whatsapp" (every existing contact) or "web"
     * (a website widget visitor), so replies are delivered on the right channel.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('contacts', 'channel')) {
                $table->string('channel', 20)->default('whatsapp')->after('email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasColumn('contacts', 'channel')) {
                $table->dropColumn('channel');
            }
        });
    }
};
