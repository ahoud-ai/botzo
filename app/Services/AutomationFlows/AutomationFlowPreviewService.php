<?php

namespace App\Services\AutomationFlows;

use App\Models\AutomationFlow;
use App\Models\ContactField;
use App\Models\ContactGroup;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class AutomationFlowPreviewService
{
    public function __construct(
        private readonly AutomationFlowGraphCompiler $compiler,
        private readonly AutomationFlowAssetService $assetService,
    ) {
    }

    public function project(AutomationFlow $flow, array $graph, ?string $focusNodeId = null): array
    {
        $compiled = $this->compiler->compile($graph);
        $currentNodeId = $compiled['start_node_id'];
        $timeline = [];
        $visited = [];

        while ($currentNodeId && isset($compiled['nodes'][$currentNodeId]) && !in_array($currentNodeId, $visited, true)) {
            $visited[] = $currentNodeId;
            $node = $compiled['nodes'][$currentNodeId];
            $config = Arr::get($node, 'config', []);
            $timeline = array_merge($timeline, $this->previewItemsForNode($flow, $node, $config));

            if (in_array($node['type'], ['human_handoff', 'handoff_to_ai_assistant'], true)) {
                break;
            }

            $nextBranch = 'default';
            if ($node['type'] === 'send_buttons') {
                $firstButton = collect(Arr::get($config, 'buttons', []))->first();
                if ($firstButton) {
                    $timeline[] = [
                        'kind' => 'user',
                        'node_id' => $node['id'],
                        'label' => Arr::get($firstButton, 'title'),
                        'meta' => ['branch' => Arr::get($firstButton, 'id')],
                    ];
                    $nextBranch = Arr::get($firstButton, 'id', 'default');
                }
            } elseif ($node['type'] === 'send_list') {
                $firstRow = collect(Arr::get($config, 'sections', []))
                    ->flatMap(fn ($section) => Arr::get($section, 'rows', []))
                    ->first();
                if ($firstRow) {
                    $timeline[] = [
                        'kind' => 'user',
                        'node_id' => $node['id'],
                        'label' => Arr::get($firstRow, 'title'),
                        'meta' => ['branch' => Arr::get($firstRow, 'id')],
                    ];
                    $nextBranch = Arr::get($firstRow, 'id', 'default');
                }
            } elseif ($node['type'] === 'save_reply_to_field') {
                $timeline[] = [
                    'kind' => 'user',
                    'node_id' => $node['id'],
                    'label' => __('Example: I want to know the prices'),
                    'meta' => ['branch' => 'default'],
                ];
            } elseif ($node['type'] === 'condition') {
                $nextBranch = 'matched';
            }

            $currentNodeId = $this->nextNodeId($compiled['adjacency'][$currentNodeId] ?? [], $nextBranch);
        }

        return [
            'scenario' => config('automation_flows.preview_default_scenario', 'main'),
            'focus_node_id' => $focusNodeId,
            'steps' => $this->focusedTimeline($timeline, $focusNodeId),
        ];
    }

    private function previewItemsForNode(AutomationFlow $flow, array $node, array $config): array
    {
        return match ($node['type']) {
            'trigger' => [[
                'kind' => 'system',
                'node_id' => $node['id'],
                'label' => __('Flow starts when a matching WhatsApp message arrives.'),
            ]],
            'send_text' => [[
                'kind' => 'assistant',
                'node_id' => $node['id'],
                'label' => (string) Arr::get($config, 'text', ''),
            ]],
            'send_media' => [[
                'kind' => 'assistant',
                'node_id' => $node['id'],
                'label' => (string) Arr::get($config, 'caption', __('Media message')),
                'meta' => [
                    'media_type' => Arr::get($config, 'media_type'),
                    'media_url' => $this->mediaUrl($flow, (string) Arr::get($config, 'asset_id', '')),
                    'media_name' => $this->mediaName($flow, (string) Arr::get($config, 'asset_id', '')),
                ],
            ]],
            'send_buttons' => [[
                'kind' => 'assistant',
                'node_id' => $node['id'],
                'label' => (string) Arr::get($config, 'body', ''),
                'meta' => [
                    'header' => Arr::get($config, 'header'),
                    'footer' => Arr::get($config, 'footer'),
                    'buttons' => Arr::get($config, 'buttons', []),
                ],
            ]],
            'send_list' => [[
                'kind' => 'assistant',
                'node_id' => $node['id'],
                'label' => (string) Arr::get($config, 'body', ''),
                'meta' => [
                    'header' => Arr::get($config, 'header'),
                    'footer' => Arr::get($config, 'footer'),
                    'button_label' => Arr::get($config, 'button_label'),
                    'sections' => Arr::get($config, 'sections', []),
                ],
            ]],
            'save_reply_to_field' => [[
                'kind' => 'system',
                'node_id' => $node['id'],
                'label' => $this->saveReplyLabel($flow, $config),
            ]],
            'condition' => [[
                'kind' => 'system',
                'node_id' => $node['id'],
                'label' => $this->conditionLabel($config),
            ]],
            'add_to_group' => [[
                'kind' => 'system',
                'node_id' => $node['id'],
                'label' => __('Add the contact to group: :group', ['group' => $this->groupLabel($flow, (string) Arr::get($config, 'group_uuid', ''))]),
            ]],
            'remove_from_group' => [[
                'kind' => 'system',
                'node_id' => $node['id'],
                'label' => __('Remove the contact from group: :group', ['group' => $this->groupLabel($flow, (string) Arr::get($config, 'group_uuid', ''))]),
            ]],
            'update_contact_field' => [[
                'kind' => 'system',
                'node_id' => $node['id'],
                'label' => $this->updateFieldLabel($flow, $config),
            ]],
            'assign_to_agent' => [[
                'kind' => 'system',
                'node_id' => $node['id'],
                'label' => __('Open or reuse the service ticket and assign the conversation to :target.', [
                    'target' => $this->assignmentLabel($config),
                ]),
            ]],
            'human_handoff' => [[
                'kind' => 'system',
                'node_id' => $node['id'],
                'label' => __('Pause automation and hand the conversation to customer service via :target.', [
                    'target' => $this->assignmentLabel($config),
                ]),
            ]],
            'handoff_to_ai_assistant' => [[
                'kind' => 'system',
                'node_id' => $node['id'],
                'label' => __('Pause the flow and let the built-in AI assistant take over.'),
            ]],
            'send_email' => [[
                'kind' => 'system',
                'node_id' => $node['id'],
                'label' => __('Send an email with subject: :subject', ['subject' => (string) Arr::get($config, 'subject', __('Configured email'))]),
            ]],
            'delay' => [[
                'kind' => 'system',
                'node_id' => $node['id'],
                'label' => __('Wait :minutes minute(s) before the next step continues.', ['minutes' => max(1, (int) Arr::get($config, 'minutes', 1))]),
            ]],
            'end' => [[
                'kind' => 'system',
                'node_id' => $node['id'],
                'label' => __('End the journey.'),
            ]],
            default => [],
        };
    }

    private function mediaUrl(AutomationFlow $flow, string $assetId): ?string
    {
        return $this->assetMeta($flow, $assetId)['url'] ?? null;
    }

    private function mediaName(AutomationFlow $flow, string $assetId): ?string
    {
        return $this->assetMeta($flow, $assetId)['original_name'] ?? null;
    }

    private function assetMeta(AutomationFlow $flow, string $assetId): array
    {
        if ($assetId === '' || !Schema::hasTable('automation_flow_assets')) {
            return [];
        }

        if (!$flow->relationLoaded('assets')) {
            $flow->loadMissing('assets');
        }

        $asset = $flow->assets->firstWhere('uuid', $assetId);

        return $asset ? $this->assetService->toBuilderArray($flow, $asset) : [];
    }

    private function nextNodeId(array $edges, string $preferredBranch): ?string
    {
        $collection = collect($edges);

        return $collection->firstWhere('branch', $preferredBranch)['target_id']
            ?? $collection->firstWhere('branch', 'default')['target_id']
            ?? $collection->first()['target_id']
            ?? null;
    }

    private function focusedTimeline(array $timeline, ?string $focusNodeId): array
    {
        if ($focusNodeId === null || $focusNodeId === '') {
            return $timeline;
        }

        $focusIndexes = collect($timeline)
            ->keys()
            ->filter(fn (int $index) => ($timeline[$index]['node_id'] ?? null) === $focusNodeId)
            ->values();

        if ($focusIndexes->isEmpty()) {
            return $timeline;
        }

        $start = $focusIndexes->first();
        $end = $focusIndexes->last();

        if (($timeline[$start]['kind'] ?? null) === 'system') {
            for ($index = $start - 1; $index >= 0; $index--) {
                if (in_array($timeline[$index]['kind'] ?? null, ['assistant', 'user'], true)) {
                    $start = $index;
                    break;
                }
            }
        }

        return array_values(array_slice($timeline, $start, ($end - $start) + 1));
    }

    private function saveReplyLabel(AutomationFlow $flow, array $config): string
    {
        if ((string) Arr::get($config, 'save_target', 'contact_field') === 'session_variable') {
            return __('Wait for a free text answer and store it in flow variable: :name', [
                'name' => $this->quotedLabel($this->variableLabel((string) Arr::get($config, 'variable_key', ''))),
            ]);
        }

        return __('Wait for a free text answer and save it to contact field: :field', [
            'field' => $this->quotedLabel($this->fieldLabel($flow, (string) Arr::get($config, 'field_uuid', ''))),
        ]);
    }

    private function conditionLabel(array $config): string
    {
        $source = (string) Arr::get($config, 'source', 'last_user_message');

        if ($source === 'flow_variable') {
            return __('Check flow variable :name and route the customer to the matching branch.', [
                'name' => $this->quotedLabel($this->variableLabel((string) Arr::get($config, 'variable_key', ''))),
            ]);
        }

        if ($source === 'contact_field') {
            return __('Check the selected contact field and route the customer to the matching branch.');
        }

        return __('Check a rule and route the customer to the matching branch.');
    }

    private function updateFieldLabel(AutomationFlow $flow, array $config): string
    {
        $saveTarget = (string) Arr::get($config, 'save_target', 'contact_field');
        $mode = (string) Arr::get($config, 'mode', Arr::get($config, 'value_mode', 'save_last_user_message'));

        if ($saveTarget === 'session_variable') {
            $target = $this->quotedLabel($this->variableLabel((string) Arr::get($config, 'variable_key', '')));

            if ($mode === 'static') {
                return __('Save :value into flow variable :target.', [
                    'value' => $this->quotedLabel((string) Arr::get($config, 'value', __('static value'))),
                    'target' => $target,
                ]);
            }

            if ($mode === 'session_variable') {
                return __('Copy flow variable :source into flow variable :target.', [
                    'source' => $this->quotedLabel($this->variableLabel((string) Arr::get($config, 'source_variable_key', ''))),
                    'target' => $target,
                ]);
            }

            return __('Save the latest customer reply into flow variable :target.', [
                'target' => $target,
            ]);
        }

        $field = $this->quotedLabel($this->fieldLabel($flow, (string) Arr::get($config, 'field_uuid', '')));

        if ($mode === 'static') {
            return __('Save :value into contact field :field.', [
                'value' => $this->quotedLabel((string) Arr::get($config, 'value', __('static value'))),
                'field' => $field,
            ]);
        }

        if ($mode === 'session_variable') {
            return __('Copy flow variable :source into contact field :field.', [
                'source' => $this->quotedLabel($this->variableLabel((string) Arr::get($config, 'source_variable_key', ''))),
                'field' => $field,
            ]);
        }

        return __('Save the latest customer reply into contact field :field.', [
            'field' => $field,
        ]);
    }

    private function assignmentLabel(array $config): string
    {
        $mode = (string) Arr::get($config, 'assignment_mode', 'auto_assign');

        return match ($mode) {
            'specific_agent' => __('the selected service agent'),
            'unassigned' => __('an open ticket without a fixed owner'),
            default => __('the least busy service agent'),
        };
    }

    private function fieldLabel(AutomationFlow $flow, string $fieldUuid): string
    {
        $fieldUuid = trim($fieldUuid);

        if ($fieldUuid === '') {
            return (string) __('selected field');
        }

        $field = ContactField::query()
            ->where('organization_id', $flow->organization_id)
            ->where('uuid', $fieldUuid)
            ->first(['name']);

        return $field?->name ?: (string) __('selected field');
    }

    private function groupLabel(AutomationFlow $flow, string $groupUuid): string
    {
        $groupUuid = trim($groupUuid);

        if ($groupUuid === '') {
            return (string) __('selected group');
        }

        $group = ContactGroup::query()
            ->where('organization_id', $flow->organization_id)
            ->where('uuid', $groupUuid)
            ->first(['name']);

        return $group?->name ?: (string) __('selected group');
    }

    private function variableLabel(string $value): string
    {
        $normalized = trim($value);

        return $normalized !== '' ? $normalized : (string) __('flow variable');
    }

    private function quotedLabel(string $value): string
    {
        $trimmed = trim($value);

        return $trimmed !== '' ? "\"{$trimmed}\"" : "\"-\"";
    }
}
