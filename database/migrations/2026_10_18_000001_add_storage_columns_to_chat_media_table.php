<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Media received from customers is stored privately and served through an authenticated
     * route. storage_path is the file's key on its disk; visibility is "private" for those files
     * and null for everything stored before (public URLs, unchanged).
     */
    public function up(): void
    {
        Schema::table('chat_media', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_media', 'storage_path')) {
                $table->string('storage_path', 512)->nullable()->after('path');
            }

            if (! Schema::hasColumn('chat_media', 'visibility')) {
                $table->string('visibility', 16)->nullable()->after('storage_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_media', function (Blueprint $table) {
            foreach (['visibility', 'storage_path'] as $column) {
                if (Schema::hasColumn('chat_media', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
