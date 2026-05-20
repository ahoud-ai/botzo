<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\IntelliReply\AiUsageLimiterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiUsageLimiterServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('organization_ai_usage_counters')) {
            $migration = require base_path('database/migrations/2026_03_03_160200_create_organization_ai_usage_counters_table.php');
            $migration->up();
        }
    }

    public function test_text_limit_is_enforced_within_subscription_cycle(): void
    {
        [$organization] = $this->createSubscriptionContext([
            'ai_text_response_limit' => 2,
            'ai_audio_response_limit' => 1,
        ]);

        Setting::updateOrCreate(['key' => 'enable_ai_billing'], ['value' => 1]);

        $service = app(AiUsageLimiterService::class);

        $this->assertTrue($service->canUseText($organization->id, 'global'));
        $this->assertTrue($service->consumeText($organization->id, 'global'));
        $this->assertTrue($service->consumeText($organization->id, 'global'));
        $this->assertFalse($service->canUseText($organization->id, 'global'));
        $this->assertFalse($service->consumeText($organization->id, 'global'));
    }

    public function test_limits_are_bypassed_when_ai_billing_is_disabled(): void
    {
        [$organization] = $this->createSubscriptionContext([
            'ai_text_response_limit' => 0,
            'ai_audio_response_limit' => 0,
        ]);

        Setting::updateOrCreate(['key' => 'enable_ai_billing'], ['value' => 0]);

        $service = app(AiUsageLimiterService::class);

        $this->assertTrue($service->canUseText($organization->id));
        $this->assertTrue($service->consumeText($organization->id));
        $this->assertTrue($service->canUseAudio($organization->id));
        $this->assertTrue($service->consumeAudio($organization->id));
    }

    public function test_organization_key_bypasses_billable_limits_and_does_not_increment_counters(): void
    {
        [$organization] = $this->createSubscriptionContext([
            'ai_text_response_limit' => 0,
            'ai_audio_response_limit' => 0,
            'ai_system_key_monthly_quota' => 0,
        ]);

        Setting::updateOrCreate(['key' => 'enable_ai_billing'], ['value' => 1]);

        $service = app(AiUsageLimiterService::class);

        $this->assertTrue($service->canUseText($organization->id, 'organization'));
        $this->assertTrue($service->consumeText($organization->id, 'organization'));
        $this->assertTrue($service->canUseAudio($organization->id, 'organization'));
        $this->assertTrue($service->consumeAudio($organization->id, 'organization'));

        $snapshot = $service->getSnapshot($organization->id);

        $this->assertSame(0, $snapshot['text_count']);
        $this->assertSame(0, $snapshot['audio_count']);
        $this->assertSame(0, $snapshot['system_key_count']);
    }

    private function createSubscriptionContext(array $planMetadata): array
    {
        $user = User::create([
            'first_name' => 'Limiter',
            'last_name' => 'Tester',
            'email' => 'limiter+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $organization = Organization::create([
            'identifier' => 'org-'.Str::lower(Str::random(8)),
            'name' => 'Org '.Str::random(5),
            'created_by' => $user->id,
            'metadata' => json_encode([]),
        ]);

        $plan = SubscriptionPlan::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Plan '.Str::random(5),
            'price' => 100,
            'period' => 'monthly',
            'metadata' => json_encode($planMetadata),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Subscription::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->startOfDay(),
            'valid_until' => now()->addMonth()->endOfDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$organization, $plan];
    }
}
