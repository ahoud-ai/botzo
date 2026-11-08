<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salla_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salla_store_id')->constrained('salla_stores')->cascadeOnDelete();
            $table->unsignedBigInteger('salla_product_id');
            $table->string('name');
            $table->string('type', 32)->default('product');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('regular_price', 12, 2)->nullable();
            $table->string('currency', 8)->default('SAR');
            $table->boolean('is_available')->default(true);
            $table->string('image_url', 1000)->nullable();
            $table->string('url', 1000)->nullable();
            $table->json('options')->nullable();
            $table->text('search_text')->nullable();
            $table->timestamps();

            $table->unique(['salla_store_id', 'salla_product_id']);
        });

        Schema::table('salla_stores', function (Blueprint $table) {
            $table->timestamp('products_synced_at')->nullable()->after('subscription_event_at');
        });
    }

    public function down(): void
    {
        Schema::table('salla_stores', function (Blueprint $table) {
            $table->dropColumn('products_synced_at');
        });

        Schema::dropIfExists('salla_products');
    }
};
