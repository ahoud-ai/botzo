<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBilingualFieldsToReviewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('reviews', 'name_ar')) {
                $table->string('name_ar')->nullable()->after('name');
            }

            if (!Schema::hasColumn('reviews', 'name_en')) {
                $table->string('name_en')->nullable()->after('name_ar');
            }

            if (!Schema::hasColumn('reviews', 'position_ar')) {
                $table->string('position_ar')->nullable()->after('position');
            }

            if (!Schema::hasColumn('reviews', 'position_en')) {
                $table->string('position_en')->nullable()->after('position_ar');
            }

            if (!Schema::hasColumn('reviews', 'review_ar')) {
                $table->text('review_ar')->nullable()->after('review');
            }

            if (!Schema::hasColumn('reviews', 'review_en')) {
                $table->text('review_en')->nullable()->after('review_ar');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $columns = [];

        foreach (['name_ar', 'name_en', 'position_ar', 'position_en', 'review_ar', 'review_en'] as $column) {
            if (Schema::hasColumn('reviews', $column)) {
                $columns[] = $column;
            }
        }

        if ($columns !== []) {
            Schema::table('reviews', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
}
