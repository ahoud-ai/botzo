<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiAssistantPlanBackfillTest extends TestCase
{
    use DatabaseTransactions;

    public function test_backfill_adds_ai_assistant_flag_when_missing(): void
    {
        $planId = $this->insertPlan([
            'addons' => [
                'Embedded Signup' => true,
            ],
        ]);

        $migration = require base_path('database/migrations/2026_03_03_130000_backfill_ai_assistant_in_subscription_plans.php');
        $migration->up();

        $metadata = json_decode(DB::table('subscription_plans')->where('id', $planId)->value('metadata'), true);

        $this->assertTrue($metadata['addons']['AI Assistant'] ?? false);
    }

    public function test_backfill_does_not_override_existing_ai_assistant_value(): void
    {
        $falsePlanId = $this->insertPlan([
            'addons' => [
                'AI Assistant' => false,
            ],
        ]);

        $truePlanId = $this->insertPlan([
            'addons' => [
                'AI Assistant' => true,
            ],
        ]);

        $migration = require base_path('database/migrations/2026_03_03_130000_backfill_ai_assistant_in_subscription_plans.php');
        $migration->up();

        $falseMetadata = json_decode(DB::table('subscription_plans')->where('id', $falsePlanId)->value('metadata'), true);
        $trueMetadata = json_decode(DB::table('subscription_plans')->where('id', $truePlanId)->value('metadata'), true);

        $this->assertFalse($falseMetadata['addons']['AI Assistant']);
        $this->assertTrue($trueMetadata['addons']['AI Assistant']);
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
