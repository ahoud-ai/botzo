<?php

namespace App\Services;

use App\Services\AutomationFlows\AutomationFlowAccessService;

class AutomationResponseSequenceService
{
    public function __construct(
        private readonly AddonStateService $addonState,
        private readonly AutomationFlowAccessService $automationFlowAccess,
    ) {
    }

    public function sanitize(array $responseSequence, int $organizationId): array
    {
        $isAiAssistantEnabled = $this->addonState
            ->isModuleEnabledForOrganization('AI Assistant', $organizationId);
        $isFlowBuilderEnabled = $this->automationFlowAccess
            ->availableForOrganization($organizationId);

        $allowed = ['Basic Replies'];
        if ($isFlowBuilderEnabled) {
            $allowed[] = 'Automation Flows';
        }
        if ($isAiAssistantEnabled) {
            $allowed[] = 'AI Reply Assistant';
        }

        $cleaned = collect($responseSequence)
            ->filter(fn ($item) => in_array($item, $allowed, true))
            ->values()
            ->all();

        if ($isFlowBuilderEnabled && !in_array('Automation Flows', $cleaned, true)) {
            array_unshift($cleaned, 'Automation Flows');
        }

        if (!in_array('Basic Replies', $cleaned, true)) {
            $flowIndex = array_search('Automation Flows', $cleaned, true);
            if ($flowIndex === false) {
                array_unshift($cleaned, 'Basic Replies');
            } else {
                array_splice($cleaned, $flowIndex + 1, 0, ['Basic Replies']);
            }
        }

        if ($isAiAssistantEnabled && !in_array('AI Reply Assistant', $cleaned, true)) {
            $cleaned[] = 'AI Reply Assistant';
        }

        return array_values(array_unique($cleaned));
    }
}
