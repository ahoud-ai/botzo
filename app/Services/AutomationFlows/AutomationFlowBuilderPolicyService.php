<?php

namespace App\Services\AutomationFlows;

use Illuminate\Support\Arr;

class AutomationFlowBuilderPolicyService
{
    public function __construct(
        private readonly AutomationFlowNodeCatalog $catalog,
    ) {
    }

    public function resolvedPolicy(): array
    {
        $policy = config('automation_flows.builder_policy', []);
        $whatsappOnlyMode = (bool) Arr::get($policy, 'whatsapp_only_mode', false);

        return [
            'channel' => (string) Arr::get($policy, 'channel', 'whatsapp'),
            'whatsapp_only_mode' => $whatsappOnlyMode,
            'allow_external_actions' => $whatsappOnlyMode
                ? false
                : (bool) Arr::get($policy, 'allow_external_actions', true),
            'allow_crm_actions' => (bool) Arr::get($policy, 'allow_crm_actions', true),
        ];
    }

    public function allowsNodeType(string $type): bool
    {
        $policy = $this->resolvedPolicy();

        if (!($policy['allow_external_actions'] ?? true) && in_array($type, $this->catalog->externalActionTypes(), true)) {
            return false;
        }

        if (!($policy['allow_crm_actions'] ?? true) && in_array($type, $this->catalog->crmActionTypes(), true)) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    public function blockedNodeTypes(array $graph): array
    {
        return collect(Arr::get($graph, 'nodes', []))
            ->map(fn ($node) => (string) Arr::get($node, 'type', ''))
            ->filter(fn (string $type) => $type !== '' && !$this->allowsNodeType($type))
            ->unique()
            ->values()
            ->all();
    }
}

