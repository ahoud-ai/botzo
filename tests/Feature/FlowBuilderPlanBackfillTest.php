<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FlowBuilderPlanBackfillTest extends TestCase
{
    use DatabaseTransactions;

    public function test_backfill_adds_flow_builder_entitlement_for_ai_enabled_plans(): void
    {
        $eligiblePlanId = DB::table('subscription_plans')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Eligible Plan',
            'price' => 29.99,
            'period' => 'monthly',
            'metadata' => json_encode([
                'addons' => [
                    'AI Assistant' => true,
                ],
            ]),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $nonEligiblePlanId = DB::table('subscription_plans')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Non Eligible Plan',
            'price' => 9.99,
            'period' => 'monthly',
            'metadata' => json_encode([
                'addons' => [
                    'AI Assistant' => false,
                ],
            ]),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require base_path('database/migrations/2026_03_06_020500_backfill_flow_builder_entitlements_in_subscription_plans.php');
        $migration->up();

        $eligibleMetadata = json_decode((string) DB::table('subscription_plans')->where('id', $eligiblePlanId)->value('metadata'), true);
        $nonEligibleMetadata = json_decode((string) DB::table('subscription_plans')->where('id', $nonEligiblePlanId)->value('metadata'), true);

        $this->assertTrue((bool) data_get($eligibleMetadata, 'addons.Flow builder'));
        $this->assertFalse((bool) data_get($nonEligibleMetadata, 'addons.Flow builder', false));
    }
}

