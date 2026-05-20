<?php

namespace App\Services\AutomationFlows;

use App\Services\SubscriptionPlanLimitService;

class AutomationFlowStarterTemplateService
{
    private const GOAL_PRESETS = [
        'sales_qualification',
        'support_routing',
        'appointment_booking',
        'seller_intake',
    ];

    public function __construct(
        private readonly AutomationFlowBuilderPolicyService $builderPolicy,
        private readonly AutomationFlowNodeCatalog $catalog,
        private readonly SubscriptionPlanLimitService $planLimitService,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public static function supportedGoalPresets(): array
    {
        return self::GOAL_PRESETS;
    }

    public function normalizeGoalPreset(?string $goalPreset): string
    {
        $normalized = trim((string) $goalPreset);

        if (in_array($normalized, self::GOAL_PRESETS, true)) {
            return $normalized;
        }

        $fallback = trim((string) config('automation_flows.default_goal_preset', 'sales_qualification'));

        return in_array($fallback, self::GOAL_PRESETS, true)
            ? $fallback
            : 'sales_qualification';
    }

    public function starterGraphForOrganization(string $goalPreset, int $organizationId): array
    {
        $goal = $this->normalizeGoalPreset($goalPreset);
        $supports = $this->capabilitiesForOrganization($organizationId);

        if (!$supports['advanced_enabled']) {
            return $this->simpleStarterGraph($goal);
        }

        return match ($goal) {
            'sales_qualification' => $supports['send_buttons']
                ? $this->salesQualificationGraph()
                : $this->simpleStarterGraph($goal),
            'support_routing' => $supports['send_list']
                ? $this->supportRoutingGraph()
                : ($supports['send_buttons']
                    ? $this->supportRoutingButtonsGraph()
                    : $this->simpleStarterGraph($goal)),
            'appointment_booking' => $supports['save_reply_to_field']
                ? $this->appointmentBookingGraph()
                : ($supports['send_buttons']
                    ? $this->appointmentBookingButtonsGraph()
                    : $this->simpleStarterGraph($goal)),
            'seller_intake' => ($supports['save_reply_to_field'] && $supports['condition'])
                ? $this->sellerIntakeGraph()
                : ($supports['send_buttons']
                    ? $this->sellerIntakeButtonsGraph()
                    : $this->simpleStarterGraph($goal)),
            default => $this->simpleStarterGraph($goal),
        };
    }

    public function preferredActiveNodeId(array $graph): string
    {
        foreach (($graph['nodes'] ?? []) as $node) {
            if (($node['type'] ?? null) !== 'trigger' && filled($node['id'] ?? null)) {
                return (string) $node['id'];
            }
        }

        return (string) ($graph['start_node_id'] ?? 'trigger-1');
    }

    /**
     * @return array<string, bool>
     */
    private function capabilitiesForOrganization(int $organizationId): array
    {
        $advancedEnabled = $this->planLimitService->boolForOrganization(
            $organizationId,
            'flow_builder_advanced_enabled',
            true
        );

        return [
            'advanced_enabled' => $advancedEnabled,
            'send_buttons' => $this->supportsNodeType('send_buttons', $advancedEnabled),
            'send_list' => $this->supportsNodeType('send_list', $advancedEnabled),
            'save_reply_to_field' => $this->supportsNodeType('save_reply_to_field', $advancedEnabled),
            'condition' => $this->supportsNodeType('condition', $advancedEnabled),
        ];
    }

    private function supportsNodeType(string $type, bool $advancedEnabled): bool
    {
        if (!$this->builderPolicy->allowsNodeType($type)) {
            return false;
        }

        if (in_array($type, $this->catalog->advancedTypes(), true) && !$advancedEnabled) {
            return false;
        }

        return true;
    }

    private function simpleStarterGraph(string $goalPreset): array
    {
        $message = match ($goalPreset) {
            'support_routing' => __('Welcome to support. Tell us what you need help with and we will route the next step.'),
            'appointment_booking' => __('Welcome. Share the appointment goal and preferred time so your team can follow up quickly.'),
            'seller_intake' => __('Welcome. Tell us about the property you want to list and the team will review the next step.'),
            default => __('Welcome. Tell us what you need and we will guide the next step.'),
        };

        return [
            'start_node_id' => 'trigger-1',
            'nodes' => [
                $this->node('trigger-1', 'trigger', 80, 120, [
                    'match_mode' => 'any_incoming',
                    'keywords' => [],
                ], true),
                $this->node('send-text-1', 'send_text', 420, 120, [
                    'text' => $message,
                ]),
                $this->node('end-1', 'end', 760, 120),
            ],
            'edges' => [
                $this->edge('edge-trigger-send', 'trigger-1', 'send-text-1'),
                $this->edge('edge-send-end', 'send-text-1', 'end-1'),
            ],
        ];
    }

    private function salesQualificationGraph(): array
    {
        return [
            'start_node_id' => 'trigger-1',
            'nodes' => [
                $this->node('trigger-1', 'trigger', 80, 120, [
                    'match_mode' => 'any_incoming',
                    'keywords' => [],
                ], true),
                $this->node('sales-path-1', 'send_buttons', 420, 120, [
                    'body' => __('What would you like to do next?'),
                    'buttons' => [
                        ['id' => 'pricing', 'title' => __('See pricing')],
                        ['id' => 'demo', 'title' => __('Book demo')],
                        ['id' => 'sales', 'title' => __('Talk to sales')],
                    ],
                    'invalid_reply_behavior' => 'release_to_fallback',
                ]),
                $this->node('send-pricing-1', 'send_text', 820, 0, [
                    'text' => __('Great. Start by sharing the package or team size you want to price.'),
                ]),
                $this->node('send-demo-1', 'send_text', 820, 140, [
                    'text' => __('Perfect. Share your preferred day and time and the team can confirm the demo.'),
                ]),
                $this->node('send-sales-1', 'send_text', 820, 280, [
                    'text' => __('Understood. Add one short sentence about your use case so sales can reply with context.'),
                ]),
                $this->node('end-1', 'end', 1180, 140),
            ],
            'edges' => [
                $this->edge('edge-trigger-buttons', 'trigger-1', 'sales-path-1'),
                $this->edge('edge-buttons-pricing', 'sales-path-1', 'send-pricing-1', 'pricing'),
                $this->edge('edge-buttons-demo', 'sales-path-1', 'send-demo-1', 'demo'),
                $this->edge('edge-buttons-sales', 'sales-path-1', 'send-sales-1', 'sales'),
                $this->edge('edge-pricing-end', 'send-pricing-1', 'end-1'),
                $this->edge('edge-demo-end', 'send-demo-1', 'end-1'),
                $this->edge('edge-sales-end', 'send-sales-1', 'end-1'),
            ],
        ];
    }

    private function supportRoutingGraph(): array
    {
        return [
            'start_node_id' => 'trigger-1',
            'nodes' => [
                $this->node('trigger-1', 'trigger', 80, 200, [
                    'match_mode' => 'any_incoming',
                    'keywords' => [],
                ], true),
                $this->node('support-list-1', 'send_list', 420, 200, [
                    'body' => __('Choose the area you need help with so the next reply stays relevant.'),
                    'button_label' => __('Pick support topic'),
                    'sections' => [
                        [
                            'title' => __('Support topics'),
                            'rows' => [
                                ['id' => 'billing', 'title' => __('Billing'), 'description' => __('Invoices, renewals, or payments')],
                                ['id' => 'technical', 'title' => __('Technical'), 'description' => __('Setup, bugs, or integrations')],
                                ['id' => 'usage', 'title' => __('Usage help'), 'description' => __('How to use the product better')],
                            ],
                        ],
                    ],
                    'invalid_reply_behavior' => 'release_to_fallback',
                ]),
                $this->node('send-billing-1', 'send_text', 840, 40, [
                    'text' => __('Thanks. Start by sharing the invoice, plan, or payment question and the team can continue from there.'),
                ]),
                $this->node('send-technical-1', 'send_text', 840, 200, [
                    'text' => __('Thanks. Share the feature, error, or integration issue and the next reply can stay technical and focused.'),
                ]),
                $this->node('send-usage-1', 'send_text', 840, 360, [
                    'text' => __('Thanks. Tell us what you are trying to achieve and the next reply can guide the safest workflow.'),
                ]),
                $this->node('end-1', 'end', 1220, 200),
            ],
            'edges' => [
                $this->edge('edge-trigger-list', 'trigger-1', 'support-list-1'),
                $this->edge('edge-list-billing', 'support-list-1', 'send-billing-1', 'billing'),
                $this->edge('edge-list-technical', 'support-list-1', 'send-technical-1', 'technical'),
                $this->edge('edge-list-usage', 'support-list-1', 'send-usage-1', 'usage'),
                $this->edge('edge-billing-end', 'send-billing-1', 'end-1'),
                $this->edge('edge-technical-end', 'send-technical-1', 'end-1'),
                $this->edge('edge-usage-end', 'send-usage-1', 'end-1'),
            ],
        ];
    }

    private function supportRoutingButtonsGraph(): array
    {
        return [
            'start_node_id' => 'trigger-1',
            'nodes' => [
                $this->node('trigger-1', 'trigger', 80, 120, [
                    'match_mode' => 'any_incoming',
                    'keywords' => [],
                ], true),
                $this->node('support-buttons-1', 'send_buttons', 420, 120, [
                    'body' => __('Choose the support area you need help with.'),
                    'buttons' => [
                        ['id' => 'billing', 'title' => 'Billing'],
                        ['id' => 'technical', 'title' => __('Technical')],
                        ['id' => 'usage', 'title' => __('Usage help')],
                    ],
                    'invalid_reply_behavior' => 'release_to_fallback',
                ]),
                $this->node('send-billing-1', 'send_text', 820, 0, [
                    'text' => __('Share the invoice, renewal, or payment detail and the next reply can stay billing-focused.'),
                ]),
                $this->node('send-technical-1', 'send_text', 820, 140, [
                    'text' => __('Share the feature or issue and the next reply can stay technical and specific.'),
                ]),
                $this->node('send-usage-1', 'send_text', 820, 280, [
                    'text' => __('Share the workflow you want to improve and the next reply can guide the right path.'),
                ]),
                $this->node('end-1', 'end', 1180, 140),
            ],
            'edges' => [
                $this->edge('edge-trigger-buttons', 'trigger-1', 'support-buttons-1'),
                $this->edge('edge-billing', 'support-buttons-1', 'send-billing-1', 'billing'),
                $this->edge('edge-technical', 'support-buttons-1', 'send-technical-1', 'technical'),
                $this->edge('edge-usage', 'support-buttons-1', 'send-usage-1', 'usage'),
                $this->edge('edge-billing-end', 'send-billing-1', 'end-1'),
                $this->edge('edge-technical-end', 'send-technical-1', 'end-1'),
                $this->edge('edge-usage-end', 'send-usage-1', 'end-1'),
            ],
        ];
    }

    private function appointmentBookingGraph(): array
    {
        return [
            'start_node_id' => 'trigger-1',
            'nodes' => [
                $this->node('trigger-1', 'trigger', 80, 120, [
                    'match_mode' => 'any_incoming',
                    'keywords' => [],
                ], true),
                $this->node('send-text-1', 'send_text', 420, 120, [
                    'text' => __('Share the preferred day or time window for the appointment, and the team can continue from there.'),
                ]),
                $this->node('save-reply-1', 'save_reply_to_field', 760, 120, [
                    'save_target' => 'session_variable',
                    'field_uuid' => '',
                    'variable_key' => 'preferred_appointment_slot',
                ]),
                $this->node('send-text-2', 'send_text', 1100, 120, [
                    'text' => __('Thanks. The preferred appointment slot is now captured so the next follow-up can confirm availability.'),
                ]),
                $this->node('end-1', 'end', 1440, 120),
            ],
            'edges' => [
                $this->edge('edge-trigger-intro', 'trigger-1', 'send-text-1'),
                $this->edge('edge-intro-save', 'send-text-1', 'save-reply-1'),
                $this->edge('edge-save-confirm', 'save-reply-1', 'send-text-2'),
                $this->edge('edge-confirm-end', 'send-text-2', 'end-1'),
            ],
        ];
    }

    private function appointmentBookingButtonsGraph(): array
    {
        return [
            'start_node_id' => 'trigger-1',
            'nodes' => [
                $this->node('trigger-1', 'trigger', 80, 120, [
                    'match_mode' => 'any_incoming',
                    'keywords' => [],
                ], true),
                $this->node('slot-buttons-1', 'send_buttons', 420, 120, [
                    'body' => __('Choose the appointment window that feels closest to the customer need.'),
                    'buttons' => [
                        ['id' => 'morning', 'title' => __('Morning')],
                        ['id' => 'afternoon', 'title' => __('Afternoon')],
                        ['id' => 'evening', 'title' => __('Evening')],
                    ],
                    'invalid_reply_behavior' => 'release_to_fallback',
                ]),
                $this->node('send-morning-1', 'send_text', 820, 0, [
                    'text' => __('Great. The next reply can confirm a morning appointment option.'),
                ]),
                $this->node('send-afternoon-1', 'send_text', 820, 140, [
                    'text' => __('Great. The next reply can confirm an afternoon appointment option.'),
                ]),
                $this->node('send-evening-1', 'send_text', 820, 280, [
                    'text' => __('Great. The next reply can confirm an evening appointment option.'),
                ]),
                $this->node('end-1', 'end', 1180, 140),
            ],
            'edges' => [
                $this->edge('edge-trigger-buttons', 'trigger-1', 'slot-buttons-1'),
                $this->edge('edge-morning', 'slot-buttons-1', 'send-morning-1', 'morning'),
                $this->edge('edge-afternoon', 'slot-buttons-1', 'send-afternoon-1', 'afternoon'),
                $this->edge('edge-evening', 'slot-buttons-1', 'send-evening-1', 'evening'),
                $this->edge('edge-morning-end', 'send-morning-1', 'end-1'),
                $this->edge('edge-afternoon-end', 'send-afternoon-1', 'end-1'),
                $this->edge('edge-evening-end', 'send-evening-1', 'end-1'),
            ],
        ];
    }

    private function sellerIntakeGraph(): array
    {
        return [
            'start_node_id' => 'trigger-1',
            'nodes' => [
                $this->node('trigger-1', 'trigger', 80, 120, [
                    'match_mode' => 'any_incoming',
                    'keywords' => [],
                ], true),
                $this->node('send-text-1', 'send_text', 420, 120, [
                    'text' => __('Tell us what property you want to list or mention the property type in one short reply.'),
                ]),
                $this->node('save-reply-1', 'save_reply_to_field', 760, 120, [
                    'save_target' => 'session_variable',
                    'field_uuid' => '',
                    'variable_key' => 'seller_property_type',
                ]),
                $this->node('condition-1', 'condition', 1100, 120, [
                    'source' => 'flow_variable',
                    'variable_key' => 'seller_property_type',
                    'operator' => 'contains',
                    'value' => 'apartment',
                ]),
                $this->node('send-apartment-1', 'send_text', 1460, 20, [
                    'text' => __('Perfect. The next step can continue with apartment-specific questions and pricing context.'),
                ]),
                $this->node('send-general-1', 'send_text', 1460, 220, [
                    'text' => __('Perfect. The next step can continue with the right listing questions for this property type.'),
                ]),
                $this->node('end-1', 'end', 1820, 120),
            ],
            'edges' => [
                $this->edge('edge-trigger-intro', 'trigger-1', 'send-text-1'),
                $this->edge('edge-intro-save', 'send-text-1', 'save-reply-1'),
                $this->edge('edge-save-condition', 'save-reply-1', 'condition-1'),
                $this->edge('edge-condition-matched', 'condition-1', 'send-apartment-1', 'matched'),
                $this->edge('edge-condition-unmatched', 'condition-1', 'send-general-1', 'unmatched'),
                $this->edge('edge-apartment-end', 'send-apartment-1', 'end-1'),
                $this->edge('edge-general-end', 'send-general-1', 'end-1'),
            ],
        ];
    }

    private function sellerIntakeButtonsGraph(): array
    {
        return [
            'start_node_id' => 'trigger-1',
            'nodes' => [
                $this->node('trigger-1', 'trigger', 80, 120, [
                    'match_mode' => 'any_incoming',
                    'keywords' => [],
                ], true),
                $this->node('seller-buttons-1', 'send_buttons', 420, 120, [
                    'body' => __('What type of property is the customer most likely listing?'),
                    'buttons' => [
                        ['id' => 'apartment', 'title' => __('Apartment')],
                        ['id' => 'villa', 'title' => __('Villa')],
                        ['id' => 'other', 'title' => __('Other')],
                    ],
                    'invalid_reply_behavior' => 'release_to_fallback',
                ]),
                $this->node('send-apartment-1', 'send_text', 820, 0, [
                    'text' => __('Great. The next reply can continue with apartment-specific listing questions.'),
                ]),
                $this->node('send-villa-1', 'send_text', 820, 140, [
                    'text' => __('Great. The next reply can continue with villa-specific listing questions.'),
                ]),
                $this->node('send-other-1', 'send_text', 820, 280, [
                    'text' => __('Great. The next reply can continue with the general listing path.'),
                ]),
                $this->node('end-1', 'end', 1180, 140),
            ],
            'edges' => [
                $this->edge('edge-trigger-buttons', 'trigger-1', 'seller-buttons-1'),
                $this->edge('edge-apartment', 'seller-buttons-1', 'send-apartment-1', 'apartment'),
                $this->edge('edge-villa', 'seller-buttons-1', 'send-villa-1', 'villa'),
                $this->edge('edge-other', 'seller-buttons-1', 'send-other-1', 'other'),
                $this->edge('edge-apartment-end', 'send-apartment-1', 'end-1'),
                $this->edge('edge-villa-end', 'send-villa-1', 'end-1'),
                $this->edge('edge-other-end', 'send-other-1', 'end-1'),
            ],
        ];
    }

    private function node(
        string $id,
        string $type,
        int $x,
        int $y,
        array $config = [],
        bool $expanded = false,
    ): array {
        return [
            'id' => $id,
            'type' => $type,
            'position' => ['x' => $x, 'y' => $y],
            'config' => $config,
            'ui' => ['expanded' => $expanded],
        ];
    }

    private function edge(
        string $id,
        string $sourceId,
        string $targetId,
        string $branch = 'default',
    ): array {
        return [
            'id' => $id,
            'source_id' => $sourceId,
            'target_id' => $targetId,
            'branch' => $branch,
        ];
    }
}
