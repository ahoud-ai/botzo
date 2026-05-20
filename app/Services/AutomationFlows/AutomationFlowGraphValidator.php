<?php

namespace App\Services\AutomationFlows;

use App\Models\AutomationFlow;
use App\Models\AutomationFlowAsset;
use App\Models\ContactField;
use App\Models\ContactGroup;
use App\Services\SubscriptionPlanLimitService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AutomationFlowGraphValidator
{
    private const VALID_INVALID_REPLY_BEHAVIORS = [
        'release_to_fallback',
        'repeat_prompt',
        'end_run',
    ];

    public function __construct(
        private readonly AutomationFlowNodeCatalog $catalog,
        private readonly AutomationFlowBuilderPolicyService $builderPolicy,
        private readonly AutomationFlowNodeSecretService $nodeSecrets,
        private readonly SubscriptionPlanLimitService $planLimitService,
        private readonly AutomationFlowWhatsappComplianceService $whatsappCompliance,
        private readonly AutomationFlowConversationHandoffService $conversationHandoff,
        private readonly AutomationFlowSessionVariableService $sessionVariables,
    ) {
    }

    public function validate(array $graph, int $organizationId, ?AutomationFlow $flow = null, array $nodeSecrets = []): array
    {
        $nodes = collect(Arr::get($graph, 'nodes', []))->values();
        $edges = collect(Arr::get($graph, 'edges', []))->values();
        $startNodeId = Arr::get($graph, 'start_node_id');
        $errors = [];
        $warnings = [];
        $this->validatePlanLimits($organizationId, $nodes, $errors);

        if ($nodes->isEmpty()) {
            $errors[] = __('Add at least one node before saving this automation.');
        }

        if (!$startNodeId || !$nodes->contains(fn ($node) => ($node['id'] ?? null) === $startNodeId)) {
            $errors[] = __('Choose a valid trigger node as the starting step.');
        }

        if ($nodes->filter(fn ($node) => ($node['type'] ?? null) === 'trigger')->count() !== 1) {
            $errors[] = __('Each automation needs exactly one trigger node.');
        }

        $nodeIndex = $nodes->keyBy('id');
        $reachable = $this->reachableNodeIds($startNodeId, $edges);

        foreach ($nodes as $node) {
            $nodeId = (string) ($node['id'] ?? '');
            $type = (string) ($node['type'] ?? '');

            if ($nodeId === '' || !$this->catalog->isValid($type)) {
                $errors[] = __('This automation contains an unsupported node type.');
                continue;
            }

            if (!$reachable->contains($nodeId)) {
                $errors[] = __('Remove disconnected steps or reconnect them to the main journey.');
            }

            foreach ($this->validateNodeConfig($nodeId, $type, Arr::get($node, 'config', []), $organizationId, $flow, $nodeSecrets) as $nodeError) {
                $errors[] = $nodeError;
            }
        }

        if ($nodes->contains(fn ($node) => in_array((string) ($node['type'] ?? ''), ['send_text', 'send_media', 'send_buttons', 'send_list'], true))) {
            $warnings[] = __('Free-form WhatsApp steps send only inside the customer care window (:hours hours). If this journey resumes later, send a template message first.', [
                'hours' => max(1, (int) config('automation_flows.whatsapp.customer_care_window_hours', 24)),
            ]);
        }

        foreach ($edges as $edge) {
            $sourceId = (string) ($edge['source_id'] ?? $edge['source'] ?? '');
            $targetId = (string) ($edge['target_id'] ?? $edge['target'] ?? '');

            if (!$nodeIndex->has($sourceId) || !$nodeIndex->has($targetId)) {
                $errors[] = __('One of the journey connections points to a missing step.');
                continue;
            }

            if ($sourceId === $targetId) {
                $errors[] = __('A step cannot connect to itself.');
            }
        }

        if ($this->hasCircularPath($startNodeId, $edges)) {
            $errors[] = __('Remove circular paths before publishing this automation.');
        }

        foreach ($nodes as $node) {
            $nodeId = (string) ($node['id'] ?? '');
            $type = (string) ($node['type'] ?? '');
            $outgoing = $edges->filter(function ($edge) use ($nodeId) {
                return ($edge['source_id'] ?? $edge['source'] ?? null) === $nodeId;
            })->values();
            $config = Arr::get($node, 'config', []);

            if ($type === 'end' && $outgoing->isNotEmpty()) {
                $errors[] = __('The end step should not connect to any other step.');
            }

            if (in_array($type, ['human_handoff', 'handoff_to_ai_assistant'], true) && $outgoing->isNotEmpty()) {
                $errors[] = __('Handoff steps should finish the journey instead of connecting to another step.');
            }

            if ($type === 'trigger' && $outgoing->isEmpty()) {
                $errors[] = __('Choose the first step that should start after the trigger.');
            }

            if ($type === 'condition') {
                $branches = $outgoing->map(fn ($edge) => $edge['branch'] ?? null)->filter()->values()->all();
                if (!in_array('matched', $branches, true) || !in_array('unmatched', $branches, true)) {
                    $errors[] = __('Condition steps need both matched and unmatched branches.');
                }
            }

            if ($type === 'send_buttons') {
                $buttonIds = $this->buttonIds($config);
                $branches = $outgoing->map(fn ($edge) => $edge['branch'] ?? null)->filter()->values()->all();
                foreach ($buttonIds as $buttonId) {
                    if (!in_array($buttonId, $branches, true)) {
                        $errors[] = __('Connect every button reply to its own next step before publishing.');
                    }
                }
            }

            if ($type === 'send_list') {
                $rowIds = $this->listRowIds($config);
                $branches = $outgoing->map(fn ($edge) => $edge['branch'] ?? null)->filter()->values()->all();
                foreach ($rowIds as $rowId) {
                    if (!in_array($rowId, $branches, true)) {
                        $errors[] = __('Connect every list choice to its own next step before publishing.');
                    }
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    public function ensureValid(array $graph, int $organizationId, ?AutomationFlow $flow = null, array $nodeSecrets = []): array
    {
        $report = $this->validate($graph, $organizationId, $flow, $nodeSecrets);

        if (!$report['valid']) {
            throw ValidationException::withMessages([
                'graph' => $report['errors'],
            ]);
        }

        return $report;
    }

    private function validateNodeConfig(string $nodeId, string $type, array $config, int $organizationId, ?AutomationFlow $flow = null, array $nodeSecrets = []): array
    {
        if (!$this->builderPolicy->allowsNodeType($type)) {
            $definition = $this->catalog->definition($type);
            $label = __((string) ($definition['label'] ?? $type));

            return [
                __('Flow Builder policy blocks :step actions for this workspace.', ['step' => $label]),
            ];
        }

        $errors = [];

        if ($type === 'trigger') {
            $matchMode = (string) Arr::get($config, 'match_mode', '');
            if (!in_array($matchMode, ['any_incoming', 'first_in_conversation', 'keyword_match'], true)) {
                $errors[] = __('Choose how this automation should start from an incoming WhatsApp message.');
            }

            if ($matchMode === 'keyword_match' && collect(Arr::get($config, 'keywords', []))->filter()->isEmpty()) {
                $errors[] = __('Add at least one keyword before publishing a keyword-based trigger.');
            }
        }

        if ($type === 'send_text' && trim((string) Arr::get($config, 'text', '')) === '') {
            $errors[] = __('Text messages need content before publishing.');
        }

        if ($type === 'send_media') {
            if (!in_array((string) Arr::get($config, 'media_type', ''), ['image', 'video', 'audio', 'document'], true)) {
                $errors[] = __('Choose the media type that should be sent to the customer.');
            }
            if (!$this->assetExists($flow, (string) Arr::get($config, 'asset_id', ''))) {
                $errors[] = __('Upload a media file before publishing this step.');
            }
        }

        if ($type === 'send_buttons') {
            $buttons = collect(Arr::get($config, 'buttons', []))
                ->filter(fn ($button) => filled(Arr::get($button, 'id')) && filled(Arr::get($button, 'title')));
            if (trim((string) Arr::get($config, 'body', '')) === '' || $buttons->isEmpty()) {
                $errors[] = __('Interactive button messages need a body and at least one button.');
            }

            if (!in_array((string) Arr::get($config, 'invalid_reply_behavior', 'release_to_fallback'), self::VALID_INVALID_REPLY_BEHAVIORS, true)) {
                $errors[] = __('Choose how this step should react when the customer sends a normal message instead of tapping a reply.');
            }

            if ($this->hasDuplicateValues($this->buttonIds($config))) {
                $errors[] = __('Each button reply needs a unique internal ID before publishing.');
            }

            foreach ($this->whatsappCompliance->validateSendButtonsConfig($config) as $complianceError) {
                $errors[] = $complianceError;
            }
        }

        if ($type === 'send_list') {
            $sections = collect(Arr::get($config, 'sections', []));
            $hasRows = $sections
                ->flatMap(fn ($section) => Arr::get($section, 'rows', []))
                ->contains(fn ($row) => filled(Arr::get($row, 'id')) && filled(Arr::get($row, 'title')));

            if (trim((string) Arr::get($config, 'body', '')) === '' || trim((string) Arr::get($config, 'button_label', '')) === '' || !$hasRows) {
                $errors[] = __('Interactive lists need a body, button label, and at least one row.');
            }

            if (!in_array((string) Arr::get($config, 'invalid_reply_behavior', 'release_to_fallback'), self::VALID_INVALID_REPLY_BEHAVIORS, true)) {
                $errors[] = __('Choose how this step should react when the customer sends a normal message instead of tapping a reply.');
            }

            $hasRowsWithoutSectionTitle = $sections->contains(function ($section) {
                $title = trim((string) Arr::get($section, 'title', ''));
                $hasSectionRows = collect(Arr::get($section, 'rows', []))
                    ->contains(fn ($row) => filled(Arr::get($row, 'id')) && filled(Arr::get($row, 'title')));

                return $hasSectionRows && $title === '';
            });

            if ($hasRowsWithoutSectionTitle) {
                $errors[] = __('Every list section needs a title before publishing.');
            }

            if ($this->hasDuplicateValues($this->listRowIds($config))) {
                $errors[] = __('Each list choice needs a unique internal ID before publishing.');
            }

            foreach ($this->whatsappCompliance->validateSendListConfig($config) as $complianceError) {
                $errors[] = $complianceError;
            }
        }

        if ($type === 'save_reply_to_field') {
            $saveTarget = (string) Arr::get($config, 'save_target', 'contact_field');

            if (!in_array($saveTarget, ['contact_field', 'session_variable'], true)) {
                $errors[] = __('Choose where this step should save the customer reply.');
            }

            if ($saveTarget === 'contact_field') {
                $fieldUuid = (string) Arr::get($config, 'field_uuid', '');

                if ($fieldUuid === '' || !$this->fieldExists($organizationId, $fieldUuid)) {
                    $errors[] = __('Choose the contact field that should store the customer reply.');
                }
            }

            if ($saveTarget === 'session_variable' && !$this->sessionVariables->hasUsableKey((string) Arr::get($config, 'variable_key', ''))) {
                $errors[] = __('Choose a valid flow variable key for this reply step.');
            }
        }

        if ($type === 'condition') {
            $source = (string) Arr::get($config, 'source', '');
            $operator = (string) Arr::get($config, 'operator', '');
            if (!in_array($source, ['last_user_message', 'selected_button_id', 'selected_list_row_id', 'contact_field', 'flow_variable'], true)) {
                $errors[] = __('Choose what this condition should evaluate.');
            }
            if (!in_array($operator, ['equals', 'not_equals', 'contains', 'filled', 'not_filled'], true)) {
                $errors[] = __('Choose a valid rule for the condition step.');
            }
            if ($source === 'contact_field' && !$this->fieldExists($organizationId, (string) Arr::get($config, 'field_uuid', ''))) {
                $errors[] = __('Choose a valid contact field for this condition.');
            }
            if ($source === 'flow_variable' && !$this->sessionVariables->hasUsableKey((string) Arr::get($config, 'variable_key', ''))) {
                $errors[] = __('Choose a valid flow variable key for this condition.');
            }
        }

        if (in_array($type, ['add_to_group', 'remove_from_group'], true)) {
            $groupUuid = (string) Arr::get($config, 'group_uuid', '');
            if ($groupUuid === '' || !$this->groupExists($organizationId, $groupUuid)) {
                $errors[] = __('Choose a valid contact group for this action.');
            }
        }

        if ($type === 'update_contact_field') {
            $saveTarget = (string) Arr::get($config, 'save_target', 'contact_field');

            if (!in_array($saveTarget, ['contact_field', 'session_variable'], true)) {
                $errors[] = __('Choose where this step should save its value.');
            }

            if ($saveTarget === 'contact_field') {
                $fieldUuid = (string) Arr::get($config, 'field_uuid', '');

                if ($fieldUuid === '' || !$this->fieldExists($organizationId, $fieldUuid)) {
                    $errors[] = __('Choose the contact field that should be updated.');
                }
            }

            if ($saveTarget === 'session_variable' && !$this->sessionVariables->hasUsableKey((string) Arr::get($config, 'variable_key', ''))) {
                $errors[] = __('Choose a valid flow variable key for this step.');
            }

            $mode = (string) Arr::get($config, 'mode', Arr::get($config, 'value_mode', 'save_last_user_message'));
            if (!in_array($mode, ['save_last_user_message', 'static', 'last_input', 'session_variable'], true)) {
                $errors[] = __('Choose how the contact field should be updated.');
            }

            if ($mode === 'session_variable' && !$this->sessionVariables->hasUsableKey((string) Arr::get($config, 'source_variable_key', ''))) {
                $errors[] = __('Choose which flow variable should supply the value for this step.');
            }

            if ($mode === 'static' && trim((string) Arr::get($config, 'value', '')) === '') {
                $errors[] = __('Add the static value that should be stored in the contact field.');
            }
        }

        if ($type === 'assign_to_agent') {
            $errors = array_merge($errors, $this->validateAssignmentConfig($organizationId, $config, false));
        }

        if ($type === 'human_handoff') {
            $errors = array_merge($errors, $this->validateAssignmentConfig($organizationId, $config, true));
        }

        if ($type === 'handoff_to_ai_assistant') {
            $capabilities = $this->conversationHandoff->capabilitiesForOrganization($organizationId);

            if (!($capabilities['ai_assistant_module_enabled'] ?? false) || !($capabilities['ai_assistant_active'] ?? false)) {
                $errors[] = __('Activate the built-in AI assistant before publishing an AI handoff step.');
            }
        }

        if ($type === 'send_email') {
            if (trim((string) Arr::get($config, 'subject', '')) === '' || trim((string) Arr::get($config, 'body', '')) === '') {
                $errors[] = __('Email steps need a subject and body before publishing.');
            }

            if (!$this->hasEmailSecret($flow, $nodeId, (string) Arr::get($config, 'secret_ref', ''), $nodeSecrets)) {
                $errors[] = __('Complete the SMTP settings for this email step before publishing.');
            }
        }

        if ($type === 'delay' && max(0, (int) Arr::get($config, 'minutes', 0)) < 1) {
            $errors[] = __('Delay steps need a duration of at least one minute.');
        }

        return $errors;
    }

    private function validatePlanLimits(int $organizationId, $nodes, array &$errors): void
    {
        $nodesLimit = $this->planLimitService->limitForOrganization(
            $organizationId,
            'flow_builder_nodes_per_flow_limit',
            -1
        );

        if ($nodesLimit >= 0 && $nodes->count() > $nodesLimit) {
            $errors[] = __('Your current plan allows up to :limit nodes per flow. Please simplify this flow or upgrade your plan.', [
                'limit' => $nodesLimit,
            ]);
        }

        $advancedEnabled = $this->planLimitService->boolForOrganization(
            $organizationId,
            'flow_builder_advanced_enabled',
            true
        );

        if ($advancedEnabled) {
            return;
        }

        $advancedTypes = $this->catalog->advancedTypes();
        $usedAdvancedTypes = $nodes
            ->map(fn ($node) => (string) ($node['type'] ?? ''))
            ->filter(fn (string $type) => in_array($type, $advancedTypes, true))
            ->unique()
            ->values();

        if ($usedAdvancedTypes->isEmpty()) {
            return;
        }

        $stepLabels = $usedAdvancedTypes
            ->map(function (string $type): string {
                $definition = $this->catalog->definition($type);

                return __((string) ($definition['label'] ?? $type));
            })
            ->implode(', ');

        $errors[] = __('Your current plan does not include advanced Flow Builder steps. Upgrade your plan to use: :steps', [
            'steps' => $stepLabels,
        ]);
    }

    private function reachableNodeIds(?string $startNodeId, $edges)
    {
        $visited = collect();
        if (!$startNodeId) {
            return $visited;
        }

        $queue = collect([$startNodeId]);
        while ($queue->isNotEmpty()) {
            $current = $queue->shift();
            if ($visited->contains($current)) {
                continue;
            }
            $visited->push($current);
            $edges->filter(fn ($edge) => (($edge['source_id'] ?? $edge['source'] ?? null) === $current))
                ->map(fn ($edge) => $edge['target_id'] ?? $edge['target'] ?? null)
                ->filter()
                ->each(fn ($target) => $queue->push($target));
        }

        return $visited;
    }

    private function hasCircularPath(?string $startNodeId, $edges): bool
    {
        if (!$startNodeId) {
            return false;
        }

        $adjacency = [];

        foreach ($edges as $edge) {
            $sourceId = (string) ($edge['source_id'] ?? $edge['source'] ?? '');
            $targetId = (string) ($edge['target_id'] ?? $edge['target'] ?? '');

            if ($sourceId === '' || $targetId === '') {
                continue;
            }

            $adjacency[$sourceId][] = $targetId;
        }

        $visited = [];
        $visiting = [];

        return $this->detectCycleFromNode($startNodeId, $adjacency, $visited, $visiting);
    }

    private function detectCycleFromNode(string $nodeId, array $adjacency, array &$visited, array &$visiting): bool
    {
        if (isset($visiting[$nodeId])) {
            return true;
        }

        if (isset($visited[$nodeId])) {
            return false;
        }

        $visiting[$nodeId] = true;

        foreach ($adjacency[$nodeId] ?? [] as $targetId) {
            if ($this->detectCycleFromNode((string) $targetId, $adjacency, $visited, $visiting)) {
                return true;
            }
        }

        unset($visiting[$nodeId]);
        $visited[$nodeId] = true;

        return false;
    }

    private function buttonIds(array $config): array
    {
        return collect(Arr::get($config, 'buttons', []))
            ->pluck('id')
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    private function listRowIds(array $config): array
    {
        return collect(Arr::get($config, 'sections', []))
            ->flatMap(fn ($section) => Arr::get($section, 'rows', []))
            ->pluck('id')
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    private function hasDuplicateValues(array $values): bool
    {
        return count($values) !== count(array_unique($values));
    }

    private function fieldExists(int $organizationId, string $fieldUuid): bool
    {
        return ContactField::where('organization_id', $organizationId)
            ->where('uuid', $fieldUuid)
            ->exists();
    }

    private function groupExists(int $organizationId, string $groupUuid): bool
    {
        return ContactGroup::where('organization_id', $organizationId)
            ->where('uuid', $groupUuid)
            ->whereNull('deleted_at')
            ->exists();
    }

    private function assetExists(?AutomationFlow $flow, string $assetUuid): bool
    {
        if (!$flow || $assetUuid === '' || !Schema::hasTable('automation_flow_assets')) {
            return false;
        }

        return AutomationFlowAsset::where('automation_flow_id', $flow->id)
            ->where('uuid', $assetUuid)
            ->exists();
    }

    private function hasEmailSecret(?AutomationFlow $flow, string $nodeId, string $secretRef, array $nodeSecrets = []): bool
    {
        if (Arr::has($nodeSecrets, $nodeId)) {
            return $this->nodeSecrets->emailPayloadIsComplete(Arr::get($nodeSecrets, $nodeId, []));
        }

        if (!$flow || !Schema::hasTable('automation_flow_node_secrets')) {
            return false;
        }

        if ($secretRef !== '') {
            $secret = $flow->nodeSecrets()
                ->where('uuid', $secretRef)
                ->first();

            if ($secret) {
                return $this->nodeSecrets->emailPayloadIsComplete($secret->payload_json ?? []);
            }
        }

        return $this->nodeSecrets->emailPayloadIsComplete(
            $this->nodeSecrets->payloadForNode($flow, $nodeId)
        );
    }

    private function validateAssignmentConfig(int $organizationId, array $config, bool $isHandoff): array
    {
        $errors = [];
        $capabilities = $this->conversationHandoff->capabilitiesForOrganization($organizationId);

        if (!($capabilities['ticketing_active'] ?? false)) {
            $errors[] = $isHandoff
                ? __('Activate ticketing before handing the conversation to customer service.')
                : __('Activate ticketing before assigning the conversation to a service agent.');

            return $errors;
        }

        $assignmentMode = (string) Arr::get($config, 'assignment_mode', 'auto_assign');

        if (!in_array($assignmentMode, ['auto_assign', 'specific_agent', 'unassigned'], true)) {
            $errors[] = __('Choose how this step should assign the conversation.');
        }

        if ($assignmentMode === 'specific_agent') {
            $agentUserId = (int) Arr::get($config, 'agent_user_id', 0);
            $agentIds = collect($capabilities['assignable_agents'] ?? [])
                ->pluck('user_id')
                ->map(fn ($value) => (int) $value)
                ->all();

            if ($agentUserId < 1 || !in_array($agentUserId, $agentIds, true)) {
                $errors[] = __('Choose a valid service agent for this step.');
            }
        }

        if ($assignmentMode === 'auto_assign' && empty($capabilities['assignable_agents'])) {
            $errors[] = __('Add at least one team member before using automatic assignment here.');
        }

        return $errors;
    }
}
