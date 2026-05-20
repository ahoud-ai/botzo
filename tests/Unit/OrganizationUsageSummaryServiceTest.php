<?php

namespace Tests\Unit;

use App\Models\Addon;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use App\Services\IntelliReply\AiUsageLimiterService;
use App\Services\OrganizationUsageSummaryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrganizationUsageSummaryServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_build_reports_metric_statuses_remaining_and_percentage(): void
    {
        $owner = $this->createUser();
        $parent = $this->createOrganization($owner, ['name' => 'Parent Workspace']);
        $branch = $this->createOrganization($owner, [
            'name' => 'Branch Workspace',
            'organization_type' => 'branch',
            'parent_organization_id' => $parent->id,
        ]);

        $this->attachOwnerToOrganization($owner, $parent);
        $this->attachUserToOrganization($this->createUser(), $parent);
        $this->attachUserToOrganization($this->createUser(), $branch);
        $this->attachUserToOrganization($this->createUser(), $branch);

        $plan = $this->createPlan([
            'branches_limit' => 1,
            'team_limit' => 5,
            'contacts_limit' => -1,
            'campaign_limit' => 10,
            'message_limit' => 100,
            'canned_replies_limit' => 10,
            'addons' => [],
        ]);

        Subscription::create([
            'organization_id' => $parent->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $summary = app(OrganizationUsageSummaryService::class)->build($parent->id, [$parent->id, $branch->id]);
        $metrics = collect($summary['metrics'])->keyBy('key');

        $this->assertSame('Current month', $summary['period_label']);
        $this->assertSame(2, $metrics['branches']['used']);
        $this->assertSame(1, $metrics['branches']['limit']);
        $this->assertSame(0, $metrics['branches']['remaining']);
        $this->assertSame(100, $metrics['branches']['percentage']);
        $this->assertSame('exceeded', $metrics['branches']['status']);

        $this->assertSame(4, $metrics['users']['used']);
        $this->assertSame(5, $metrics['users']['limit']);
        $this->assertSame(1, $metrics['users']['remaining']);
        $this->assertSame(80, $metrics['users']['percentage']);
        $this->assertSame('warning', $metrics['users']['status']);

        $this->assertSame(-1, $metrics['contacts']['limit']);
        $this->assertNull($metrics['contacts']['remaining']);
        $this->assertNull($metrics['contacts']['percentage']);
        $this->assertSame('unlimited', $metrics['contacts']['status']);
    }

    public function test_build_uses_parent_limits_and_family_scope_when_called_from_branch(): void
    {
        $owner = $this->createUser();
        $parent = $this->createOrganization($owner, ['name' => 'Parent Workspace']);
        $branch = $this->createOrganization($owner, [
            'name' => 'Branch Workspace',
            'organization_type' => 'branch',
            'parent_organization_id' => $parent->id,
        ]);

        $this->attachOwnerToOrganization($owner, $parent);
        $this->attachOwnerToOrganization($owner, $branch);
        $this->attachUserToOrganization($this->createUser(), $branch);

        $plan = $this->createPlan([
            'branches_limit' => 3,
            'team_limit' => 4,
            'contacts_limit' => 50,
            'campaign_limit' => 10,
            'message_limit' => 100,
            'canned_replies_limit' => 10,
            'addons' => [],
        ]);

        Subscription::create([
            'organization_id' => $parent->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $summary = app(OrganizationUsageSummaryService::class)->build($branch->id);
        $metrics = collect($summary['metrics'])->keyBy('key');

        $this->assertSame(2, $metrics['branches']['used']);
        $this->assertSame(3, $metrics['branches']['limit']);
        $this->assertSame(1, $metrics['branches']['remaining']);
        $this->assertSame('healthy', $metrics['branches']['status']);

        $this->assertSame(2, $metrics['users']['used']);
        $this->assertSame(4, $metrics['users']['limit']);
        $this->assertSame(2, $metrics['users']['remaining']);
        $this->assertSame('healthy', $metrics['users']['status']);
    }

    public function test_build_only_shows_addon_usage_metrics_when_related_addons_are_enabled(): void
    {
        Setting::updateOrCreate(['key' => 'enable_ai_billing'], ['value' => '1']);

        $owner = $this->createUser();
        $organization = $this->createOrganization($owner, ['name' => 'Usage Workspace']);
        $this->attachOwnerToOrganization($owner, $organization);

        $plan = $this->createPlan([
            'branches_limit' => 1,
            'team_limit' => 5,
            'contacts_limit' => 50,
            'campaign_limit' => 10,
            'message_limit' => 100,
            'canned_replies_limit' => 10,
            'ai_text_response_limit' => 25,
            'ai_audio_response_limit' => 10,
            'flow_builder_active_flows_limit' => 3,
            'flow_builder_monthly_runs_limit' => 100,
            'addons' => [],
        ]);

        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $withoutAddons = collect(app(OrganizationUsageSummaryService::class)->build($organization->id)['metrics'])->keyBy('key');

        $this->assertFalse($withoutAddons->has('ai_text'));
        $this->assertFalse($withoutAddons->has('ai_audio'));
        $this->assertFalse($withoutAddons->has('active_flows'));
        $this->assertFalse($withoutAddons->has('flow_runs'));

        $this->createAddon('AI Assistant');
        $this->createAddon('Flow builder');

        $plan->update([
            'metadata' => json_encode([
                'branches_limit' => 1,
                'team_limit' => 5,
                'contacts_limit' => 50,
                'campaign_limit' => 10,
                'message_limit' => 100,
                'canned_replies_limit' => 10,
                'ai_text_response_limit' => 25,
                'ai_audio_response_limit' => 10,
                'flow_builder_active_flows_limit' => 3,
                'flow_builder_monthly_runs_limit' => 100,
                'addons' => [
                    'AI Assistant' => '1',
                    'Flow builder' => true,
                ],
            ]),
        ]);

        $withAddons = collect(app(OrganizationUsageSummaryService::class)->build($organization->id)['metrics'])->keyBy('key');

        $this->assertTrue($withAddons->has('ai_text'));
        $this->assertTrue($withAddons->has('ai_audio'));
        $this->assertTrue($withAddons->has('ai_system_key'));
        $this->assertTrue($withAddons->has('active_flows'));
        $this->assertTrue($withAddons->has('flow_runs'));
        $this->assertSame(25, $withAddons['ai_text']['limit']);
        $this->assertSame(10, $withAddons['ai_audio']['limit']);
        $this->assertSame(-1, $withAddons['ai_system_key']['limit']);
        $this->assertSame(3, $withAddons['active_flows']['limit']);
        $this->assertSame(100, $withAddons['flow_runs']['limit']);
    }

    public function test_addon_usage_metrics_require_active_global_addon_even_when_plan_entitled(): void
    {
        Setting::updateOrCreate(['key' => 'enable_ai_billing'], ['value' => '1']);

        $this->createAddon('AI Assistant', false);
        $this->createAddon('Flow builder', false);

        $owner = $this->createUser();
        $organization = $this->createOrganization($owner, ['name' => 'Disabled Addon Workspace']);
        $this->attachOwnerToOrganization($owner, $organization);

        $plan = $this->createPlan([
            'branches_limit' => 1,
            'team_limit' => 5,
            'contacts_limit' => 50,
            'campaign_limit' => 10,
            'message_limit' => 100,
            'canned_replies_limit' => 10,
            'ai_text_response_limit' => 25,
            'ai_audio_response_limit' => 10,
            'flow_builder_active_flows_limit' => 3,
            'flow_builder_monthly_runs_limit' => 100,
            'addons' => [
                'AI Assistant' => true,
                'Flow builder' => true,
            ],
        ]);

        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $metrics = collect(app(OrganizationUsageSummaryService::class)->build($organization->id)['metrics'])->keyBy('key');

        $this->assertFalse($metrics->has('ai_text'));
        $this->assertFalse($metrics->has('ai_audio'));
        $this->assertFalse($metrics->has('ai_system_key'));
        $this->assertFalse($metrics->has('active_flows'));
        $this->assertFalse($metrics->has('flow_runs'));
    }

    public function test_trial_override_shows_addon_usage_metrics_when_global_addon_is_active(): void
    {
        $this->createAddon('Flow builder');
        Setting::updateOrCreate(
            ['key' => 'trial_addons'],
            ['value' => json_encode(['Flow builder' => true])]
        );

        $owner = $this->createUser();
        $organization = $this->createOrganization($owner, ['name' => 'Trial Addon Workspace']);
        $this->attachOwnerToOrganization($owner, $organization);

        $plan = $this->createPlan([
            'branches_limit' => 1,
            'team_limit' => 5,
            'contacts_limit' => 50,
            'campaign_limit' => 10,
            'message_limit' => 100,
            'canned_replies_limit' => 10,
            'flow_builder_active_flows_limit' => 4,
            'flow_builder_monthly_runs_limit' => 250,
            'addons' => [
                'Flow builder' => false,
            ],
        ]);

        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'trial',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addDays(14),
        ]);

        $metrics = collect(app(OrganizationUsageSummaryService::class)->build($organization->id)['metrics'])->keyBy('key');

        $this->assertTrue($metrics->has('active_flows'));
        $this->assertTrue($metrics->has('flow_runs'));
        $this->assertSame(4, $metrics['active_flows']['limit']);
        $this->assertSame(250, $metrics['flow_runs']['limit']);
    }

    public function test_build_includes_ai_notices_and_global_quota_status_when_ai_usage_is_tracked(): void
    {
        Setting::updateOrCreate(['key' => 'enable_ai_billing'], ['value' => '1']);
        $this->createAddon('AI Assistant');

        $owner = $this->createUser();
        $organization = $this->createOrganization($owner, ['name' => 'AI Usage Workspace']);
        $this->attachOwnerToOrganization($owner, $organization);

        $plan = $this->createPlan([
            'branches_limit' => 1,
            'team_limit' => 5,
            'contacts_limit' => 50,
            'campaign_limit' => 10,
            'message_limit' => 100,
            'canned_replies_limit' => 10,
            'ai_text_response_limit' => 5,
            'ai_audio_response_limit' => 3,
            'ai_system_key_monthly_quota' => 2,
            'addons' => [
                'AI Assistant' => true,
            ],
        ]);

        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $limiter = app(AiUsageLimiterService::class);
        $this->assertTrue($limiter->consumeText($organization->id, 'global'));
        $this->assertTrue($limiter->consumeAudio($organization->id, 'global'));

        $summary = app(OrganizationUsageSummaryService::class)->build($organization->id);
        $metrics = collect($summary['metrics'])->keyBy('key');
        $notices = collect($summary['notices'])->keyBy('key');

        $this->assertSame(1, $metrics['ai_text']['used']);
        $this->assertSame(5, $metrics['ai_text']['limit']);
        $this->assertSame('Current billing-cycle AI text responses that use the global AI key.', $metrics['ai_text']['helper']);
        $this->assertSame(1, $metrics['ai_audio']['used']);
        $this->assertSame(3, $metrics['ai_audio']['limit']);
        $this->assertSame(2, $metrics['ai_system_key']['used']);
        $this->assertSame(2, $metrics['ai_system_key']['limit']);
        $this->assertSame('exceeded', $metrics['ai_system_key']['status']);

        $this->assertTrue($notices->has('ai_usage_scope'));
        $this->assertTrue($notices->has('ai_global_quota_exceeded'));
    }

    private function createUser(): User
    {
        return User::create([
            'first_name' => 'Usage',
            'last_name' => 'Tester',
            'email' => 'usage+'.Str::random(8).'@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);
    }

    private function createOrganization(User $creator, array $attributes = []): Organization
    {
        return Organization::create(array_merge([
            'name' => 'Organization '.Str::random(4),
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => $creator->id,
            'organization_type' => 'main',
            'parent_organization_id' => null,
            'metadata' => json_encode([
                'addons' => [],
            ]),
        ], $attributes));
    }

    private function createPlan(array $metadata): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Plan '.Str::random(4),
            'name_ar' => 'خطة '.Str::random(4),
            'name_en' => 'Plan '.Str::random(4),
            'price' => 99.00,
            'period' => 'monthly',
            'metadata' => json_encode($metadata),
            'status' => 'active',
        ]);
    }

    private function createAddon(string $name, bool $active = true): void
    {
        Addon::updateOrCreate(
            ['name' => $name],
            [
                'uuid' => Addon::query()->where('name', $name)->value('uuid') ?: (string) Str::uuid(),
                'category' => 'business',
                'logo' => Str::slug($name).'.svg',
                'description' => $name.' addon',
                'metadata' => json_encode(['name' => $name]),
                'status' => 1,
                'is_active' => $active ? 1 : 0,
                'is_plan_restricted' => 1,
            ]
        );
    }

    private function attachOwnerToOrganization(User $user, Organization $organization): void
    {
        $this->attachUserToOrganization($user, $organization);
    }

    private function attachUserToOrganization(User $user, Organization $organization): void
    {
        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'organization_role_id' => $this->ownerRole()->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);
    }

    private function ownerRole(): OrganizationRole
    {
        return OrganizationRole::firstOrCreate(
            [
                'organization_id' => null,
                'name' => 'Owner',
            ],
            [
                'description' => 'Owner role for usage summary tests',
                'permissions' => ['*'],
            ]
        );
    }
}
