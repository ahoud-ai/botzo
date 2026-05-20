<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\AutomationFlow;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\AutomationFlows\AutomationFlowAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class AutomationFlowFeatureAccessTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_flows_index_redirects_to_basic_when_runtime_flag_is_disabled(): void
    {
        config()->set('automation_flows.enabled', false);

        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/automation/flows')
            ->assertRedirect('/automation/basic');
    }

    public function test_flow_sub_routes_return_not_found_when_runtime_flag_is_disabled(): void
    {
        config()->set('automation_flows.enabled', false);

        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/automation/flows/example-flow')
            ->assertNotFound();
    }

    public function test_flows_index_redirects_to_basic_when_addon_is_not_active_for_the_organization(): void
    {
        config()->set('automation_flows.enabled', true);

        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id, addonActive: false);

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/automation/flows')
            ->assertRedirect('/automation/basic');
    }

    public function test_flow_sub_routes_are_forbidden_when_plan_does_not_include_the_addon(): void
    {
        config()->set('automation_flows.enabled', true);

        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id, addonActive: true, planEntitled: false);

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/automation/flows/example-flow')
            ->assertForbidden();
    }

    public function test_flow_access_uses_the_active_subscription_instead_of_the_first_previous_row(): void
    {
        config()->set('automation_flows.enabled', true);

        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id, addonActive: true, planEntitled: false);

        Subscription::query()
            ->where('organization_id', $organization->id)
            ->update([
                'valid_until' => now()->addDays(3),
            ]);

        $entitledPlan = SubscriptionPlan::create([
            'name' => 'Entitled Flow Builder Plan',
            'price' => 20,
            'period' => 'monthly',
            'metadata' => json_encode([
                'addons' => [
                    'Flow builder' => true,
                ],
            ]),
            'status' => 'active',
        ]);

        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $entitledPlan->id,
            'payment_details' => null,
            'start_date' => now(),
            'valid_until' => now()->addMonth(),
            'status' => 'active',
        ]);

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/automation/flows')
            ->assertOk();
    }

    public function test_flows_index_stays_available_when_builder_tables_are_missing_but_base_tables_exist(): void
    {
        config()->set('automation_flows.enabled', true);

        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $this->createFlow($organization->id, $user->id);
        $this->mockBuilderSchemaIncomplete($organization->id);

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/automation/flows')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('User/Automation/Flows/Index')
                ->where('flowBuilderEnabled', true)
                ->where('flowBuilderReadiness.builder_ready', false)
                ->where('flowBuilderReadiness.builder_schema_ready', false)
            );
    }

    public function test_flow_show_redirects_to_index_when_builder_tables_are_missing(): void
    {
        config()->set('automation_flows.enabled', true);

        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($organization->id, $user->id);
        $this->mockBuilderSchemaIncomplete($organization->id);

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get("/automation/flows/{$flow->uuid}")
            ->assertRedirect('/automation/flows');
    }

    public function test_flow_mutation_routes_return_conflict_when_builder_tables_are_missing(): void
    {
        config()->set('automation_flows.enabled', true);

        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $this->mockBuilderSchemaIncomplete($organization->id);

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson('/automation/flows', [
                'name' => 'Blocked automation',
                'description' => __('Should not be created while builder schema is incomplete.'),
                'goal_preset' => 'sales_qualification',
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'flow_builder_schema_incomplete');
    }

    private function enableFlowBuilderForOrganization(int $organizationId, bool $addonActive = true, bool $planEntitled = true): void
    {
        Addon::updateOrCreate(
            ['name' => 'Flow builder'],
            [
                'uuid' => Addon::query()->where('name', 'Flow builder')->value('uuid') ?: (string) Str::uuid(),
                'category' => 'automation',
                'logo' => 'flow_builder.png',
                'description' => __('Flow Builder v2'),
                'metadata' => json_encode(['name' => 'FlowBuilder']),
                'status' => 1,
                'is_active' => $addonActive ? 1 : 0,
                'is_plan_restricted' => 1,
            ]
        );

        $this->createActiveSubscription($organizationId, [
            'addons' => [
                'Flow builder' => $planEntitled,
            ],
        ]);
    }

    private function createFlow(int $organizationId, int $userId): AutomationFlow
    {
        return AutomationFlow::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'name' => 'Readiness test flow',
            'description' => __('Schema readiness coverage flow.'),
            'goal_preset' => 'sales_qualification',
            'channel' => 'whatsapp',
            'trigger_type' => 'incoming_whatsapp_message',
            'status' => 'draft',
            'graph_json' => [
                'start_node_id' => 'trigger-1',
                'nodes' => [
                    [
                        'id' => 'trigger-1',
                        'type' => 'trigger',
                        'position' => ['x' => 80, 'y' => 120],
                        'config' => ['match_mode' => 'any_incoming', 'keywords' => []],
                        'ui' => ['expanded' => true],
                    ],
                ],
                'edges' => [],
            ],
            'ui_json' => [],
            'created_by' => $userId,
            'updated_by' => $userId,
            'has_unpublished_changes' => true,
        ]);
    }

    private function mockBuilderSchemaIncomplete(int $organizationId): void
    {
        $mock = \Mockery::mock(AutomationFlowAccessService::class);
        $mock->shouldReceive('runtimeEnabled')->andReturn(true);
        $mock->shouldReceive('addonEnabledForOrganization')->with($organizationId)->andReturn(true);
        $mock->shouldReceive('readinessReport')->with($organizationId)->andReturn([
            'runtime_enabled' => true,
            'addon_enabled' => true,
            'base_schema_ready' => true,
            'builder_schema_ready' => false,
            'surface_ready' => true,
            'builder_ready' => false,
            'missing_base_tables' => [],
            'missing_builder_tables' => ['automation_flow_assets', 'automation_flow_node_secrets'],
            'missing_tables' => ['automation_flow_assets', 'automation_flow_node_secrets'],
            'message' => __('Flow Builder setup is incomplete. Run the latest migrations and try again.'),
        ]);
        $this->instance(AutomationFlowAccessService::class, $mock);
    }
}
