<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faqs', function (Blueprint $table): void {
            if (!Schema::hasColumn('faqs', 'question_ar')) {
                $table->text('question_ar')->nullable()->after('question');
            }

            if (!Schema::hasColumn('faqs', 'question_en')) {
                $table->text('question_en')->nullable()->after('question_ar');
            }

            if (!Schema::hasColumn('faqs', 'answer_ar')) {
                $table->text('answer_ar')->nullable()->after('answer');
            }

            if (!Schema::hasColumn('faqs', 'answer_en')) {
                $table->text('answer_en')->nullable()->after('answer_ar');
            }
        });
    }

    public function down(): void
    {
        $columns = [];

        foreach (['question_ar', 'question_en', 'answer_ar', 'answer_en'] as $column) {
            if (Schema::hasColumn('faqs', $column)) {
                $columns[] = $column;
            }
        }

        if ($columns === []) {
            return;
        }

        Schema::table('faqs', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};

