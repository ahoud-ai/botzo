<?php

namespace Tests\Feature;

use App\Models\PaymentGateway;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MoyasarPaymentGatewayBackfillTest extends TestCase
{
    use DatabaseTransactions;

    public function test_moyasar_gateway_backfill_migration_inserts_missing_row(): void
    {
        PaymentGateway::where('name', 'Moyasar')->delete();

        $migration = require database_path('migrations/2026_03_24_190000_add_moyasar_gateway_to_payment_gateways_table.php');
        $migration->up();

        $this->assertDatabaseHas('payment_gateways', [
            'name' => 'Moyasar',
        ]);
    }
}
