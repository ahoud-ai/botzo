<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            if (!Schema::hasColumn('pages', 'name_ar')) {
                $table->string('name_ar', 128)->nullable()->after('name');
            }

            if (!Schema::hasColumn('pages', 'name_en')) {
                $table->string('name_en', 128)->nullable()->after('name_ar');
            }

            if (!Schema::hasColumn('pages', 'content_ar')) {
                $table->text('content_ar')->nullable()->after('content');
            }

            if (!Schema::hasColumn('pages', 'content_en')) {
                $table->text('content_en')->nullable()->after('content_ar');
            }
        });
    }

    public function down(): void
    {
        $columns = [];

        foreach (['name_ar', 'name_en', 'content_ar', 'content_en'] as $column) {
            if (Schema::hasColumn('pages', $column)) {
                $columns[] = $column;
            }
        }

        if ($columns === []) {
            return;
        }

        Schema::table('pages', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};
