<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use App\Services\IntelliReply\AiKeyResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiKeyResolverTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hybrid_policy_prefers_organization_key_then_fallbacks_to_global(): void
    {
        $this->setPolicy('hybrid', 1, 'sk-global-key');
        $resolver = app(AiKeyResolver::class);

        $withOrgKey = $resolver->resolveForOrganization([
            'ai' => [
                'api_key_encrypted' => Crypt::encryptString('sk-org-key'),
            ],
        ], 'auto');

        $this->assertSame('sk-org-key', $withOrgKey['key']);
        $this->assertSame('organization', $withOrgKey['source']);

        $withoutOrgKey = $resolver->resolveForOrganization([
            'ai' => [],
        ], 'auto');

        $this->assertSame('sk-global-key', $withoutOrgKey['key']);
        $this->assertSame('global', $withoutOrgKey['source']);
    }

    public function test_global_only_policy_ignores_organization_key(): void
    {
        $this->setPolicy('global_only', 1, 'sk-global-key');
        $resolver = app(AiKeyResolver::class);

        $bundle = $resolver->resolveForOrganization([
            'ai' => [
                'api_key_encrypted' => Crypt::encryptString('sk-org-key'),
            ],
        ], 'organization');

        $this->assertSame('global_only', $bundle['policy']);
        $this->assertSame('sk-global-key', $bundle['key']);
        $this->assertSame('global', $bundle['source']);
    }

    public function test_plan_can_disable_organization_key_override(): void
    {
        $organization = $this->createOrganizationWithPlan([
            'addons' => [
                'AI Assistant' => true,
            ],
            'ai_organization_key_enabled' => 0,
        ]);

        $this->setPolicy('hybrid', 1, 'sk-global-key');
        $resolver = app(AiKeyResolver::class);

        $bundle = $resolver->resolveForOrganization([
            'ai' => [
                'api_key_encrypted' => Crypt::encryptString('sk-org-key'),
            ],
        ], 'organization', $organization->id);

        $this->assertFalse($bundle['organization_key_allowed']);
        $this->assertSame('sk-global-key', $bundle['key']);
        $this->assertSame('global', $bundle['source']);
        $this->assertFalse($bundle['has_org_key']);
    }

    private function setPolicy(string $policy, int $allowOverride, ?string $globalKey = null): void
    {
        Setting::updateOrCreate(['key' => 'ai_key_policy'], ['value' => $policy]);
        Setting::updateOrCreate(['key' => 'ai_allow_org_override'], ['value' => $allowOverride]);

        Setting::where('key', 'ai_global_api_key_encrypted')->delete();
        if ($globalKey !== null) {
            Setting::updateOrCreate(
                ['key' => 'ai_global_api_key_encrypted'],
                ['value' => Crypt::encryptString($globalKey)]
            );
        }
    }

    private function createOrganizationWithPlan(array $planMetadata): Organization
    {
        $user = User::query()->create([
            'first_name' => 'AI',
            'last_name' => 'Owner',
            'email' => 'ai-key+'.Str::random(8).'@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $organization = Organization::query()->create([
            'name' => 'AI Workspace '.Str::random(4),
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => $user->id,
            'organization_type' => 'main',
            'parent_organization_id' => null,
            'metadata' => json_encode(['addons' => []]),
        ]);

        Team::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'organization_role_id' => $this->ownerRole()->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $plan = SubscriptionPlan::query()->create([
            'name' => 'AI Plan '.Str::random(4),
            'name_ar' => 'خطة ذكاء '.Str::random(4),
            'name_en' => 'AI Plan '.Str::random(4),
            'price' => 99,
            'period' => 'monthly',
            'status' => 'active',
            'metadata' => json_encode(array_merge([
                'addons' => [],
                'ai_organization_key_enabled' => 1,
            ], $planMetadata)),
        ]);

        Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        return $organization;
    }

    private function ownerRole(): OrganizationRole
    {
        return OrganizationRole::query()->firstOrCreate(
            [
                'organization_id' => null,
                'name' => 'Owner',
            ],
            [
                'description' => 'Owner role for AI key resolver tests',
                'permissions' => ['*'],
            ]
        );
    }
}
