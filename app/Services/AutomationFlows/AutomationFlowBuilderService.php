<?php

namespace App\Services\AutomationFlows;

use App\Models\AutomationFlow;
use App\Models\AutomationFlowVersion;
use App\Models\ContactField;
use App\Models\ContactGroup;
use App\Services\OrganizationHierarchyService;
use App\Services\SubscriptionPlanLimitService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AutomationFlowBuilderService
{
    public function __construct(
        private readonly AutomationFlowGraphValidator $validator,
        private readonly AutomationFlowGraphCompiler $compiler,
        private readonly AutomationFlowPreviewService $previewService,
        private readonly AutomationFlowNodeCatalog $catalog,
        private readonly AutomationFlowStarterTemplateService $starterTemplates,
        private readonly AutomationFlowBuilderPolicyService $builderPolicy,
        private readonly AutomationFlowAssetService $assetService,
        private readonly AutomationFlowNodeSecretService $secretService,
        private readonly AutomationFlowWhatsappComplianceService $whatsappCompliance,
        private readonly AutomationFlowConversationHandoffService $conversationHandoff,
        private readonly SubscriptionPlanLimitService $planLimitService,
        private readonly OrganizationHierarchyService $organizationHierarchyService,
    ) {
    }

    public function list(int $organizationId, ?string $search = null, ?string $status = null)
    {
        return AutomationFlow::query()
            ->where('organization_id', $organizationId)
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            })
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest('updated_at')
            ->paginate(10)
            ->through(function (AutomationFlow $flow) {
                return [
                    'id' => $flow->id,
                    'uuid' => $flow->uuid,
                    'name' => $flow->name,
                    'description' => $flow->description,
                    'goal_preset' => $flow->goal_preset,
                    'status' => $flow->status,
                    'runs_count' => $flow->runs_count,
                    'has_unpublished_changes' => $flow->has_unpublished_changes,
                    'updated_at' => optional($flow->updated_at)->toDateTimeString(),
                    'last_published_at' => optional($flow->last_published_at)->toDateTimeString(),
                ];
            });
    }

    public function create(int $organizationId, int $userId, array $payload): AutomationFlow
    {
        $goalPreset = $this->starterTemplates->normalizeGoalPreset(
            $payload['goal_preset'] ?? (string) config('automation_flows.default_goal_preset', 'sales_qualification')
        );
        $graph = $this->starterTemplates->starterGraphForOrganization($goalPreset, $organizationId);
        $preferredNodeId = $this->starterTemplates->preferredActiveNodeId($graph);

        return AutomationFlow::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'name' => $payload['name'],
            'description' => $payload['description'] ?? null,
            'goal_preset' => $goalPreset,
            'channel' => 'whatsapp',
            'trigger_type' => 'incoming_whatsapp_message',
            'status' => 'draft',
            'graph_json' => $graph,
            'ui_json' => [
                'surface' => ['variant' => 'canvas'],
                'mode' => 'simple',
                'library' => ['tab' => 'messages'],
                'selection' => ['active_node_id' => $preferredNodeId],
                'canvas' => ['expanded_node_id' => $preferredNodeId],
                'right_dock' => [
                    'tab' => 'inspector',
                    'last_non_preview_tab' => 'inspector',
                ],
                'preview' => [
                    'selected_scenario' => config('automation_flows.preview_default_scenario', 'main'),
                    'collapsed' => false,
                    'mode' => 'whatsapp',
                ],
            ],
            'created_by' => $userId,
            'updated_by' => $userId,
            'has_unpublished_changes' => true,
        ]);
    }

    public function update(AutomationFlow $flow, int $organizationId, int $userId, array $payload): AutomationFlow
    {
        $this->ensureOwnership($flow, $organizationId);
        $graph = $this->secretService->sanitizeGraphAndSyncSecrets(
            $flow,
            $payload['graph_json'],
            $payload['node_secrets'] ?? []
        );

        $flow->update([
            'name' => $payload['name'],
            'description' => $payload['description'] ?? null,
            'graph_json' => $graph,
            'ui_json' => $payload['ui_json'] ?? ($flow->ui_json ?? []),
            'updated_by' => $userId,
            'has_unpublished_changes' => true,
        ]);

        return $flow->fresh(['currentVersion', 'assets', 'nodeSecrets']);
    }

    public function validateDraft(AutomationFlow $flow, int $organizationId, ?array $graphOverride = null, array $nodeSecrets = []): array
    {
        $this->ensureOwnership($flow, $organizationId);
        $prepared = $this->secretService->sanitizeGraphForValidation(
            $flow,
            $graphOverride ?? ($flow->graph_json ?? []),
            $nodeSecrets
        );

        return $this->validator->validate(
            $prepared['graph'],
            $organizationId,
            $flow,
            $prepared['node_secrets']
        );
    }

    public function publish(AutomationFlow $flow, int $organizationId, int $userId): AutomationFlow
    {
        $this->ensureOwnership($flow, $organizationId);
        $this->assertActiveFlowPublishLimit($flow, $organizationId);

        $graph = $flow->graph_json ?? [];
        $this->ensureGraphRespectsBuilderPolicy($graph);
        $this->validator->ensureValid($graph, $organizationId, $flow);
        $compiled = $this->compiler->compile($graph);
        $nextVersion = (int) $flow->versions()->max('version_number') + 1;

        $version = AutomationFlowVersion::create([
            'uuid' => (string) Str::uuid(),
            'automation_flow_id' => $flow->id,
            'organization_id' => $organizationId,
            'version_number' => $nextVersion,
            'label' => 'v' . $nextVersion,
            'graph_json' => $graph,
            'ui_json' => $flow->ui_json ?? [],
            'compiled_json' => $compiled,
            'published_by' => $userId,
            'published_at' => now(),
        ]);

        $flow->update([
            'status' => 'published',
            'current_version_id' => $version->id,
            'last_published_at' => now(),
            'has_unpublished_changes' => false,
            'updated_by' => $userId,
        ]);

        return $flow->fresh(['currentVersion']);
    }

    public function pause(AutomationFlow $flow, int $organizationId, bool $pause = true): AutomationFlow
    {
        $this->ensureOwnership($flow, $organizationId);
        if (!$pause) {
            $this->assertActiveFlowPublishLimit($flow, $organizationId);
        }

        $flow->update([
            'status' => $pause ? 'paused' : ($flow->current_version_id ? 'published' : 'draft'),
        ]);

        return $flow->fresh(['currentVersion']);
    }

    public function preview(AutomationFlow $flow, int $organizationId, ?array $graphOverride = null, ?string $focusNodeId = null): array
    {
        $this->ensureOwnership($flow, $organizationId);

        return $this->previewService->project($flow, $graphOverride ?? ($flow->graph_json ?? []), $focusNodeId);
    }

    public function duplicate(AutomationFlow $flow, int $organizationId, int $userId): AutomationFlow
    {
        $this->ensureOwnership($flow, $organizationId);
        $assetsTableReady = Schema::hasTable('automation_flow_assets');
        $secretsTableReady = Schema::hasTable('automation_flow_node_secrets');

        return DB::transaction(function () use ($flow, $userId, $assetsTableReady, $secretsTableReady) {
            if ($assetsTableReady) {
                $flow->loadMissing('assets');
            }
            if ($secretsTableReady) {
                $flow->loadMissing('nodeSecrets');
            }

            $graph = $flow->graph_json ?? $this->defaultGraph();
            $duplicate = AutomationFlow::create([
                'uuid' => (string) Str::uuid(),
                'organization_id' => $flow->organization_id,
                'name' => __(':name Copy', ['name' => $flow->name]),
                'description' => $flow->description,
                'goal_preset' => $flow->goal_preset,
                'channel' => $flow->channel,
                'trigger_type' => $flow->trigger_type,
                'status' => 'draft',
                'graph_json' => $graph,
                'ui_json' => $this->freshUiState($flow->ui_json ?? []),
                'created_by' => $userId,
                'updated_by' => $userId,
                'has_unpublished_changes' => true,
                'current_version_id' => null,
                'last_published_at' => null,
                'runs_count' => 0,
            ]);

            $assetMap = $assetsTableReady ? $this->assetService->duplicateAll($flow, $duplicate, $userId) : [];
            $secretMap = $secretsTableReady ? $this->secretService->duplicateAll($flow, $duplicate, $graph) : [];

            $duplicate->update([
                'graph_json' => $this->remapGraphReferences($graph, $assetMap, $secretMap),
            ]);

            return $duplicate->fresh(['assets', 'nodeSecrets']);
        });
    }

    public function builderPayload(AutomationFlow $flow, int $organizationId): array
    {
        $this->ensureOwnership($flow, $organizationId);
        $assetsTableReady = Schema::hasTable('automation_flow_assets');
        $nodeSecretsTableReady = Schema::hasTable('automation_flow_node_secrets');

        $relations = [];
        if ($assetsTableReady) {
            $relations[] = 'assets';
        }
        if ($nodeSecretsTableReady) {
            $relations[] = 'nodeSecrets';
        }

        if ($relations !== []) {
            $flow->loadMissing($relations);
        }
        $graph = $flow->graph_json ?? $this->defaultGraph();
        $advancedEnabled = $this->planLimitService->boolForOrganization(
            $organizationId,
            'flow_builder_advanced_enabled',
            true
        );
        $builderPolicy = $this->builderPolicy->resolvedPolicy();
        $handoffCapabilities = $this->conversationHandoff->capabilitiesForOrganization($organizationId);
        $advancedTypes = $this->catalog->advancedTypes();
        $library = collect($this->catalog->all($builderPolicy))
            ->when(!$advancedEnabled, function ($items) use ($advancedTypes) {
                return $items->reject(fn (array $item) => in_array((string) ($item['type'] ?? ''), $advancedTypes, true));
            })
            ->values()
            ->all();

        return [
            'flow' => [
                'id' => $flow->id,
                'uuid' => $flow->uuid,
                'name' => $flow->name,
                'description' => $flow->description,
                'goal_preset' => $flow->goal_preset,
                'status' => $flow->status,
                'channel' => $flow->channel,
                'trigger_type' => $flow->trigger_type,
                'graph_json' => $graph,
                'ui_json' => $flow->ui_json ?? [],
                'has_unpublished_changes' => $flow->has_unpublished_changes,
                'last_published_at' => optional($flow->last_published_at)->toDateTimeString(),
            ],
            'builder_runtime' => [
                'surface_version' => 'simple_canvas_v1',
                'default_mode' => 'simple',
                'available_modes' => ['simple'],
                'layout_model' => 'automation_canvas_inline',
                'library_strategy' => 'catalog_drag_drop',
                'right_dock_tabs' => ['inspector', 'guide', 'preview'],
                'autosave_debounce_ms' => (int) config('automation_flows.autosave_debounce_ms', 1200),
                'ui_enhancements_enabled' => (bool) config('automation_flows.ui_enhancements_enabled', true),
                'builder_policy' => [
                    'channel' => (string) ($builderPolicy['channel'] ?? 'whatsapp'),
                    'whatsapp_only_mode' => (bool) ($builderPolicy['whatsapp_only_mode'] ?? false),
                    'allow_external_actions' => (bool) ($builderPolicy['allow_external_actions'] ?? true),
                    'allow_crm_actions' => (bool) ($builderPolicy['allow_crm_actions'] ?? true),
                ],
                'runtime' => [
                    'active_run_stale_minutes' => (int) config('automation_flows.runtime.active_run_stale_minutes', 30),
                    'waiting_input_stale_minutes' => (int) config('automation_flows.runtime.waiting_input_stale_minutes', 1440),
                    'waiting_handoff_stale_minutes' => (int) config('automation_flows.runtime.waiting_handoff_stale_minutes', 10080),
                    'invalid_reply_default_behavior' => (string) config('automation_flows.runtime.invalid_reply_default_behavior', 'release_to_fallback'),
                    'invalid_reply_behaviors' => array_values(config('automation_flows.runtime.invalid_reply_behaviors', [
                        'release_to_fallback',
                    ])),
                    'whatsapp' => [
                        'customer_care_window_hours' => max(1, (int) config('automation_flows.whatsapp.customer_care_window_hours', 24)),
                        'enforce_customer_care_window' => (bool) config('automation_flows.whatsapp.enforce_customer_care_window', true),
                        'preview_customer_care_window_open' => (bool) config('automation_flows.whatsapp.preview_customer_care_window_open', true),
                        'on_window_closed' => $this->normalizeWindowClosedAction((string) config('automation_flows.whatsapp.on_window_closed', 'fail_run')),
                    ],
                ],
                'whatsapp_compliance' => $this->whatsappCompliance->builderConstraints(),
                'handoff_capabilities' => $handoffCapabilities,
            ],
            'library' => $library,
            'plan_limits' => [
                'active_flows' => $this->planLimitService->limitForOrganization(
                    $organizationId,
                    'flow_builder_active_flows_limit',
                    -1
                ),
                'nodes_per_flow' => $this->planLimitService->limitForOrganization(
                    $organizationId,
                    'flow_builder_nodes_per_flow_limit',
                    -1
                ),
                'monthly_runs' => $this->planLimitService->limitForOrganization(
                    $organizationId,
                    'flow_builder_monthly_runs_limit',
                    -1
                ),
                'advanced_enabled' => $advancedEnabled,
            ],
            'contact_fields' => ContactField::where('organization_id', $organizationId)
                ->orderBy('name')
                ->get(['uuid', 'name'])
                ->map(fn ($field) => ['uuid' => $field->uuid, 'name' => $field->name])
                ->values(),
            'contact_groups' => ContactGroup::where('organization_id', $organizationId)
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(['uuid', 'name'])
                ->map(fn ($group) => ['uuid' => $group->uuid, 'name' => $group->name])
                ->values(),
            'assignable_agents' => collect($handoffCapabilities['assignable_agents'] ?? [])
                ->map(fn (array $agent) => [
                    'user_id' => (int) ($agent['user_id'] ?? 0),
                    'team_id' => (int) ($agent['team_id'] ?? 0),
                    'name' => (string) ($agent['name'] ?? ''),
                ])
                ->values(),
            'assets' => $assetsTableReady
                ? $flow->assets
                    ->mapWithKeys(fn ($asset) => [$asset->uuid => $this->assetService->toBuilderArray($flow, $asset)])
                    ->all()
                : [],
            'node_secrets' => $nodeSecretsTableReady
                ? $this->secretService->builderPayloadForFlow($flow)
                : [],
            'preview' => $this->previewService->project(
                $flow,
                $graph,
                Arr::get($flow->ui_json, 'canvas.expanded_node_id') ?: Arr::get($flow->ui_json, 'selection.active_node_id')
            ),
            'validation' => $this->validator->validate($graph, $organizationId, $flow),
        ];
    }

    public function defaultGraph(): array
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
            ],
            'edges' => [],
        ];
    }

    private function ensureOwnership(AutomationFlow $flow, int $organizationId): void
    {
        abort_if((int) $flow->organization_id !== $organizationId, 404);
    }

    private function assertActiveFlowPublishLimit(AutomationFlow $flow, int $organizationId): void
    {
        $familyOrganizationIds = $this->organizationHierarchyService->familyOrganizationIds($organizationId);
        $limit = $this->planLimitService->limitForOrganization(
            $organizationId,
            'flow_builder_active_flows_limit',
            -1
        );

        if ($limit < 0 || $flow->status === 'published') {
            return;
        }

        $publishedFlowsCount = AutomationFlow::query()
            ->whereIn('organization_id', $familyOrganizationIds !== [] ? $familyOrganizationIds : [$organizationId])
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->count();

        if ($publishedFlowsCount < $limit) {
            return;
        }

        throw ValidationException::withMessages([
            'graph' => [
                __('Active Flow Builder limit reached for your current plan. Please upgrade your plan to publish more flows.'),
            ],
        ]);
    }

    private function freshUiState(array $uiJson): array
    {
        return array_replace_recursive($uiJson, [
            'mode' => 'simple',
            'surface' => ['variant' => 'canvas'],
            'selection' => ['active_node_id' => null],
            'canvas' => ['expanded_node_id' => null],
            'preview' => ['collapsed' => false, 'mode' => 'whatsapp'],
        ]);
    }

    private function normalizeWindowClosedAction(string $action): string
    {
        return in_array($action, ['fail_run', 'release_to_fallback'], true)
            ? $action
            : 'fail_run';
    }

    private function ensureGraphRespectsBuilderPolicy(array $graph): void
    {
        $blockedTypes = $this->builderPolicy->blockedNodeTypes($graph);

        if ($blockedTypes === []) {
            return;
        }

        $blockedLabels = collect($blockedTypes)
            ->map(function (string $type): string {
                $definition = $this->catalog->definition($type);

                return __((string) ($definition['label'] ?? $type));
            })
            ->implode(', ');

        throw ValidationException::withMessages([
            'graph' => [
                __('Flow Builder policy blocks one or more steps in this flow: :steps', [
                    'steps' => $blockedLabels,
                ]),
            ],
        ]);
    }

    /**
     * @param  array<string, string>  $assetMap
     * @param  array<string, string>  $secretMap
     */
    private function remapGraphReferences(array $graph, array $assetMap, array $secretMap): array
    {
        $graph['nodes'] = collect(Arr::get($graph, 'nodes', []))
            ->map(function (array $node) use ($assetMap, $secretMap): array {
                $assetId = Arr::get($node, 'config.asset_id');
                $secretRef = Arr::get($node, 'config.secret_ref');
                $nodeKey = ($node['id'] ?? '').':'.($node['type'] ?? '');

                if (is_string($assetId) && isset($assetMap[$assetId])) {
                    $node['config']['asset_id'] = $assetMap[$assetId];
                }

                if (is_string($secretRef) && isset($secretMap[$nodeKey])) {
                    $node['config']['secret_ref'] = $secretMap[$nodeKey];
                }

                return $node;
            })
            ->values()
            ->all();

        return $graph;
    }
}
