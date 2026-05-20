<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmbeddedSignupPlanBackfillTest extends TestCase
{
    use DatabaseTransactions;

    public function test_backfill_adds_embedded_signup_flag_when_missing(): void
    {
        $planId = $this->insertPlan([
            'addons' => [
                'AI Assistant' => true,
            ],
        ]);

        $migration = require base_path('database/migrations/2026_02_26_120000_backfill_embedded_signup_in_subscription_plans.php');
        $migration->up();

        $metadata = json_decode(DB::table('subscription_plans')->where('id', $planId)->value('metadata'), true);

        $this->assertTrue($metadata['addons']['Embedded Signup'] ?? false);
    }

    public function test_backfill_does_not_override_existing_embedded_signup_value(): void
    {
        $falsePlanId = $this->insertPlan([
            'addons' => [
                'Embedded Signup' => false,
            ],
        ]);

        $truePlanId = $this->insertPlan([
            'addons' => [
                'Embedded Signup' => true,
            ],
        ]);

        $migration = require base_path('database/migrations/2026_02_26_120000_backfill_embedded_signup_in_subscription_plans.php');
        $migration->up();

        $falseMetadata = json_decode(DB::table('subscription_plans')->where('id', $falsePlanId)->value('metadata'), true);
        $trueMetadata = json_decode(DB::table('subscription_plans')->where('id', $truePlanId)->value('metadata'), true);

        $this->assertFalse($falseMetadata['addons']['Embedded Signup']);
        $this->assertTrue($trueMetadata['addons']['Embedded Signup']);
    }

    private function insertPlan(array $metadata): int
    {
        return (int) DB::table('subscription_plans')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Plan '.Str::random(6),
            'price' => 99.99,
            'period' => 'monthly',
            'metadata' => json_encode($metadata),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
