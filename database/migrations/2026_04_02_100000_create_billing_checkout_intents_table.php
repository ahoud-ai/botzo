<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_checkout_intents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('uuid', 50)->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('billing_organization_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('type', ['subscription_purchase', 'account_topup']);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'canceled', 'expired'])->default('pending');
            $table->unsignedBigInteger('target_plan_id')->nullable();
            $table->string('processor')->nullable();
            $table->string('currency', 10)->nullable();
            $table->decimal('base_price', 13, 2)->default(0);
            $table->decimal('gross_amount', 13, 2)->default(0);
            $table->decimal('tax_total', 13, 2)->default(0);
            $table->decimal('net_total', 13, 2)->default(0);
            $table->decimal('balance_applied', 13, 2)->default(0);
            $table->decimal('amount_due', 13, 2)->default(0);
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->string('coupon_code')->nullable();
            $table->decimal('coupon_amount', 13, 2)->default(0);
            $table->string('external_reference', 191)->nullable();
            $table->unsignedBigInteger('completed_invoice_id')->nullable();
            $table->unsignedBigInteger('completed_payment_id')->nullable();
            $table->text('snapshot_json')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['billing_organization_id', 'status'], 'billing_checkout_intents_billing_status_idx');
            $table->index(['processor', 'external_reference'], 'billing_checkout_intents_processor_reference_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_checkout_intents');
    }
};
