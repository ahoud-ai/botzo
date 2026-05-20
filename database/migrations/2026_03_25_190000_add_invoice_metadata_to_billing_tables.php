<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('billing_invoices', 'created_at')) {
                $table->timestamp('created_at')->nullable()->after('total');
            }

            if (!Schema::hasColumn('billing_invoices', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });

        Schema::table('billing_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('billing_payments', 'invoice_id')) {
                $table->unsignedBigInteger('invoice_id')->nullable()->after('organization_id');
                $table->index('invoice_id');
            }

            if (!Schema::hasColumn('billing_payments', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('processor');
            }

            if (!Schema::hasColumn('billing_payments', 'created_at')) {
                $table->timestamp('created_at')->nullable()->after('amount');
            }

            if (!Schema::hasColumn('billing_payments', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });

        $invoiceTransactions = DB::table('billing_transactions')
            ->select(['entity_id', 'created_at'])
            ->where('entity_type', 'invoice')
            ->whereNotNull('created_at')
            ->get()
            ->groupBy('entity_id');

        DB::table('billing_invoices')
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(100, function ($invoices) use ($invoiceTransactions) {
                foreach ($invoices as $invoice) {
                    $timestamp = optional($invoiceTransactions->get($invoice->id))->first()->created_at ?? now();

                    DB::table('billing_invoices')
                        ->where('id', $invoice->id)
                        ->update([
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ]);
                }
            });

        $paymentTransactions = DB::table('billing_transactions')
            ->select(['entity_id', 'created_at'])
            ->where('entity_type', 'payment')
            ->whereNotNull('created_at')
            ->get()
            ->groupBy('entity_id');

        DB::table('billing_payments')
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(100, function ($payments) use ($paymentTransactions) {
                foreach ($payments as $payment) {
                    $timestamp = optional($paymentTransactions->get($payment->id))->first()->created_at ?? now();

                    DB::table('billing_payments')
                        ->where('id', $payment->id)
                        ->update([
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('billing_payments', function (Blueprint $table) {
            if (Schema::hasColumn('billing_payments', 'invoice_id')) {
                $table->dropIndex(['invoice_id']);
                $table->dropColumn('invoice_id');
            }

            foreach (['payment_method', 'created_at', 'updated_at'] as $column) {
                if (Schema::hasColumn('billing_payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('billing_invoices', function (Blueprint $table) {
            foreach (['created_at', 'updated_at'] as $column) {
                if (Schema::hasColumn('billing_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
