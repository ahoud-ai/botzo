<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropSeoFromPagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('pages', 'seo') || ! Schema::hasColumn('pages', 'created_at')) {
            Schema::table('pages', function (Blueprint $table) {
                if (Schema::hasColumn('pages', 'seo')) {
                    $table->dropColumn('seo');
                }

                if (! Schema::hasColumn('pages', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
            });
        }

        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->decimal('coupon_amount', 23, 2)->nullable()->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (! Schema::hasColumn('pages', 'seo') || Schema::hasColumn('pages', 'created_at')) {
            Schema::table('pages', function (Blueprint $table) {
                if (! Schema::hasColumn('pages', 'seo')) {
                    $table->string('seo')->nullable();
                }

                if (Schema::hasColumn('pages', 'created_at')) {
                    $table->dropColumn('created_at');
                }
            });
        }

        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->decimal('coupon_amount', 23, 2)->nullable(false)->default(0)->change();
        });
    }
}
