<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckSubscriptionStatus;
use App\Models\Addon;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserAiKeyPolicyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_setup_rejects_activation_when_policy_requires_missing_global_key(): void
    {
        [$user, $organization] = $this->createUserContext();

        Setting::updateOrCreate(['key' => 'ai_key_policy'], ['value' => 'global_only']);
        Setting::updateOrCreate(['key' => 'ai_allow_org_override'], ['value' => 0]);
        Setting::where('key', 'ai_global_api_key_encrypted')->delete();

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/automation/ai/setup', [
                'active' => true,
                'api_key' => 'sk-org-should-not-work',
                'key_source' => 'organization',
                'model' => 'gpt-4o-mini',
                'allow_audio_response' => false,
                'max_tokens' => 512,
                'temperature' => 0.7,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status', function ($status): bool {
            return is_array($status)
                && ($status['type'] ?? null) === 'error'
                && ($status['message'] ?? null) === __('AI API key is missing for the selected key policy.');
        });
    }

    public function test_setup_uses_global_source_when_policy_is_global_only(): void
    {
        [$user, $organization] = $this->createUserContext();

        Setting::updateOrCreate(['key' => 'ai_key_policy'], ['value' => 'global_only']);
        Setting::updateOrCreate(['key' => 'ai_allow_org_override'], ['value' => 0]);
        Setting::updateOrCreate(
            ['key' => 'ai_global_api_key_encrypted'],
            ['value' => Crypt::encryptString('sk-global-ready')]
        );

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/automation/ai/setup', [
                'active' => true,
                'api_key' => 'sk-org-ignored',
                'key_source' => 'organization',
                'model' => 'gpt-4o-mini',
                'allow_audio_response' => false,
                'max_tokens' => 512,
                'temperature' => 0.7,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status', function ($status): bool {
            return is_array($status) && ($status['type'] ?? null) === 'success';
        });

        $organization->refresh();
        $metadata = json_decode($organization->metadata ?? '{}', true);

        $this->assertSame('global', data_get($metadata, 'ai.key_source'));
        $this->assertArrayNotHasKey('api_key', $metadata['ai'] ?? []);
    }

    public function test_settings_page_locks_organization_key_when_plan_disables_it_under_hybrid_policy(): void
    {
        [$user, $organization] = $this->createUserContext([
            'ai_organization_key_enabled' => 0,
        ]);

        Setting::updateOrCreate(['key' => 'ai_key_policy'], ['value' => 'hybrid']);
        Setting::updateOrCreate(['key' => 'ai_allow_org_override'], ['value' => 1]);
        Setting::updateOrCreate(
            ['key' => 'ai_global_api_key_encrypted'],
            ['value' => Crypt::encryptString('sk-global-ready')]
        );

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/automation/ai');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('settings.metadata', function ($metadata): bool {
                $decoded = json_decode((string) $metadata, true);

                return is_array($decoded)
                    && data_get($decoded, 'ai.key_policy') === 'hybrid'
                    && data_get($decoded, 'ai.can_set_org_key') === false
                    && data_get($decoded, 'ai.organization_key_allowed') === false
                    && data_get($decoded, 'ai.organization_key_locked') === true
                    && data_get($decoded, 'ai.effective_key_source') === 'global';
            })
        );
    }

    public function test_settings_page_includes_ai_usage_summary_notices_when_ai_billing_is_enabled(): void
    {
        [$user, $organization] = $this->createUserContext([
            'ai_text_response_limit' => 4,
            'ai_audio_response_limit' => 2,
            'ai_system_key_monthly_quota' => 10,
            'ai_organization_key_enabled' => 1,
        ], [
            'ai' => [
                'key_source' => 'global',
            ],
        ]);

        Setting::updateOrCreate(['key' => 'enable_ai_billing'], ['value' => 1]);
        Setting::updateOrCreate(['key' => 'ai_key_policy'], ['value' => 'hybrid']);
        Setting::updateOrCreate(['key' => 'ai_allow_org_override'], ['value' => 1]);
        Setting::updateOrCreate(
            ['key' => 'ai_global_api_key_encrypted'],
            ['value' => Crypt::encryptString('sk-global-ready')]
        );

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/automation/ai');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('aiUsageSummary', function ($summary): bool {
                $metrics = collect($summary['metrics'] ?? []);
                $notices = collect($summary['notices'] ?? []);

                return $metrics->contains(fn ($metric) => ($metric['key'] ?? null) === 'ai_system_key')
                    && $notices->contains(fn ($notice) => ($notice['key'] ?? null) === 'ai_usage_scope');
            })
        );
    }

    public function test_setup_falls_back_to_global_source_when_plan_disables_organization_key_under_hybrid_policy(): void
    {
        [$user, $organization] = $this->createUserContext([
            'ai_organization_key_enabled' => 0,
        ]);

        Setting::updateOrCreate(['key' => 'ai_key_policy'], ['value' => 'hybrid']);
        Setting::updateOrCreate(['key' => 'ai_allow_org_override'], ['value' => 1]);
        Setting::updateOrCreate(
            ['key' => 'ai_global_api_key_encrypted'],
            ['value' => Crypt::encryptString('sk-global-ready')]
        );

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/automation/ai/setup', [
                'active' => true,
                'api_key' => 'sk-org-ignored',
                'key_source' => 'organization',
                'model' => 'gpt-4o-mini',
                'allow_audio_response' => false,
                'max_tokens' => 512,
                'temperature' => 0.7,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status', function ($status): bool {
            return is_array($status) && ($status['type'] ?? null) === 'success';
        });

        $organization->refresh();
        $metadata = json_decode($organization->metadata ?? '{}', true);

        $this->assertSame('global', data_get($metadata, 'ai.key_source'));
        $this->assertArrayNotHasKey('api_key', $metadata['ai'] ?? []);
        $this->assertArrayNotHasKey('api_key_encrypted', $metadata['ai'] ?? []);
    }

    public function test_setup_allows_organization_source_when_plan_enables_it_under_hybrid_policy(): void
    {
        [$user, $organization] = $this->createUserContext([
            'ai_organization_key_enabled' => 1,
        ]);

        Setting::updateOrCreate(['key' => 'ai_key_policy'], ['value' => 'hybrid']);
        Setting::updateOrCreate(['key' => 'ai_allow_org_override'], ['value' => 1]);
        Setting::where('key', 'ai_global_api_key_encrypted')->delete();

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/automation/ai/setup', [
                'active' => true,
                'api_key' => 'sk-org-allowed',
                'key_source' => 'organization',
                'model' => 'gpt-4o-mini',
                'allow_audio_response' => false,
                'max_tokens' => 512,
                'temperature' => 0.7,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status', function ($status): bool {
            return is_array($status) && ($status['type'] ?? null) === 'success';
        });

        $organization->refresh();
        $metadata = json_decode($organization->metadata ?? '{}', true);

        $this->assertSame('organization', data_get($metadata, 'ai.key_source'));
        $this->assertSame('sk-org-allowed', Crypt::decryptString((string) data_get($metadata, 'ai.api_key_encrypted')));
    }

    private function createUserContext(array $planMetadataOverrides = [], array $organizationMetadata = []): array
    {
        $user = User::create([
            'first_name' => 'Policy',
            'last_name' => 'Tester',
            'email' => 'ai-policy+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $organization = Organization::create([
            'identifier' => 'org-'.Str::lower(Str::random(8)),
            'name' => 'Org '.Str::random(5),
            'created_by' => $user->id,
            'metadata' => json_encode($organizationMetadata),
        ]);

        $ownerRole = OrganizationRole::firstOrCreate(
            ['organization_id' => null, 'name' => 'Owner'],
            ['description' => __('Universal owner role'), 'permissions' => ['*']]
        );

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $addon = Addon::firstOrCreate(
            ['name' => 'AI Assistant'],
            [
                'uuid' => (string) Str::uuid(),
                'category' => 'Automation',
                'logo' => 'ai.svg',
                'description' => __('AI assistant module'),
                'metadata' => json_encode(['name' => 'IntelliReply']),
                'status' => 1,
                'is_active' => 1,
                'is_plan_restricted' => 1,
            ]
        );

        $addon->update(['status' => 1, 'is_active' => 1, 'is_plan_restricted' => 1]);

        $planMetadata = array_replace_recursive([
            'addons' => ['AI Assistant' => true],
            'ai_organization_key_enabled' => 1,
        ], $planMetadataOverrides);

        $plan = SubscriptionPlan::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Plan '.Str::random(5),
            'price' => 49.99,
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
            'start_date' => now(),
            'valid_until' => now()->addMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $organization];
    }
}
