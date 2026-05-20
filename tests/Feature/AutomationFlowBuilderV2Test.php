<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\AutomationFlow;
use App\Models\AutomationFlowAsset;
use App\Models\AutomationFlowNodeSecret;
use App\Models\AutomationFlowRun;
use App\Models\AutomationFlowRunStep;
use App\Models\Chat;
use App\Models\ChatTicket;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Team;
use App\Models\User;
use App\Services\AutomationFlows\AutomationFlowRuntimeService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class AutomationFlowBuilderV2Test extends TestCase
{
    use CreatesOrganizationContext;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('automation_flows.enabled', true);
    }

    public function test_flows_index_is_available_when_v2_feature_flag_is_enabled(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);

        $response = $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/automation/flows');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/Automation/Flows/Index')
            ->where('flowBuilderEnabled', true)
            ->where('rows.data', [])
        );
    }

    public function test_builder_page_is_available_for_a_flow(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Builder surface');

        $response = $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get("/automation/flows/{$flow->uuid}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/Automation/Flows/Builder')
            ->where('flow.uuid', $flow->uuid)
            ->where('flowBuilderEnabled', true)
            ->where('flow.ui_json.canvas.expanded_node_id', 'sales-path-1')
            ->where('builder_runtime.builder_policy.channel', 'whatsapp')
            ->where('builder_runtime.builder_policy.whatsapp_only_mode', true)
            ->where('builder_runtime.builder_policy.allow_external_actions', false)
            ->where('builder_runtime.builder_policy.allow_crm_actions', true)
            ->where('builder_runtime.runtime.invalid_reply_default_behavior', 'release_to_fallback')
            ->where('builder_runtime.runtime.invalid_reply_behaviors.0', 'release_to_fallback')
            ->where('builder_runtime.runtime.invalid_reply_behaviors.1', 'repeat_prompt')
            ->where('builder_runtime.runtime.invalid_reply_behaviors.2', 'end_run')
            ->where('builder_runtime.runtime.whatsapp.customer_care_window_hours', 24)
            ->where('builder_runtime.runtime.whatsapp.enforce_customer_care_window', true)
            ->where('builder_runtime.runtime.whatsapp.preview_customer_care_window_open', true)
            ->where('builder_runtime.runtime.whatsapp.on_window_closed', 'fail_run')
            ->where('builder_runtime.handoff_capabilities.ticketing_active', false)
            ->where('builder_runtime.handoff_capabilities.ai_assistant_active', false)
            ->has('plan_limits')
            ->has('plan_limits.active_flows')
            ->has('plan_limits.nodes_per_flow')
            ->has('plan_limits.monthly_runs')
            ->has('assignable_agents')
            ->where('library.0.builder_scope', 'whatsapp_message')
            ->where('library.0.demo_safe', true)
            ->where('library', fn ($library) => collect($library)->contains(
                fn (array $item) => ($item['type'] ?? null) === 'assign_to_agent' && ($item['builder_scope'] ?? null) === 'service_action'
            ))
            ->where('library', fn ($library) => collect($library)->contains(
                fn (array $item) => ($item['type'] ?? null) === 'human_handoff' && ($item['builder_scope'] ?? null) === 'service_action'
            ))
            ->where('library', fn ($library) => collect($library)->contains(
                fn (array $item) => ($item['type'] ?? null) === 'handoff_to_ai_assistant' && ($item['builder_scope'] ?? null) === 'service_action'
            ))
            ->where('library', fn ($library) => collect($library)->doesntContain(
                fn (array $item) => ($item['type'] ?? null) === 'send_email'
            ))
            ->has('validation.errors')
        );
    }

    public function test_store_rejects_unknown_goal_preset(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson('/automation/flows', [
                'name' => 'Broken preset',
                'description' => 'Should fail validation.',
                'goal_preset' => 'unknown_goal',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['goal_preset']);
    }

    public function test_supported_goal_presets_create_ready_to_edit_starter_graphs(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);

        $cases = [
            'sales_qualification' => ['active_node_id' => 'sales-path-1', 'expected_type' => 'send_buttons'],
            'support_routing' => ['active_node_id' => 'support-list-1', 'expected_type' => 'send_list'],
            'appointment_booking' => ['active_node_id' => 'send-text-1', 'expected_type' => 'save_reply_to_field'],
            'seller_intake' => ['active_node_id' => 'send-text-1', 'expected_type' => 'condition'],
        ];

        foreach ($cases as $goalPreset => $expectation) {
            $name = "Starter {$goalPreset}";

            $this->withoutMiddleware()
                ->actingAs($user, 'user')
                ->withSession(['current_organization' => $organization->id])
                ->postJson('/automation/flows', [
                    'name' => $name,
                    'description' => 'Starter flow for testing.',
                    'goal_preset' => $goalPreset,
                ])
                ->assertOk()
                ->assertJson([
                    'status' => 'ok',
                ]);

            $flow = AutomationFlow::query()
                ->where('organization_id', $organization->id)
                ->where('name', $name)
                ->latest('id')
                ->firstOrFail();

            $this->assertSame($goalPreset, $flow->goal_preset);
            $this->assertSame($expectation['active_node_id'], Arr::get($flow->ui_json, 'selection.active_node_id'));
            $this->assertSame($expectation['active_node_id'], Arr::get($flow->ui_json, 'canvas.expanded_node_id'));
            $this->assertContains($expectation['expected_type'], collect($flow->graph_json['nodes'] ?? [])->pluck('type')->all());

            $this->withoutMiddleware()
                ->actingAs($user, 'user')
                ->withSession(['current_organization' => $organization->id])
                ->postJson("/automation/flows/{$flow->uuid}/validate", [
                    'graph_json' => $flow->graph_json,
                ])
                ->assertOk()
                ->assertJson([
                    'valid' => true,
                ]);
        }
    }

    public function test_builder_page_remains_available_when_the_stored_graph_contains_malformed_nodes(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Malformed builder graph');

        $flow->update([
            'graph_json' => [
                'start_node_id' => 'broken-node',
                'nodes' => [
                    ['id' => 'broken-node'],
                    ['type' => 'send_text'],
                ],
                'edges' => [
                    ['source_id' => 'broken-node', 'target_id' => 'missing-node'],
                ],
            ],
        ]);

        $response = $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get("/automation/flows/{$flow->uuid}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/Automation/Flows/Builder')
            ->where('flow.uuid', $flow->uuid)
            ->has('validation.errors')
        );
    }

    public function test_user_can_create_update_validate_preview_publish_pause_and_delete_v2_flow(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);

        $createResponse = $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson('/automation/flows', [
                'name' => 'Lead qualification',
                'description' => __('Capture the lead and guide the next step.'),
                'goal_preset' => 'sales_qualification',
            ]);

        $createResponse->assertOk()
            ->assertJson([
                'status' => 'ok',
            ]);

        $flow = AutomationFlow::query()->where('organization_id', $organization->id)->firstOrFail();
        $createResponse->assertJsonPath('flow_uuid', $flow->uuid);
        $this->assertSame('sales-path-1', Arr::get($flow->ui_json, 'selection.active_node_id'));
        $this->assertSame('sales-path-1', Arr::get($flow->ui_json, 'canvas.expanded_node_id'));
        $this->assertSame('messages', Arr::get($flow->ui_json, 'library.tab'));
        $this->assertSame('inspector', Arr::get($flow->ui_json, 'right_dock.tab'));
        $this->assertSame('whatsapp', Arr::get($flow->ui_json, 'preview.mode'));
        $this->assertFalse(Arr::get($flow->ui_json, 'preview.collapsed'));

        $graph = $this->validGraph();

        $updateResponse = $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->putJson("/automation/flows/{$flow->uuid}", [
                'name' => 'Lead qualification',
                'description' => __('Capture the lead and guide the next step.'),
                'graph_json' => $graph,
                'ui_json' => [
                    'preview' => ['selected_scenario' => 'main'],
                ],
            ]);

        $updateResponse->assertOk()
            ->assertJson([
                'status' => 'ok',
            ]);

        $validateResponse = $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/validate", [
                'graph_json' => $graph,
            ]);

        $validateResponse->assertOk()
            ->assertJson([
                'valid' => true,
            ]);

        $previewResponse = $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/preview", [
                'graph_json' => $graph,
                'focus_node_id' => 'send-text-1',
            ]);

        $previewResponse->assertOk();
        $previewResponse->assertJsonPath('focus_node_id', 'send-text-1');
        $previewResponse->assertJsonPath('steps.0.kind', 'assistant');
        $previewResponse->assertJsonPath('steps.0.label', 'Welcome to Botzo. Tell us what you need and we will guide the next step.');

        $publishResponse = $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/publish");

        $publishResponse->assertOk()
            ->assertJson([
                'status' => 'ok',
            ]);

        $flow->refresh();
        $this->assertSame('published', $flow->status);
        $this->assertFalse($flow->has_unpublished_changes);
        $this->assertNotNull($flow->current_version_id);
        $this->assertSame(1, $flow->versions()->count());

        $pauseResponse = $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/pause", [
                'pause' => true,
            ]);

        $pauseResponse->assertOk()
            ->assertJson([
                'status' => 'ok',
                'flow_status' => 'paused',
            ]);

        $duplicateResponse = $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/duplicate");

        $duplicateResponse->assertOk()
            ->assertJson([
                'status' => 'ok',
            ]);

        $this->assertSame(2, AutomationFlow::query()->where('organization_id', $organization->id)->count());
        $duplicate = AutomationFlow::query()
            ->where('organization_id', $organization->id)
            ->where('uuid', '!=', $flow->uuid)
            ->latest('id')
            ->firstOrFail();
        $duplicateResponse->assertJsonPath('flow_uuid', $duplicate->uuid);
        $this->assertSame('draft', $duplicate->status);
        $this->assertTrue($duplicate->has_unpublished_changes);
        $this->assertSame('Lead qualification Copy', $duplicate->name);
        $this->assertSame('send_text', $duplicate->graph_json['nodes'][1]['type']);

        $deleteResponse = $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->deleteJson("/automation/flows/{$flow->uuid}");

        $deleteResponse->assertOk()
            ->assertJson([
                'status' => 'ok',
            ]);

        $this->assertSoftDeleted('automation_flows', [
            'id' => $flow->id,
        ]);
    }

    public function test_publish_is_blocked_when_email_secrets_are_incomplete(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $this->allowExternalActionsInBuilderPolicy();
        $flow = $this->createFlow($user->id, $organization->id, 'Risky flow');

        $graph = $this->emailGraph();

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->putJson("/automation/flows/{$flow->uuid}", [
                'name' => 'Risky flow',
                'description' => __('Contains blocked steps.'),
                'graph_json' => $graph,
                'ui_json' => ['preview' => ['selected_scenario' => 'main']],
                'node_secrets' => [],
            ])
            ->assertOk();

        $validationResponse = $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/validate", [
                'graph_json' => $graph,
                'node_secrets' => [],
            ]);

        $validationResponse->assertOk()
            ->assertJson([
                'valid' => false,
            ]);

        $this->assertContains('Complete the SMTP settings for this email step before publishing.', $validationResponse->json('errors'));

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/publish")
            ->assertStatus(422)
            ->assertJsonPath('errors.graph.0', 'Complete the SMTP settings for this email step before publishing.');
    }

    public function test_publish_is_blocked_when_a_button_reply_branch_is_missing(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Missing button branch');

        $graph = $this->buttonsGraphWithMissingBranch();

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->putJson("/automation/flows/{$flow->uuid}", [
                'name' => 'Missing button branch',
                'description' => __('Contains an incomplete button path.'),
                'graph_json' => $graph,
                'ui_json' => ['preview' => ['selected_scenario' => 'main']],
                'node_secrets' => [],
            ])
            ->assertOk();

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/validate", [
                'graph_json' => $graph,
            ])
            ->assertOk()
            ->assertJson([
                'valid' => false,
            ])
            ->assertJsonFragment([
                'Connect every button reply to its own next step before publishing.',
            ]);

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/publish")
            ->assertStatus(422)
            ->assertJsonFragment([
                'Connect every button reply to its own next step before publishing.',
            ]);
    }

    public function test_validate_blocks_duplicate_button_ids(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Duplicate buttons');

        $graph = $this->duplicateButtonsGraph();

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/validate", [
                'graph_json' => $graph,
            ])
            ->assertOk()
            ->assertJson([
                'valid' => false,
            ])
            ->assertJsonFragment([
                'Each button reply needs a unique internal ID before publishing.',
            ]);
    }

    public function test_validate_blocks_list_sections_without_titles_and_duplicate_row_ids(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Invalid list');

        $graph = $this->invalidListGraph();

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/validate", [
                'graph_json' => $graph,
            ])
            ->assertOk()
            ->assertJson([
                'valid' => false,
            ])
            ->assertJsonFragment([
                'Every list section needs a title before publishing.',
            ])
            ->assertJsonFragment([
                'Each list choice needs a unique internal ID before publishing.',
            ]);
    }

    public function test_validate_blocks_whatsapp_button_constraints_before_publish(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Button constraints');

        $graph = $this->buttonsRuntimeGraph();
        $graph['nodes'][1]['config']['header'] = str_repeat('H', 61);
        $graph['nodes'][1]['config']['body'] = str_repeat('B', 1025);
        $graph['nodes'][1]['config']['footer'] = str_repeat('F', 61);
        $graph['nodes'][1]['config']['buttons'] = [
            ['id' => 'buy', 'title' => str_repeat('A', 21)],
            ['id' => 'rent', 'title' => 'Rent'],
            ['id' => 'lease', 'title' => 'Lease'],
            ['id' => 'invest', 'title' => 'Invest'],
        ];

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/validate", [
                'graph_json' => $graph,
            ])
            ->assertOk()
            ->assertJson([
                'valid' => false,
            ])
            ->assertJsonFragment([
                'WhatsApp reply buttons support up to 3 choices per message.',
            ])
            ->assertJsonFragment([
                'Interactive headers support up to 60 characters.',
            ])
            ->assertJsonFragment([
                'Interactive message bodies support up to 1024 characters.',
            ])
            ->assertJsonFragment([
                'Interactive footers support up to 60 characters.',
            ])
            ->assertJsonFragment([
                'Reply button 1 supports up to 20 characters.',
            ]);
    }

    public function test_validate_blocks_whatsapp_list_constraints_before_publish(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'List constraints');

        $graph = $this->listRuntimeGraph();
        $graph['nodes'][1]['config']['header'] = str_repeat('H', 61);
        $graph['nodes'][1]['config']['body'] = str_repeat('B', 1025);
        $graph['nodes'][1]['config']['footer'] = str_repeat('F', 61);
        $graph['nodes'][1]['config']['button_label'] = str_repeat('L', 21);
        $graph['nodes'][1]['config']['sections'] = collect(range(1, 11))
            ->map(fn (int $index) => [
                'title' => "Audience {$index}",
                'rows' => [[
                    'id' => $index === 1 ? str_repeat('r', 201) : "row-{$index}",
                    'title' => $index === 1 ? str_repeat('T', 25) : "Row {$index}",
                    'description' => $index === 1 ? str_repeat('D', 73) : "Description {$index}",
                ]],
            ])
            ->all();

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/validate", [
                'graph_json' => $graph,
            ])
            ->assertOk()
            ->assertJson([
                'valid' => false,
            ])
            ->assertJsonFragment([
                'WhatsApp list messages support up to 10 sections.',
            ])
            ->assertJsonFragment([
                'WhatsApp list messages support up to 10 rows in total across all sections.',
            ])
            ->assertJsonFragment([
                'Interactive headers support up to 60 characters.',
            ])
            ->assertJsonFragment([
                'Interactive message bodies support up to 1024 characters.',
            ])
            ->assertJsonFragment([
                'Interactive footers support up to 60 characters.',
            ])
            ->assertJsonFragment([
                'List button labels support up to 20 characters.',
            ])
            ->assertJsonFragment([
                'List row IDs support up to 200 characters.',
            ])
            ->assertJsonFragment([
                'Row 1 in section 1 supports up to 24 characters for the title.',
            ])
            ->assertJsonFragment([
                'Row 1 in section 1 supports up to 72 characters for the description.',
            ]);
    }

    public function test_publish_is_blocked_when_the_flow_contains_a_circular_path(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Circular flow');

        $graph = $this->circularRuntimeGraph();

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->putJson("/automation/flows/{$flow->uuid}", [
                'name' => 'Circular flow',
                'description' => __('Contains a loop that should be blocked before publish.'),
                'graph_json' => $graph,
                'ui_json' => ['preview' => ['selected_scenario' => 'main']],
                'node_secrets' => [],
            ])
            ->assertOk();

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/validate", [
                'graph_json' => $graph,
            ])
            ->assertOk()
            ->assertJson([
                'valid' => false,
            ])
            ->assertJsonFragment([
                'Remove circular paths before publishing this automation.',
            ]);

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/publish")
            ->assertStatus(422)
            ->assertJsonFragment([
                'Remove circular paths before publishing this automation.',
            ]);
    }

    public function test_duplicate_flow_copies_email_secrets_and_remaps_secret_reference(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $this->allowExternalActionsInBuilderPolicy();
        $flow = $this->createFlow($user->id, $organization->id, 'Email flow');

        $graph = $this->emailGraph();

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->putJson("/automation/flows/{$flow->uuid}", [
                'name' => 'Email flow',
                'description' => __('Contains an email step.'),
                'graph_json' => $graph,
                'ui_json' => ['preview' => ['selected_scenario' => 'main']],
                'node_secrets' => [
                    'send-email-1' => [
                        'host' => 'smtp.example.com',
                        'port' => '587',
                        'username' => 'mailer@example.com',
                        'password' => 'super-secret',
                        'encryption' => 'tls',
                        'from_name' => 'Botzo',
                        'from_email' => 'mailer@example.com',
                    ],
                ],
            ])
            ->assertOk();

        $flow->refresh()->load('nodeSecrets');
        $originalSecret = $flow->nodeSecrets->firstOrFail();
        $originalSecretRef = Arr::get($flow->graph_json, 'nodes.1.config.secret_ref');

        $this->assertSame($originalSecret->uuid, $originalSecretRef);

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/duplicate")
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
            ]);

        $duplicate = AutomationFlow::query()
            ->where('organization_id', $organization->id)
            ->where('uuid', '!=', $flow->uuid)
            ->latest('id')
            ->firstOrFail();

        $duplicate->load('nodeSecrets');
        $duplicateSecret = $duplicate->nodeSecrets->firstOrFail();
        $duplicateSecretRef = Arr::get($duplicate->graph_json, 'nodes.1.config.secret_ref');

        $this->assertCount(1, $duplicate->nodeSecrets);
        $this->assertSame($duplicateSecret->uuid, $duplicateSecretRef);
        $this->assertNotSame($originalSecret->uuid, $duplicateSecret->uuid);
        $this->assertSame($originalSecret->payload_json, $duplicateSecret->payload_json);
    }

    public function test_validate_endpoint_rejects_malformed_payload_with_422(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Malformed validation');

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/validate", [
                'graph_json' => 'not-an-array',
                'node_secrets' => 'not-an-array',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['graph_json', 'node_secrets']);
    }

    public function test_preview_endpoint_rejects_graph_nodes_missing_required_fields_with_422(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Malformed preview');

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/preview", [
                'graph_json' => [
                    'start_node_id' => 'broken-node',
                    'nodes' => [
                        ['id' => 'broken-node'],
                    ],
                    'edges' => [],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['graph_json.nodes.0.type']);
    }

    public function test_validate_does_not_persist_secrets_and_update_cleans_removed_email_node_secrets(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $this->allowExternalActionsInBuilderPolicy();
        $flow = $this->createFlow($user->id, $organization->id, 'Secret lifecycle');
        $emailSecrets = $this->smtpSecrets();

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/validate", [
                'graph_json' => $this->emailGraph(),
                'node_secrets' => [
                    'send-email-1' => $emailSecrets,
                ],
            ])
            ->assertOk()
            ->assertJson([
                'valid' => true,
            ]);

        $this->assertSame(0, AutomationFlowNodeSecret::query()->where('automation_flow_id', $flow->id)->count());

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->putJson("/automation/flows/{$flow->uuid}", [
                'name' => 'Secret lifecycle',
                'description' => __('Contains a persisted email step.'),
                'graph_json' => $this->emailGraph(),
                'ui_json' => ['preview' => ['selected_scenario' => 'main']],
                'node_secrets' => [
                    'send-email-1' => $emailSecrets,
                ],
            ])
            ->assertOk();

        $this->assertSame(1, AutomationFlowNodeSecret::query()->where('automation_flow_id', $flow->id)->count());

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->putJson("/automation/flows/{$flow->uuid}", [
                'name' => 'Secret lifecycle',
                'description' => __('Email step removed.'),
                'graph_json' => $this->validGraph(),
                'ui_json' => ['preview' => ['selected_scenario' => 'main']],
                'node_secrets' => [],
            ])
            ->assertOk();

        $this->assertSame(0, AutomationFlowNodeSecret::query()->where('automation_flow_id', $flow->id)->count());
    }

    public function test_duplicate_flow_ignores_orphaned_email_secrets(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $this->allowExternalActionsInBuilderPolicy();
        $flow = $this->createFlow($user->id, $organization->id, 'Email flow with orphan');

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->putJson("/automation/flows/{$flow->uuid}", [
                'name' => 'Email flow with orphan',
                'description' => __('Contains one active email step.'),
                'graph_json' => $this->emailGraph(),
                'ui_json' => ['preview' => ['selected_scenario' => 'main']],
                'node_secrets' => [
                    'send-email-1' => $this->smtpSecrets(),
                ],
            ])
            ->assertOk();

        AutomationFlowNodeSecret::create([
            'uuid' => (string) Str::uuid(),
            'automation_flow_id' => $flow->id,
            'organization_id' => $organization->id,
            'node_id' => 'orphan-email-1',
            'node_type' => 'send_email',
            'payload_json' => $this->smtpSecrets(),
        ]);

        $this->assertSame(2, AutomationFlowNodeSecret::query()->where('automation_flow_id', $flow->id)->count());

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/duplicate")
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
            ]);

        $duplicate = AutomationFlow::query()
            ->where('organization_id', $organization->id)
            ->where('uuid', '!=', $flow->uuid)
            ->latest('id')
            ->firstOrFail();

        $duplicate->load('nodeSecrets');

        $this->assertCount(1, $duplicate->nodeSecrets);
        $this->assertSame('send-email-1', $duplicate->nodeSecrets->first()->node_id);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_runtime_consumes_button_reply_and_advances_to_the_selected_branch(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Buttons runtime');
        $this->publishGraph($user, $organization->id, $flow, $this->buttonsRuntimeGraph());
        $this->mockRuntimeWhatsappService();

        $contact = $this->createContact($organization->id, $user->id, '+15550000001');
        $runtime = app(AutomationFlowRuntimeService::class);

        $started = $runtime->handleInbound($this->createInboundChat($contact, [
            'type' => 'text',
            'text' => ['body' => 'Hello'],
        ]));

        $this->assertTrue($started);

        $run = AutomationFlowRun::query()->where('automation_flow_id', $flow->id)->latest('id')->firstOrFail();
        $this->assertSame('waiting_input', $run->status);
        $this->assertSame('button', $run->waiting_for);

        $resumed = $runtime->handleInbound($this->createInboundChat($contact, [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button_reply',
                'button_reply' => [
                    'id' => 'buy',
                    'title' => 'Buy',
                ],
            ],
        ]));

        $this->assertTrue($resumed);

        $run->refresh();
        $this->assertSame('completed', $run->status);
        $this->assertNull($run->waiting_for);
        $this->assertTrue(
            AutomationFlowRunStep::query()
                ->where('automation_flow_run_id', $run->id)
                ->where('node_id', 'buttons-1')
                ->where('status', 'executed')
                ->where('output_json->selected_button_id', 'buy')
                ->exists()
        );
        $this->assertTrue(
            AutomationFlowRunStep::query()
                ->where('automation_flow_run_id', $run->id)
                ->where('node_id', 'send-buy')
                ->where('status', 'executed')
                ->exists()
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_runtime_sends_button_headers_and_footers_to_whatsapp(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Buttons header runtime');
        $graph = $this->buttonsRuntimeGraph();
        $graph['nodes'][1]['config']['header'] = 'Choose a service';
        $graph['nodes'][1]['config']['footer'] = 'Pick one option';

        $this->publishGraph($user, $organization->id, $flow, $graph);

        $sentMessages = [];
        $this->mockRuntimeWhatsappService($sentMessages);

        $contact = $this->createContact($organization->id, $user->id, '+15550000009');
        $runtime = app(AutomationFlowRuntimeService::class);

        $started = $runtime->handleInbound($this->createInboundChat($contact, [
            'type' => 'text',
            'text' => ['body' => 'Hello'],
        ]));

        $this->assertTrue($started);
        $this->assertNotEmpty($sentMessages);
        $this->assertSame('interactive buttons', $sentMessages[0][3]);
        $this->assertSame([
            'type' => 'text',
            'text' => 'Choose a service',
        ], $sentMessages[0][5]);
        $this->assertSame('Pick one option', $sentMessages[0][6]);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_runtime_consumes_list_reply_and_advances_to_the_selected_branch(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Lists runtime');
        $this->publishGraph($user, $organization->id, $flow, $this->listRuntimeGraph());
        $this->mockRuntimeWhatsappService();

        $contact = $this->createContact($organization->id, $user->id, '+15550000002');
        $runtime = app(AutomationFlowRuntimeService::class);

        $started = $runtime->handleInbound($this->createInboundChat($contact, [
            'type' => 'text',
            'text' => ['body' => 'Start'],
        ]));

        $this->assertTrue($started);

        $run = AutomationFlowRun::query()->where('automation_flow_id', $flow->id)->latest('id')->firstOrFail();
        $this->assertSame('waiting_input', $run->status);
        $this->assertSame('list', $run->waiting_for);

        $resumed = $runtime->handleInbound($this->createInboundChat($contact, [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list_reply',
                'list_reply' => [
                    'id' => 'vip',
                    'title' => 'VIP',
                ],
            ],
        ]));

        $this->assertTrue($resumed);

        $run->refresh();
        $this->assertSame('completed', $run->status);
        $this->assertNull($run->waiting_for);
        $this->assertTrue(
            AutomationFlowRunStep::query()
                ->where('automation_flow_run_id', $run->id)
                ->where('node_id', 'list-1')
                ->where('status', 'executed')
                ->where('output_json->selected_list_row_id', 'vip')
                ->exists()
        );
        $this->assertTrue(
            AutomationFlowRunStep::query()
                ->where('automation_flow_run_id', $run->id)
                ->where('node_id', 'send-vip')
                ->where('status', 'executed')
                ->exists()
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_runtime_sends_list_headers_footers_and_button_labels_to_whatsapp(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'List header runtime');
        $graph = $this->listRuntimeGraph();
        $graph['nodes'][1]['config']['header'] = 'Available services';
        $graph['nodes'][1]['config']['footer'] = 'Choose what fits you';
        $graph['nodes'][1]['config']['button_label'] = 'Open menu';

        $this->publishGraph($user, $organization->id, $flow, $graph);

        $sentMessages = [];
        $this->mockRuntimeWhatsappService($sentMessages);

        $contact = $this->createContact($organization->id, $user->id, '+15550000010');
        $runtime = app(AutomationFlowRuntimeService::class);

        $started = $runtime->handleInbound($this->createInboundChat($contact, [
            'type' => 'text',
            'text' => ['body' => 'Start'],
        ]));

        $this->assertTrue($started);
        $this->assertNotEmpty($sentMessages);
        $this->assertSame('interactive list', $sentMessages[0][3]);
        $this->assertSame([
            'type' => 'text',
            'text' => 'Available services',
        ], $sentMessages[0][5]);
        $this->assertSame('Choose what fits you', $sentMessages[0][6]);
        $this->assertSame('Open menu', $sentMessages[0][7]);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_runtime_releases_free_text_reply_when_button_selection_is_required(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Interactive fallback');
        $this->publishGraph($user, $organization->id, $flow, $this->buttonsRuntimeGraph());
        $this->mockRuntimeWhatsappService();

        $contact = $this->createContact($organization->id, $user->id, '+15550000003');
        $runtime = app(AutomationFlowRuntimeService::class);

        $runtime->handleInbound($this->createInboundChat($contact, [
            'type' => 'text',
            'text' => ['body' => 'Hello'],
        ]));

        $run = AutomationFlowRun::query()->where('automation_flow_id', $flow->id)->latest('id')->firstOrFail();
        $this->assertSame('waiting_input', $run->status);
        $this->assertSame('button', $run->waiting_for);

        $released = $runtime->handleInbound($this->createInboundChat($contact, [
            'type' => 'text',
            'text' => ['body' => 'عاوز أعرف الأسعار'],
        ]));

        $this->assertFalse($released);

        $run->refresh();
        $this->assertSame('waiting_input', $run->status);
        $this->assertSame('button', $run->waiting_for);
        $this->assertTrue(
            AutomationFlowRunStep::query()
                ->where('automation_flow_run_id', $run->id)
                ->where('node_id', 'buttons-1')
                ->where('status', 'ignored')
                ->where('metadata_json->released_to_fallback', true)
                ->exists()
        );
    }

    public function test_validate_blocks_an_unknown_invalid_reply_behavior(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Invalid reply behavior');

        $graph = $this->buttonsRuntimeGraph();
        $graph['nodes'][1]['config']['invalid_reply_behavior'] = 'explode';

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/validate", [
                'graph_json' => $graph,
            ])
            ->assertOk()
            ->assertJson([
                'valid' => false,
            ])
            ->assertJsonFragment([
                'Choose how this step should react when the customer sends a normal message instead of tapping a reply.',
            ]);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_runtime_repeats_the_interactive_prompt_when_repeat_prompt_behavior_is_selected(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Interactive repeat prompt');
        $graph = $this->buttonsRuntimeGraph();
        $graph['nodes'][1]['config']['invalid_reply_behavior'] = 'repeat_prompt';
        $this->publishGraph($user, $organization->id, $flow, $graph);

        $sentMessages = [];
        $this->mockRuntimeWhatsappService($sentMessages);

        $contact = $this->createContact($organization->id, $user->id, '+15550000013');
        $runtime = app(AutomationFlowRuntimeService::class);

        $runtime->handleInbound($this->createInboundChat($contact, [
            'type' => 'text',
            'text' => ['body' => 'Hello'],
        ]));

        $run = AutomationFlowRun::query()->where('automation_flow_id', $flow->id)->latest('id')->firstOrFail();
        $this->assertSame('waiting_input', $run->status);
        $this->assertCount(1, $sentMessages);

        $handled = $runtime->handleInbound($this->createInboundChat($contact, [
            'type' => 'text',
            'text' => ['body' => 'أحتاج موظف'],
        ]));

        $this->assertTrue($handled);

        $run->refresh();
        $this->assertSame('waiting_input', $run->status);
        $this->assertSame('button', $run->waiting_for);
        $this->assertCount(2, $sentMessages);
        $this->assertTrue(
            AutomationFlowRunStep::query()
                ->where('automation_flow_run_id', $run->id)
                ->where('node_id', 'buttons-1')
                ->where('status', 'waiting')
                ->where('metadata_json->invalid_reply_behavior', 'repeat_prompt')
                ->exists()
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_runtime_can_end_the_waiting_run_when_invalid_reply_behavior_is_end_run(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Interactive end run');
        $graph = $this->buttonsRuntimeGraph();
        $graph['nodes'][1]['config']['invalid_reply_behavior'] = 'end_run';
        $this->publishGraph($user, $organization->id, $flow, $graph);
        $this->mockRuntimeWhatsappService();

        $contact = $this->createContact($organization->id, $user->id, '+15550000014');
        $runtime = app(AutomationFlowRuntimeService::class);

        $runtime->handleInbound($this->createInboundChat($contact, [
            'type' => 'text',
            'text' => ['body' => 'Hello'],
        ]));

        $run = AutomationFlowRun::query()->where('automation_flow_id', $flow->id)->latest('id')->firstOrFail();
        $this->assertSame('waiting_input', $run->status);

        $handled = $runtime->handleInbound($this->createInboundChat($contact, [
            'type' => 'text',
            'text' => ['body' => 'أحتاج تواصل مع الموظف'],
        ]));

        $this->assertFalse($handled);

        $run->refresh();
        $this->assertSame('cancelled', $run->status);
        $this->assertNull($run->waiting_for);
        $this->assertTrue(
            AutomationFlowRunStep::query()
                ->where('automation_flow_run_id', $run->id)
                ->where('node_id', 'buttons-1')
                ->where('status', 'cancelled')
                ->where('metadata_json->invalid_reply_behavior', 'end_run')
                ->exists()
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_runtime_expires_a_stale_waiting_run_and_starts_a_fresh_one(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Stale waiting run');
        $this->publishGraph($user, $organization->id, $flow, $this->buttonsRuntimeGraph());

        $sentMessages = [];
        $this->mockRuntimeWhatsappService($sentMessages);

        $contact = $this->createContact($organization->id, $user->id, '+15550000015');
        $runtime = app(AutomationFlowRuntimeService::class);

        $runtime->handleInbound($this->createInboundChat($contact, [
            'type' => 'text',
            'text' => ['body' => 'Hello'],
        ]));

        $staleRun = AutomationFlowRun::query()->where('automation_flow_id', $flow->id)->latest('id')->firstOrFail();
        $staleRun->update([
            'last_activity_at' => now()->subMinutes((int) config('automation_flows.runtime.waiting_input_stale_minutes', 1440) + 5),
        ]);

        $startedFresh = $runtime->handleInbound($this->createInboundChat($contact, [
            'type' => 'text',
            'text' => ['body' => 'Hello again'],
        ]));

        $this->assertTrue($startedFresh);

        $staleRun->refresh();
        $this->assertSame('cancelled', $staleRun->status);
        $this->assertTrue(
            AutomationFlowRunStep::query()
                ->where('automation_flow_run_id', $staleRun->id)
                ->where('status', 'cancelled')
                ->where('metadata_json->reason', 'stale_run_expired')
                ->exists()
        );

        $latestRun = AutomationFlowRun::query()->where('automation_flow_id', $flow->id)->latest('id')->firstOrFail();
        $this->assertNotSame($staleRun->id, $latestRun->id);
        $this->assertSame('waiting_input', $latestRun->status);
        $this->assertSame('button', $latestRun->waiting_for);
        $this->assertCount(2, $sentMessages);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_any_incoming_trigger_starts_for_non_text_inbound_messages(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Image trigger');
        $this->publishGraph($user, $organization->id, $flow, $this->validGraph());
        $this->mockRuntimeWhatsappService();

        $contact = $this->createContact($organization->id, $user->id, '+15550000004');
        $runtime = app(AutomationFlowRuntimeService::class);

        $started = $runtime->handleInbound($this->createInboundChat($contact, [
            'type' => 'image',
            'image' => ['id' => 'img-123'],
        ]));

        $this->assertTrue($started);

        $run = AutomationFlowRun::query()->where('automation_flow_id', $flow->id)->latest('id')->firstOrFail();
        $this->assertSame('completed', $run->status);
    }

    public function test_preview_includes_media_asset_metadata_for_rendering(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Media preview');

        $asset = AutomationFlowAsset::create([
            'uuid' => (string) Str::uuid(),
            'automation_flow_id' => $flow->id,
            'organization_id' => $organization->id,
            'media_kind' => 'image',
            'disk' => 'local',
            'path' => 'automation-flows/test/sample.jpg',
            'original_name' => 'sample.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 2048,
            'meta_json' => ['extension' => 'jpg'],
            'created_by' => $user->id,
        ]);

        $graph = $this->mediaPreviewGraph($asset->uuid);

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->putJson("/automation/flows/{$flow->uuid}", [
                'name' => 'Media preview',
                'description' => __('Shows image metadata in preview.'),
                'graph_json' => $graph,
                'ui_json' => ['preview' => ['selected_scenario' => 'main']],
                'node_secrets' => [],
            ])
            ->assertOk();

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/preview", [
                'graph_json' => $graph,
                'focus_node_id' => 'send-media-1',
            ])
            ->assertOk()
            ->assertJsonPath('steps.0.kind', 'assistant')
            ->assertJsonPath('steps.0.meta.media_type', 'image')
            ->assertJsonPath('steps.0.meta.media_name', 'sample.jpg');
    }

    public function test_preview_describes_system_side_effects_with_more_context(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Preview side effects');

        $graph = $this->updateSessionVariableGraph();

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->putJson("/automation/flows/{$flow->uuid}", [
                'name' => 'Preview side effects',
                'description' => __('Contains internal routing effects.'),
                'graph_json' => $graph,
                'ui_json' => ['preview' => ['selected_scenario' => 'main']],
                'node_secrets' => [],
            ])
            ->assertOk();

        $previewResponse = $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/preview", [
                'graph_json' => $graph,
            ]);

        $previewResponse->assertOk();
        $labels = collect($previewResponse->json('steps'))->pluck('label')->filter()->values()->all();

        $this->assertContains('Save "vip" into flow variable "route_code".', $labels);
        $this->assertContains('Check flow variable "route_code" and route the customer to the matching branch.', $labels);
    }

    public function test_validate_blocks_assign_to_agent_when_ticketing_is_inactive(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Assign to agent validation');

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/validate", [
                'graph_json' => $this->assignToAgentGraph(),
            ])
            ->assertOk()
            ->assertJson([
                'valid' => false,
            ])
            ->assertJsonFragment([
                'Activate ticketing before assigning the conversation to a service agent.',
            ]);
    }

    public function test_validate_blocks_specific_agent_assignment_when_agent_is_invalid(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $this->enableTicketingForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Specific agent validation');

        $graph = $this->assignToAgentGraph();
        $graph['nodes'][1]['config']['assignment_mode'] = 'specific_agent';
        $graph['nodes'][1]['config']['agent_user_id'] = 999999;

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/validate", [
                'graph_json' => $graph,
            ])
            ->assertOk()
            ->assertJson([
                'valid' => false,
            ])
            ->assertJsonFragment([
                'Choose a valid service agent for this step.',
            ]);
    }

    public function test_preview_stops_when_the_flow_hands_off_to_customer_service(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $this->enableTicketingForOrganization($organization->id);
        $this->addAssignableAgent($organization->id, $user->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Human handoff preview');

        $graph = $this->humanHandoffGraph();

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->putJson("/automation/flows/{$flow->uuid}", [
                'name' => 'Human handoff preview',
                'description' => __('Stops at the handoff step.'),
                'graph_json' => $graph,
                'ui_json' => ['preview' => ['selected_scenario' => 'main']],
                'node_secrets' => [],
            ])
            ->assertOk();

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/preview", [
                'graph_json' => $graph,
            ])
            ->assertOk()
            ->assertJsonPath('steps.1.kind', 'system')
            ->assertJsonPath('steps.1.label', 'Pause automation and hand the conversation to customer service via the least busy service agent.');
    }

    public function test_validate_blocks_missing_flow_variable_key_when_session_storage_is_selected(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Session variable validation');

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/validate", [
                'graph_json' => $this->saveReplyToSessionGraph(''),
            ])
            ->assertOk()
            ->assertJson([
                'valid' => false,
            ])
            ->assertJsonFragment([
                'Choose a valid flow variable key for this reply step.',
            ]);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_runtime_assign_to_agent_creates_ticket_and_continues_the_flow(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $this->enableTicketingForOrganization($organization->id);
        $agent = $this->addAssignableAgent($organization->id, $user->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Assign to agent runtime');
        $graph = $this->assignToAgentGraph();
        $graph['nodes'][1]['config']['assignment_mode'] = 'specific_agent';
        $graph['nodes'][1]['config']['agent_user_id'] = $agent->id;
        $this->publishGraph($user, $organization->id, $flow, $graph);

        $sentMessages = [];
        $this->mockRuntimeWhatsappService($sentMessages);

        $contact = $this->createContact($organization->id, $user->id, '+15550000016');
        $runtime = app(AutomationFlowRuntimeService::class);

        $handled = $runtime->handleInbound($this->createInboundChat($contact, [
            'type' => 'text',
            'text' => ['body' => 'ابدأ'],
        ]));

        $this->assertTrue($handled);

        $ticket = ChatTicket::query()->where('contact_id', $contact->id)->latest('id')->firstOrFail();
        $this->assertSame($agent->id, (int) $ticket->assigned_to);
        $this->assertCount(1, $sentMessages);
        $this->assertSame('Your request is now with customer service.', $sentMessages[0][1]);

        $run = AutomationFlowRun::query()->where('automation_flow_id', $flow->id)->latest('id')->firstOrFail();
        $this->assertSame('completed', $run->status);
        $this->assertTrue(
            AutomationFlowRunStep::query()
                ->where('automation_flow_run_id', $run->id)
                ->where('node_id', 'assign-agent-1')
                ->where('status', 'executed')
                ->exists()
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_runtime_human_handoff_moves_the_run_to_waiting_handoff(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $this->enableTicketingForOrganization($organization->id);
        $agent = $this->addAssignableAgent($organization->id, $user->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Human handoff runtime');
        $graph = $this->humanHandoffGraph();
        $graph['nodes'][1]['config']['assignment_mode'] = 'specific_agent';
        $graph['nodes'][1]['config']['agent_user_id'] = $agent->id;
        $this->publishGraph($user, $organization->id, $flow, $graph);
        $this->mockRuntimeWhatsappService();

        $contact = $this->createContact($organization->id, $user->id, '+15550000017');
        Contact::query()->whereKey($contact->id)->update(['ai_assistance_enabled' => true]);

        $runtime = app(AutomationFlowRuntimeService::class);
        $handled = $runtime->handleInbound($this->createInboundChat($contact, [
            'type' => 'text',
            'text' => ['body' => 'أحتاج موظف'],
        ]));

        $this->assertTrue($handled);

        $ticket = ChatTicket::query()->where('contact_id', $contact->id)->latest('id')->firstOrFail();
        $this->assertSame($agent->id, (int) $ticket->assigned_to);

        $run = AutomationFlowRun::query()->where('automation_flow_id', $flow->id)->latest('id')->firstOrFail();
        $this->assertSame('waiting_handoff', $run->status);
        $this->assertSame('human_handoff', $run->waiting_for);
        $this->assertSame('human', data_get($run->state_json, 'handoff.target'));

        $contact->refresh();
        $this->assertFalse((bool) $contact->ai_assistance_enabled);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_runtime_can_store_the_customer_reply_in_a_flow_variable_and_route_on_it(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Session variable runtime');
        $this->publishGraph($user, $organization->id, $flow, $this->saveReplyToSessionGraph('lead_intent'));

        $sentMessages = [];
        $this->mockRuntimeWhatsappService($sentMessages);

        $contact = $this->createContact($organization->id, $user->id, '+15550000019');
        $runtime = app(AutomationFlowRuntimeService::class);

        $handled = $runtime->handleInbound($this->createInboundChat($contact, [
            'type' => 'text',
            'text' => ['body' => 'أريد شراء شقة'],
        ]));

        $this->assertTrue($handled);
        $this->assertCount(1, $sentMessages);
        $this->assertSame('تم حفظ اهتمامك بالشراء داخل التدفق.', $sentMessages[0][1]);

        $run = AutomationFlowRun::query()->where('automation_flow_id', $flow->id)->latest('id')->firstOrFail();
        $this->assertSame('completed', $run->status);
        $this->assertSame('أريد شراء شقة', data_get($run->state_json, 'session_variables.lead_intent'));

        $conditionStep = AutomationFlowRunStep::query()
            ->where('automation_flow_run_id', $run->id)
            ->where('node_id', 'condition-1')
            ->firstOrFail();

        $this->assertSame('matched', data_get($conditionStep->output_json, 'branch'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_runtime_update_contact_field_can_store_a_static_value_in_a_flow_variable(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Update flow variable runtime');
        $this->publishGraph($user, $organization->id, $flow, $this->updateSessionVariableGraph());

        $sentMessages = [];
        $this->mockRuntimeWhatsappService($sentMessages);

        $contact = $this->createContact($organization->id, $user->id, '+15550000020');
        $runtime = app(AutomationFlowRuntimeService::class);

        $handled = $runtime->handleInbound($this->createInboundChat($contact, [
            'type' => 'text',
            'text' => ['body' => 'ابدأ'],
        ]));

        $this->assertTrue($handled);
        $this->assertCount(1, $sentMessages);
        $this->assertSame('تم حفظ القيمة الداخلية لهذا التدفق.', $sentMessages[0][1]);

        $run = AutomationFlowRun::query()->where('automation_flow_id', $flow->id)->latest('id')->firstOrFail();
        $this->assertSame('completed', $run->status);
        $this->assertSame('vip', data_get($run->state_json, 'session_variables.route_code'));

        $updateStep = AutomationFlowRunStep::query()
            ->where('automation_flow_run_id', $run->id)
            ->where('node_id', 'set-route-code-1')
            ->firstOrFail();

        $this->assertSame('session_variable', data_get($updateStep->output_json, 'save_target'));
        $this->assertSame('route_code', data_get($updateStep->output_json, 'variable_key'));
        $this->assertSame('vip', data_get($updateStep->output_json, 'value'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_runtime_ai_handoff_enables_the_built_in_ai_assistant(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $this->enableAiAssistantForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'AI handoff runtime');
        $this->publishGraph($user, $organization->id, $flow, $this->aiHandoffGraph());
        $this->mockRuntimeWhatsappService();

        $contact = $this->createContact($organization->id, $user->id, '+15550000018');
        $runtime = app(AutomationFlowRuntimeService::class);

        $handled = $runtime->handleInbound($this->createInboundChat($contact, [
            'type' => 'text',
            'text' => ['body' => 'أحتاج المساعد الذكي'],
        ]));

        $this->assertTrue($handled);

        $run = AutomationFlowRun::query()->where('automation_flow_id', $flow->id)->latest('id')->firstOrFail();
        $this->assertSame('waiting_handoff', $run->status);
        $this->assertSame('ai_handoff', $run->waiting_for);
        $this->assertSame('ai_assistant', data_get($run->state_json, 'handoff.target'));

        $contact->refresh();
        $this->assertTrue((bool) $contact->ai_assistance_enabled);
    }

    public function test_validate_and_publish_block_external_steps_when_policy_disables_them(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $this->blockExternalActionsInBuilderPolicy();
        $flow = $this->createFlow($user->id, $organization->id, 'Policy blocked flow');
        $graph = $this->emailGraph();

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->putJson("/automation/flows/{$flow->uuid}", [
                'name' => 'Policy blocked flow',
                'description' => __('Contains external actions blocked by policy.'),
                'graph_json' => $graph,
                'ui_json' => ['preview' => ['selected_scenario' => 'main']],
                'node_secrets' => [
                    'send-email-1' => $this->smtpSecrets(),
                ],
            ])
            ->assertOk();

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/validate", [
                'graph_json' => $graph,
                'node_secrets' => [
                    'send-email-1' => $this->smtpSecrets(),
                ],
            ])
            ->assertOk()
            ->assertJson([
                'valid' => false,
            ])
            ->assertJsonFragment([
                'Flow Builder policy blocks Send Email actions for this workspace.',
            ]);

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/publish")
            ->assertStatus(422)
            ->assertJsonFragment([
                'Flow Builder policy blocks one or more steps in this flow: Send Email',
            ]);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_runtime_stops_external_steps_when_policy_is_disabled_after_publish(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $this->allowExternalActionsInBuilderPolicy();
        $flow = $this->createFlow($user->id, $organization->id, 'Policy flip');

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->putJson("/automation/flows/{$flow->uuid}", [
                'name' => 'Policy flip',
                'description' => __('Published before policy was tightened.'),
                'graph_json' => $this->emailGraph(),
                'ui_json' => ['preview' => ['selected_scenario' => 'main']],
                'node_secrets' => [
                    'send-email-1' => $this->smtpSecrets(),
                ],
            ])
            ->assertOk();

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/publish")
            ->assertOk();

        $this->blockExternalActionsInBuilderPolicy();

        $contact = $this->createContact($organization->id, $user->id, '+15550000999');
        $runtime = app(AutomationFlowRuntimeService::class);

        $started = $runtime->handleInbound($this->createInboundChat($contact, [
            'type' => 'text',
            'text' => ['body' => 'Need a quote'],
        ]));

        $this->assertTrue($started);

        $run = AutomationFlowRun::query()->where('automation_flow_id', $flow->id)->latest('id')->firstOrFail();
        $this->assertSame('failed', $run->status);
        $this->assertTrue(
            AutomationFlowRunStep::query()
                ->where('automation_flow_run_id', $run->id)
                ->where('status', 'failed')
                ->where('node_type', 'send_email')
                ->where('output_json->reason', 'builder_policy_blocked_step')
                ->where('metadata_json->policy_blocked', true)
                ->exists()
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_runtime_blocks_freeform_whatsapp_steps_when_customer_care_window_is_closed(): void
    {
        config()->set('automation_flows.whatsapp.customer_care_window_hours', 1);
        config()->set('automation_flows.whatsapp.enforce_customer_care_window', true);

        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Customer care window guard');
        $this->publishGraph($user, $organization->id, $flow, $this->validGraph());

        $sentMessages = [];
        $this->mockRuntimeWhatsappService($sentMessages);

        $contact = $this->createContact($organization->id, $user->id, '+15550000155');
        $runtime = app(AutomationFlowRuntimeService::class);

        $inboundChat = $this->createInboundChat($contact, [
            'type' => 'text',
            'text' => ['body' => 'Hello flow'],
        ]);
        $inboundChat->update(['created_at' => now()->subHours(3)]);
        $inboundChat->refresh();

        $started = $runtime->handleInbound($inboundChat);
        $this->assertTrue($started);

        $run = AutomationFlowRun::query()->where('automation_flow_id', $flow->id)->latest('id')->firstOrFail();
        $this->assertSame('failed', $run->status);
        $this->assertTrue(
            AutomationFlowRunStep::query()
                ->where('automation_flow_run_id', $run->id)
                ->where('status', 'failed')
                ->where('node_type', 'send_text')
                ->where('output_json->reason', 'customer_care_window_closed')
                ->where('metadata_json->conversation_window_closed', true)
                ->exists()
        );
        $this->assertCount(0, $sentMessages);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_runtime_can_release_to_fallback_when_customer_care_window_is_closed(): void
    {
        config()->set('automation_flows.whatsapp.customer_care_window_hours', 1);
        config()->set('automation_flows.whatsapp.enforce_customer_care_window', true);
        config()->set('automation_flows.whatsapp.on_window_closed', 'release_to_fallback');

        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Customer care window release');
        $this->publishGraph($user, $organization->id, $flow, $this->validGraph());

        $sentMessages = [];
        $this->mockRuntimeWhatsappService($sentMessages);

        $contact = $this->createContact($organization->id, $user->id, '+15550000156');
        $runtime = app(AutomationFlowRuntimeService::class);

        $inboundChat = $this->createInboundChat($contact, [
            'type' => 'text',
            'text' => ['body' => 'Hello flow'],
        ]);
        $inboundChat->update(['created_at' => now()->subHours(3)]);
        $inboundChat->refresh();

        $started = $runtime->handleInbound($inboundChat);
        $this->assertFalse($started);

        $run = AutomationFlowRun::query()->where('automation_flow_id', $flow->id)->latest('id')->firstOrFail();
        $this->assertSame('cancelled', $run->status);
        $this->assertTrue((bool) Arr::get($run->state_json ?? [], 'runtime.release_to_fallback', false));
        $this->assertSame('customer_care_window_closed', (string) Arr::get($run->state_json ?? [], 'runtime.release_reason', ''));
        $this->assertTrue(
            AutomationFlowRunStep::query()
                ->where('automation_flow_run_id', $run->id)
                ->where('status', 'cancelled')
                ->where('node_type', 'send_text')
                ->where('output_json->reason', 'customer_care_window_closed')
                ->where('metadata_json->released_to_fallback', true)
                ->exists()
        );
        $this->assertCount(0, $sentMessages);
    }

    public function test_asset_upload_requires_media_kind_and_blocks_mime_kind_mismatch(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($user->id, $organization->id, 'Asset guard');
        Storage::fake('local');

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/assets", [
                'file' => UploadedFile::fake()->create('sample.mp4', 1024, 'video/mp4'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['media_kind']);

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson("/automation/flows/{$flow->uuid}/assets", [
                'file' => UploadedFile::fake()->create('sample.mp4', 1024, 'video/mp4'),
                'media_kind' => 'image',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file'])
            ->assertJsonFragment([
                'Uploaded file MIME type does not match the selected image media type.',
            ]);
    }

    private function validGraph(): array
    {
        return [
            'start_node_id' => 'trigger-1',
            'nodes' => [
                [
                    'id' => 'trigger-1',
                    'type' => 'trigger',
                    'position' => ['x' => 80, 'y' => 120],
                    'config' => [
                        'match_mode' => 'any_incoming',
                        'keywords' => [],
                    ],
                    'ui' => ['expanded' => true],
                ],
                [
                    'id' => 'send-text-1',
                    'type' => 'send_text',
                    'position' => ['x' => 420, 'y' => 120],
                    'config' => [
                        'text' => 'Welcome to Botzo. Tell us what you need and we will guide the next step.',
                    ],
                    'ui' => ['expanded' => false],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 760, 'y' => 120],
                    'config' => [],
                    'ui' => ['expanded' => false],
                ],
            ],
            'edges' => [
                [
                    'id' => 'edge-trigger-send',
                    'source_id' => 'trigger-1',
                    'target_id' => 'send-text-1',
                    'branch' => 'default',
                ],
                [
                    'id' => 'edge-send-end',
                    'source_id' => 'send-text-1',
                    'target_id' => 'end-1',
                    'branch' => 'default',
                ],
            ],
        ];
    }

    private function emailGraph(): array
    {
        return [
            'start_node_id' => 'trigger-1',
            'nodes' => [
                [
                    'id' => 'trigger-1',
                    'type' => 'trigger',
                    'position' => ['x' => 80, 'y' => 120],
                    'config' => [
                        'match_mode' => 'any_incoming',
                        'keywords' => [],
                    ],
                    'ui' => ['expanded' => true],
                ],
                [
                    'id' => 'send-email-1',
                    'type' => 'send_email',
                    'position' => ['x' => 420, 'y' => 120],
                    'config' => [
                        'subject' => 'Lead update',
                        'body' => 'A new lead asked for pricing.',
                    ],
                    'ui' => ['expanded' => false],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 760, 'y' => 120],
                    'config' => [],
                    'ui' => ['expanded' => false],
                ],
            ],
            'edges' => [
                [
                    'id' => 'edge-trigger-email',
                    'source_id' => 'trigger-1',
                    'target_id' => 'send-email-1',
                    'branch' => 'default',
                ],
                [
                    'id' => 'edge-email-end',
                    'source_id' => 'send-email-1',
                    'target_id' => 'end-1',
                    'branch' => 'default',
                ],
            ],
        ];
    }

    private function buttonsRuntimeGraph(): array
    {
        return [
            'start_node_id' => 'trigger-1',
            'nodes' => [
                [
                    'id' => 'trigger-1',
                    'type' => 'trigger',
                    'position' => ['x' => 80, 'y' => 120],
                    'config' => [
                        'match_mode' => 'any_incoming',
                        'keywords' => [],
                    ],
                    'ui' => ['expanded' => true],
                ],
                [
                    'id' => 'buttons-1',
                    'type' => 'send_buttons',
                    'position' => ['x' => 420, 'y' => 120],
                    'config' => [
                        'body' => 'Choose your path',
                        'buttons' => [
                            ['id' => 'buy', 'title' => 'Buy'],
                            ['id' => 'rent', 'title' => 'Rent'],
                        ],
                    ],
                    'ui' => ['expanded' => false],
                ],
                [
                    'id' => 'send-buy',
                    'type' => 'send_text',
                    'position' => ['x' => 760, 'y' => 40],
                    'config' => [
                        'text' => 'Buying flow selected.',
                    ],
                    'ui' => ['expanded' => false],
                ],
                [
                    'id' => 'send-rent',
                    'type' => 'send_text',
                    'position' => ['x' => 760, 'y' => 220],
                    'config' => [
                        'text' => 'Renting flow selected.',
                    ],
                    'ui' => ['expanded' => false],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 1100, 'y' => 120],
                    'config' => [],
                    'ui' => ['expanded' => false],
                ],
            ],
            'edges' => [
                [
                    'id' => 'edge-trigger-buttons',
                    'source_id' => 'trigger-1',
                    'target_id' => 'buttons-1',
                    'branch' => 'default',
                ],
                [
                    'id' => 'edge-buttons-buy',
                    'source_id' => 'buttons-1',
                    'target_id' => 'send-buy',
                    'branch' => 'buy',
                ],
                [
                    'id' => 'edge-buttons-rent',
                    'source_id' => 'buttons-1',
                    'target_id' => 'send-rent',
                    'branch' => 'rent',
                ],
                [
                    'id' => 'edge-buy-end',
                    'source_id' => 'send-buy',
                    'target_id' => 'end-1',
                    'branch' => 'default',
                ],
                [
                    'id' => 'edge-rent-end',
                    'source_id' => 'send-rent',
                    'target_id' => 'end-1',
                    'branch' => 'default',
                ],
            ],
        ];
    }

    private function buttonsGraphWithMissingBranch(): array
    {
        $graph = $this->buttonsRuntimeGraph();
        $graph['edges'] = array_values(array_filter($graph['edges'], static fn (array $edge) => $edge['branch'] !== 'rent'));

        return $graph;
    }

    private function duplicateButtonsGraph(): array
    {
        $graph = $this->buttonsRuntimeGraph();
        $graph['nodes'][1]['config']['buttons'] = [
            ['id' => 'buy', 'title' => 'Buy'],
            ['id' => 'buy', 'title' => 'Rent'],
        ];

        return $graph;
    }

    private function listRuntimeGraph(): array
    {
        return [
            'start_node_id' => 'trigger-1',
            'nodes' => [
                [
                    'id' => 'trigger-1',
                    'type' => 'trigger',
                    'position' => ['x' => 80, 'y' => 120],
                    'config' => [
                        'match_mode' => 'any_incoming',
                        'keywords' => [],
                    ],
                    'ui' => ['expanded' => true],
                ],
                [
                    'id' => 'list-1',
                    'type' => 'send_list',
                    'position' => ['x' => 420, 'y' => 120],
                    'config' => [
                        'body' => 'Choose a segment',
                        'button_label' => 'View options',
                        'sections' => [
                            [
                                'title' => 'Audience',
                                'rows' => [
                                    ['id' => 'vip', 'title' => 'VIP', 'description' => __('Priority leads')],
                                    ['id' => 'standard', 'title' => 'Standard', 'description' => __('General leads')],
                                ],
                            ],
                        ],
                    ],
                    'ui' => ['expanded' => false],
                ],
                [
                    'id' => 'send-vip',
                    'type' => 'send_text',
                    'position' => ['x' => 760, 'y' => 40],
                    'config' => [
                        'text' => 'VIP flow selected.',
                    ],
                    'ui' => ['expanded' => false],
                ],
                [
                    'id' => 'send-standard',
                    'type' => 'send_text',
                    'position' => ['x' => 760, 'y' => 220],
                    'config' => [
                        'text' => 'Standard flow selected.',
                    ],
                    'ui' => ['expanded' => false],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 1100, 'y' => 120],
                    'config' => [],
                    'ui' => ['expanded' => false],
                ],
            ],
            'edges' => [
                [
                    'id' => 'edge-trigger-list',
                    'source_id' => 'trigger-1',
                    'target_id' => 'list-1',
                    'branch' => 'default',
                ],
                [
                    'id' => 'edge-list-vip',
                    'source_id' => 'list-1',
                    'target_id' => 'send-vip',
                    'branch' => 'vip',
                ],
                [
                    'id' => 'edge-list-standard',
                    'source_id' => 'list-1',
                    'target_id' => 'send-standard',
                    'branch' => 'standard',
                ],
                [
                    'id' => 'edge-vip-end',
                    'source_id' => 'send-vip',
                    'target_id' => 'end-1',
                    'branch' => 'default',
                ],
                [
                    'id' => 'edge-standard-end',
                    'source_id' => 'send-standard',
                    'target_id' => 'end-1',
                    'branch' => 'default',
                ],
            ],
        ];
    }

    private function invalidListGraph(): array
    {
        $graph = $this->listRuntimeGraph();
        $graph['nodes'][1]['config']['sections'] = [
            [
                'title' => '',
                'rows' => [
                    ['id' => 'vip', 'title' => 'VIP', 'description' => __('Priority leads')],
                    ['id' => 'vip', 'title' => 'VIP Duplicate', 'description' => __('Duplicate path')],
                ],
            ],
        ];

        return $graph;
    }

    private function circularRuntimeGraph(): array
    {
        return [
            'start_node_id' => 'trigger-1',
            'nodes' => [
                [
                    'id' => 'trigger-1',
                    'type' => 'trigger',
                    'position' => ['x' => 80, 'y' => 120],
                    'config' => [
                        'match_mode' => 'any_incoming',
                        'keywords' => [],
                    ],
                    'ui' => ['expanded' => true],
                ],
                [
                    'id' => 'send-text-1',
                    'type' => 'send_text',
                    'position' => ['x' => 420, 'y' => 120],
                    'config' => [
                        'text' => 'Welcome to the loop.',
                    ],
                    'ui' => ['expanded' => false],
                ],
                [
                    'id' => 'send-text-2',
                    'type' => 'send_text',
                    'position' => ['x' => 760, 'y' => 120],
                    'config' => [
                        'text' => 'This step should not point back forever.',
                    ],
                    'ui' => ['expanded' => false],
                ],
            ],
            'edges' => [
                [
                    'id' => 'edge-trigger-text-1',
                    'source_id' => 'trigger-1',
                    'target_id' => 'send-text-1',
                    'branch' => 'default',
                ],
                [
                    'id' => 'edge-text-1-text-2',
                    'source_id' => 'send-text-1',
                    'target_id' => 'send-text-2',
                    'branch' => 'default',
                ],
                [
                    'id' => 'edge-text-2-text-1',
                    'source_id' => 'send-text-2',
                    'target_id' => 'send-text-1',
                    'branch' => 'default',
                ],
            ],
        ];
    }

    private function mediaPreviewGraph(string $assetUuid): array
    {
        return [
            'start_node_id' => 'trigger-1',
            'nodes' => [
                [
                    'id' => 'trigger-1',
                    'type' => 'trigger',
                    'position' => ['x' => 80, 'y' => 120],
                    'config' => [
                        'match_mode' => 'any_incoming',
                        'keywords' => [],
                    ],
                    'ui' => ['expanded' => true],
                ],
                [
                    'id' => 'send-media-1',
                    'type' => 'send_media',
                    'position' => ['x' => 420, 'y' => 120],
                    'config' => [
                        'media_type' => 'image',
                        'asset_id' => $assetUuid,
                        'caption' => 'Welcome image',
                    ],
                    'ui' => ['expanded' => false],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 760, 'y' => 120],
                    'config' => [],
                    'ui' => ['expanded' => false],
                ],
            ],
            'edges' => [
                [
                    'id' => 'edge-trigger-media',
                    'source_id' => 'trigger-1',
                    'target_id' => 'send-media-1',
                    'branch' => 'default',
                ],
                [
                    'id' => 'edge-media-end',
                    'source_id' => 'send-media-1',
                    'target_id' => 'end-1',
                    'branch' => 'default',
                ],
            ],
        ];
    }

    private function assignToAgentGraph(): array
    {
        return [
            'start_node_id' => 'trigger-1',
            'nodes' => [
                [
                    'id' => 'trigger-1',
                    'type' => 'trigger',
                    'position' => ['x' => 80, 'y' => 120],
                    'config' => [
                        'match_mode' => 'any_incoming',
                        'keywords' => [],
                    ],
                    'ui' => ['expanded' => true],
                ],
                [
                    'id' => 'assign-agent-1',
                    'type' => 'assign_to_agent',
                    'position' => ['x' => 420, 'y' => 120],
                    'config' => [
                        'assignment_mode' => 'auto_assign',
                        'agent_user_id' => null,
                        'reopen_closed_ticket' => true,
                    ],
                    'ui' => ['expanded' => false],
                ],
                [
                    'id' => 'send-text-1',
                    'type' => 'send_text',
                    'position' => ['x' => 760, 'y' => 120],
                    'config' => [
                        'text' => 'Your request is now with customer service.',
                    ],
                    'ui' => ['expanded' => false],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 1100, 'y' => 120],
                    'config' => [],
                    'ui' => ['expanded' => false],
                ],
            ],
            'edges' => [
                [
                    'id' => 'edge-trigger-assign',
                    'source_id' => 'trigger-1',
                    'target_id' => 'assign-agent-1',
                    'branch' => 'default',
                ],
                [
                    'id' => 'edge-assign-send',
                    'source_id' => 'assign-agent-1',
                    'target_id' => 'send-text-1',
                    'branch' => 'default',
                ],
                [
                    'id' => 'edge-send-end',
                    'source_id' => 'send-text-1',
                    'target_id' => 'end-1',
                    'branch' => 'default',
                ],
            ],
        ];
    }

    private function humanHandoffGraph(): array
    {
        return [
            'start_node_id' => 'trigger-1',
            'nodes' => [
                [
                    'id' => 'trigger-1',
                    'type' => 'trigger',
                    'position' => ['x' => 80, 'y' => 120],
                    'config' => [
                        'match_mode' => 'any_incoming',
                        'keywords' => [],
                    ],
                    'ui' => ['expanded' => true],
                ],
                [
                    'id' => 'handoff-human-1',
                    'type' => 'human_handoff',
                    'position' => ['x' => 420, 'y' => 120],
                    'config' => [
                        'assignment_mode' => 'auto_assign',
                        'agent_user_id' => null,
                        'reopen_closed_ticket' => true,
                    ],
                    'ui' => ['expanded' => false],
                ],
            ],
            'edges' => [
                [
                    'id' => 'edge-trigger-handoff',
                    'source_id' => 'trigger-1',
                    'target_id' => 'handoff-human-1',
                    'branch' => 'default',
                ],
            ],
        ];
    }

    private function aiHandoffGraph(): array
    {
        return [
            'start_node_id' => 'trigger-1',
            'nodes' => [
                [
                    'id' => 'trigger-1',
                    'type' => 'trigger',
                    'position' => ['x' => 80, 'y' => 120],
                    'config' => [
                        'match_mode' => 'any_incoming',
                        'keywords' => [],
                    ],
                    'ui' => ['expanded' => true],
                ],
                [
                    'id' => 'handoff-ai-1',
                    'type' => 'handoff_to_ai_assistant',
                    'position' => ['x' => 420, 'y' => 120],
                    'config' => [],
                    'ui' => ['expanded' => false],
                ],
            ],
            'edges' => [
                [
                    'id' => 'edge-trigger-ai',
                    'source_id' => 'trigger-1',
                    'target_id' => 'handoff-ai-1',
                    'branch' => 'default',
                ],
            ],
        ];
    }

    private function saveReplyToSessionGraph(string $variableKey): array
    {
        return [
            'start_node_id' => 'trigger-1',
            'nodes' => [
                [
                    'id' => 'trigger-1',
                    'type' => 'trigger',
                    'position' => ['x' => 80, 'y' => 120],
                    'config' => [
                        'match_mode' => 'any_incoming',
                        'keywords' => [],
                    ],
                    'ui' => ['expanded' => true],
                ],
                [
                    'id' => 'save-reply-1',
                    'type' => 'save_reply_to_field',
                    'position' => ['x' => 420, 'y' => 120],
                    'config' => [
                        'save_target' => 'session_variable',
                        'field_uuid' => '',
                        'variable_key' => $variableKey,
                    ],
                    'ui' => ['expanded' => false],
                ],
                [
                    'id' => 'condition-1',
                    'type' => 'condition',
                    'position' => ['x' => 760, 'y' => 120],
                    'config' => [
                        'source' => 'flow_variable',
                        'variable_key' => $variableKey,
                        'operator' => 'contains',
                        'value' => 'شراء',
                    ],
                    'ui' => ['expanded' => false],
                ],
                [
                    'id' => 'send-matched-1',
                    'type' => 'send_text',
                    'position' => ['x' => 1100, 'y' => 40],
                    'config' => [
                        'text' => 'تم حفظ اهتمامك بالشراء داخل التدفق.',
                    ],
                    'ui' => ['expanded' => false],
                ],
                [
                    'id' => 'send-unmatched-1',
                    'type' => 'send_text',
                    'position' => ['x' => 1100, 'y' => 220],
                    'config' => [
                        'text' => 'تم حفظ الرد داخل التدفق لكن بدون تطابق شراء.',
                    ],
                    'ui' => ['expanded' => false],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 1440, 'y' => 120],
                    'config' => [],
                    'ui' => ['expanded' => false],
                ],
            ],
            'edges' => [
                [
                    'id' => 'edge-trigger-save',
                    'source_id' => 'trigger-1',
                    'target_id' => 'save-reply-1',
                    'branch' => 'default',
                ],
                [
                    'id' => 'edge-save-condition',
                    'source_id' => 'save-reply-1',
                    'target_id' => 'condition-1',
                    'branch' => 'default',
                ],
                [
                    'id' => 'edge-condition-matched',
                    'source_id' => 'condition-1',
                    'target_id' => 'send-matched-1',
                    'branch' => 'matched',
                ],
                [
                    'id' => 'edge-condition-unmatched',
                    'source_id' => 'condition-1',
                    'target_id' => 'send-unmatched-1',
                    'branch' => 'unmatched',
                ],
                [
                    'id' => 'edge-send-matched-end',
                    'source_id' => 'send-matched-1',
                    'target_id' => 'end-1',
                    'branch' => 'default',
                ],
                [
                    'id' => 'edge-send-unmatched-end',
                    'source_id' => 'send-unmatched-1',
                    'target_id' => 'end-1',
                    'branch' => 'default',
                ],
            ],
        ];
    }

    private function updateSessionVariableGraph(): array
    {
        return [
            'start_node_id' => 'trigger-1',
            'nodes' => [
                [
                    'id' => 'trigger-1',
                    'type' => 'trigger',
                    'position' => ['x' => 80, 'y' => 120],
                    'config' => [
                        'match_mode' => 'any_incoming',
                        'keywords' => [],
                    ],
                    'ui' => ['expanded' => true],
                ],
                [
                    'id' => 'set-route-code-1',
                    'type' => 'update_contact_field',
                    'position' => ['x' => 420, 'y' => 120],
                    'config' => [
                        'save_target' => 'session_variable',
                        'field_uuid' => '',
                        'variable_key' => 'route_code',
                        'mode' => 'static',
                        'source_variable_key' => '',
                        'value' => 'vip',
                        'active' => true,
                    ],
                    'ui' => ['expanded' => false],
                ],
                [
                    'id' => 'condition-1',
                    'type' => 'condition',
                    'position' => ['x' => 760, 'y' => 120],
                    'config' => [
                        'source' => 'flow_variable',
                        'variable_key' => 'route_code',
                        'operator' => 'equals',
                        'value' => 'vip',
                    ],
                    'ui' => ['expanded' => false],
                ],
                [
                    'id' => 'send-matched-1',
                    'type' => 'send_text',
                    'position' => ['x' => 1100, 'y' => 40],
                    'config' => [
                        'text' => 'تم حفظ القيمة الداخلية لهذا التدفق.',
                    ],
                    'ui' => ['expanded' => false],
                ],
                [
                    'id' => 'send-unmatched-1',
                    'type' => 'send_text',
                    'position' => ['x' => 1100, 'y' => 220],
                    'config' => [
                        'text' => 'لم يتم حفظ القيمة الداخلية لهذا التدفق.',
                    ],
                    'ui' => ['expanded' => false],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 1440, 'y' => 120],
                    'config' => [],
                    'ui' => ['expanded' => false],
                ],
            ],
            'edges' => [
                [
                    'id' => 'edge-trigger-update',
                    'source_id' => 'trigger-1',
                    'target_id' => 'set-route-code-1',
                    'branch' => 'default',
                ],
                [
                    'id' => 'edge-update-condition',
                    'source_id' => 'set-route-code-1',
                    'target_id' => 'condition-1',
                    'branch' => 'default',
                ],
                [
                    'id' => 'edge-condition-matched',
                    'source_id' => 'condition-1',
                    'target_id' => 'send-matched-1',
                    'branch' => 'matched',
                ],
                [
                    'id' => 'edge-condition-unmatched',
                    'source_id' => 'condition-1',
                    'target_id' => 'send-unmatched-1',
                    'branch' => 'unmatched',
                ],
                [
                    'id' => 'edge-send-matched-end',
                    'source_id' => 'send-matched-1',
                    'target_id' => 'end-1',
                    'branch' => 'default',
                ],
                [
                    'id' => 'edge-send-unmatched-end',
                    'source_id' => 'send-unmatched-1',
                    'target_id' => 'end-1',
                    'branch' => 'default',
                ],
            ],
        ];
    }

    private function smtpSecrets(): array
    {
        return [
            'host' => 'smtp.example.com',
            'port' => '587',
            'username' => 'mailer@example.com',
            'password' => 'super-secret',
            'encryption' => 'tls',
            'from_name' => 'Botzo',
            'from_email' => 'mailer@example.com',
        ];
    }

    private function publishGraph(\App\Models\User $user, int $organizationId, AutomationFlow $flow, array $graph): void
    {
        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organizationId])
            ->putJson("/automation/flows/{$flow->uuid}", [
                'name' => $flow->name,
                'description' => $flow->description,
                'graph_json' => $graph,
                'ui_json' => ['preview' => ['selected_scenario' => 'main']],
                'node_secrets' => [],
            ])
            ->assertOk();

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organizationId])
            ->postJson("/automation/flows/{$flow->uuid}/publish")
            ->assertOk();
    }

    private function mockRuntimeWhatsappService(?array &$sentMessages = null): void
    {
        $mock = Mockery::mock('overload:App\Services\WhatsappService');

        $mock->shouldReceive('sendMessage')
            ->andReturnUsing(function (...$arguments) use (&$sentMessages) {
                if (is_array($sentMessages)) {
                    $sentMessages[] = $arguments;
                }

                $response = new \stdClass;
                $response->success = true;
                $response->data = (object) [
                    'messages' => [
                        (object) ['id' => 'wam-'.Str::random(10)],
                    ],
                ];

                return $response;
            });
    }

    private function createContact(int $organizationId, int $userId, string $phone): Contact
    {
        return Contact::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'first_name' => 'Lead',
            'phone' => $phone,
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createInboundChat(Contact $contact, array $metadata): Chat
    {
        return Chat::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $contact->organization_id,
            'wam_id' => 'wam-' . Str::random(10),
            'contact_id' => $contact->id,
            'type' => 'inbound',
            'metadata' => json_encode($metadata),
            'status' => 'received',
            'created_at' => now(),
        ]);
    }

    private function createFlow(int $userId, int $organizationId, string $name): AutomationFlow
    {
        $this->withoutMiddleware()
            ->actingAs(\App\Models\User::query()->findOrFail($userId), 'user')
            ->withSession(['current_organization' => $organizationId])
            ->postJson('/automation/flows', [
                'name' => $name,
                'description' => __('Automation flow for testing.'),
                'goal_preset' => 'sales_qualification',
            ])
            ->assertOk();

        return AutomationFlow::query()
            ->where('organization_id', $organizationId)
            ->latest('id')
            ->firstOrFail();
    }

    private function enableFlowBuilderForOrganization(int $organizationId, bool $addonActive = true, bool $planEntitled = true): void
    {
        Addon::updateOrCreate(
            ['name' => 'Flow builder'],
            [
                'uuid' => Addon::query()->where('name', 'Flow builder')->value('uuid') ?: (string) \Illuminate\Support\Str::uuid(),
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

    private function enableTicketingForOrganization(int $organizationId, bool $autoAssignment = true, bool $reassignReopened = true): void
    {
        $organization = Organization::query()->findOrFail($organizationId);
        $metadata = json_decode((string) $organization->metadata, true) ?: [];

        Arr::set($metadata, 'tickets.active', true);
        Arr::set($metadata, 'tickets.auto_assignment', $autoAssignment);
        Arr::set($metadata, 'tickets.reassign_reopened_chats', $reassignReopened);

        $organization->update([
            'metadata' => json_encode($metadata),
        ]);
    }

    private function enableAiAssistantForOrganization(int $organizationId, bool $moduleEnabled = true, bool $assistantActive = true): void
    {
        Addon::updateOrCreate(
            ['name' => 'AI Assistant'],
            [
                'uuid' => Addon::query()->where('name', 'AI Assistant')->value('uuid') ?: (string) Str::uuid(),
                'category' => 'automation',
                'logo' => 'ai_assistant.png',
                'description' => __('AI Assistant'),
                'metadata' => json_encode(['name' => 'AI Assistant']),
                'status' => 1,
                'is_active' => $moduleEnabled ? 1 : 0,
                'is_plan_restricted' => 1,
            ]
        );

        $subscription = \App\Models\Subscription::query()
            ->where('organization_id', $organizationId)
            ->with('plan')
            ->orderByDesc('valid_until')
            ->first();

        if ($subscription?->plan) {
            $metadata = $subscription->plan->metadata;

            if (is_string($metadata)) {
                $metadata = json_decode($metadata, true) ?: [];
            }

            if (!is_array($metadata)) {
                $metadata = [];
            }

            $addons = $metadata['addons'] ?? [];
            if (!is_array($addons)) {
                $addons = [];
            }

            $addons['Flow builder'] = true;
            $addons['AI Assistant'] = $moduleEnabled;
            $metadata['addons'] = $addons;

            $subscription->plan->update([
                'metadata' => json_encode($metadata),
            ]);
        } else {
            $this->createActiveSubscription($organizationId, [
                'addons' => [
                    'Flow builder' => true,
                    'AI Assistant' => $moduleEnabled,
                ],
            ]);
        }

        $organization = Organization::query()->findOrFail($organizationId);
        $metadata = json_decode((string) $organization->metadata, true) ?: [];
        Arr::set($metadata, 'ai.active', $assistantActive);

        $organization->update([
            'metadata' => json_encode($metadata),
        ]);
    }

    private function allowExternalActionsInBuilderPolicy(): void
    {
        config()->set('automation_flows.builder_policy.whatsapp_only_mode', false);
        config()->set('automation_flows.builder_policy.allow_external_actions', true);
    }

    private function blockExternalActionsInBuilderPolicy(): void
    {
        config()->set('automation_flows.builder_policy.whatsapp_only_mode', true);
        config()->set('automation_flows.builder_policy.allow_external_actions', false);
    }

    private function addAssignableAgent(int $organizationId, int $createdByUserId): User
    {
        $role = OrganizationRole::firstOrCreate(
            [
                'organization_id' => null,
                'name' => 'Owner',
            ],
            [
                'description' => __('Universal owner role'),
                'permissions' => ['*'],
            ]
        );

        $agent = User::create([
            'first_name' => 'Service',
            'last_name' => 'Agent',
            'email' => 'agent+'.Str::random(8).'@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        Team::create([
            'organization_id' => $organizationId,
            'user_id' => $agent->id,
            'organization_role_id' => $role->id,
            'status' => 'active',
            'created_by' => $createdByUserId,
        ]);

        return $agent;
    }
}

