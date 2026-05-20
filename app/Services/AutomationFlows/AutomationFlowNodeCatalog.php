<?php

namespace App\Services\AutomationFlows;

class AutomationFlowNodeCatalog
{
    private const EXTERNAL_ACTION_TYPES = [
        'send_email',
    ];

    private const SERVICE_ACTION_TYPES = [
        'assign_to_agent',
        'human_handoff',
        'handoff_to_ai_assistant',
    ];

    private const CRM_ACTION_TYPES = [
        'save_reply_to_field',
        'add_to_group',
        'remove_from_group',
        'update_contact_field',
    ];

    private const ADVANCED_TYPES = [
        'send_media',
        'send_buttons',
        'send_list',
        'save_reply_to_field',
        'condition',
        'add_to_group',
        'remove_from_group',
        'update_contact_field',
        'send_email',
        'assign_to_agent',
        'human_handoff',
        'handoff_to_ai_assistant',
        'delay',
    ];

    private const DEFINITIONS = [
        'trigger' => [
            'category' => 'trigger',
            'label' => 'Trigger',
            'description' => 'Start when a WhatsApp message matches the selected rule.',
            'icon' => 'workflow',
            'builder_scope' => 'flow_logic',
            'whatsapp_native' => false,
            'persists_contact_data' => false,
            'requires_external_setup' => false,
            'demo_safe' => true,
        ],
        'send_text' => [
            'category' => 'messages',
            'label' => 'Simple text',
            'description' => 'Send a short text reply to the customer.',
            'icon' => 'message-circle-more',
            'builder_scope' => 'whatsapp_message',
            'whatsapp_native' => true,
            'persists_contact_data' => false,
            'requires_external_setup' => false,
            'demo_safe' => true,
        ],
        'send_media' => [
            'category' => 'messages',
            'label' => 'Media files',
            'description' => 'Send an image, audio, video, or document message.',
            'icon' => 'paperclip',
            'builder_scope' => 'whatsapp_message',
            'whatsapp_native' => true,
            'persists_contact_data' => false,
            'requires_external_setup' => false,
            'demo_safe' => true,
        ],
        'send_buttons' => [
            'category' => 'messages',
            'label' => 'Interactive buttons',
            'description' => 'Send quick reply buttons and branch by the customer choice.',
            'icon' => 'mouse-pointer-click',
            'builder_scope' => 'whatsapp_message',
            'whatsapp_native' => true,
            'persists_contact_data' => false,
            'requires_external_setup' => false,
            'demo_safe' => true,
        ],
        'send_list' => [
            'category' => 'messages',
            'label' => 'Interactive list',
            'description' => 'Send a WhatsApp list and continue based on the selected row.',
            'icon' => 'list',
            'builder_scope' => 'whatsapp_message',
            'whatsapp_native' => true,
            'persists_contact_data' => false,
            'requires_external_setup' => false,
            'demo_safe' => true,
        ],
        'save_reply_to_field' => [
            'category' => 'actions',
            'label' => 'Save reply',
            'description' => 'Store the next customer answer in a flow variable or contact field.',
            'icon' => 'database-zap',
            'builder_scope' => 'crm_action',
            'whatsapp_native' => false,
            'persists_contact_data' => true,
            'requires_external_setup' => false,
            'demo_safe' => false,
        ],
        'condition' => [
            'category' => 'actions',
            'label' => 'Condition',
            'description' => 'Split the journey according to a rule.',
            'icon' => 'git-fork',
            'builder_scope' => 'flow_logic',
            'whatsapp_native' => false,
            'persists_contact_data' => false,
            'requires_external_setup' => false,
            'demo_safe' => true,
        ],
        'add_to_group' => [
            'category' => 'actions',
            'label' => 'Add to Group',
            'description' => 'Add the contact to a selected group.',
            'icon' => 'user-plus',
            'builder_scope' => 'crm_action',
            'whatsapp_native' => false,
            'persists_contact_data' => true,
            'requires_external_setup' => false,
            'demo_safe' => false,
        ],
        'remove_from_group' => [
            'category' => 'actions',
            'label' => 'Remove from Group',
            'description' => 'Remove the contact from a selected group.',
            'icon' => 'user-minus',
            'builder_scope' => 'crm_action',
            'whatsapp_native' => false,
            'persists_contact_data' => true,
            'requires_external_setup' => false,
            'demo_safe' => false,
        ],
        'update_contact_field' => [
            'category' => 'actions',
            'label' => 'Update Contact',
            'description' => 'Update a contact field or flow variable from the current flow context.',
            'icon' => 'user-pen',
            'builder_scope' => 'crm_action',
            'whatsapp_native' => false,
            'persists_contact_data' => true,
            'requires_external_setup' => false,
            'demo_safe' => false,
        ],
        'assign_to_agent' => [
            'category' => 'actions',
            'label' => 'Assign to Agent',
            'description' => 'Open the customer ticket and assign it to a service agent.',
            'icon' => 'user-check',
            'builder_scope' => 'service_action',
            'whatsapp_native' => false,
            'persists_contact_data' => false,
            'requires_external_setup' => false,
            'demo_safe' => false,
        ],
        'human_handoff' => [
            'category' => 'actions',
            'label' => 'Human handoff',
            'description' => 'Pause automation and move the conversation to customer service.',
            'icon' => 'headset',
            'builder_scope' => 'service_action',
            'whatsapp_native' => false,
            'persists_contact_data' => false,
            'requires_external_setup' => false,
            'demo_safe' => false,
        ],
        'handoff_to_ai_assistant' => [
            'category' => 'actions',
            'label' => 'AI assistant handoff',
            'description' => 'Pause the flow and let the built-in AI assistant take the next replies.',
            'icon' => 'bot',
            'builder_scope' => 'service_action',
            'whatsapp_native' => false,
            'persists_contact_data' => false,
            'requires_external_setup' => false,
            'demo_safe' => false,
        ],
        'send_email' => [
            'category' => 'actions',
            'label' => 'Send Email',
            'description' => 'Send an email by using SMTP settings saved for this step.',
            'icon' => 'mail',
            'builder_scope' => 'external_action',
            'whatsapp_native' => false,
            'persists_contact_data' => false,
            'requires_external_setup' => true,
            'demo_safe' => false,
        ],
        'delay' => [
            'category' => 'actions',
            'label' => 'Delay',
            'description' => 'Pause the automation for a number of minutes before continuing.',
            'icon' => 'clock-3',
            'builder_scope' => 'flow_logic',
            'whatsapp_native' => false,
            'persists_contact_data' => false,
            'requires_external_setup' => false,
            'demo_safe' => true,
        ],
        'end' => [
            'category' => 'actions',
            'label' => 'End',
            'description' => 'Finish the customer journey.',
            'icon' => 'circle-stop',
            'builder_scope' => 'flow_logic',
            'whatsapp_native' => false,
            'persists_contact_data' => false,
            'requires_external_setup' => false,
            'demo_safe' => true,
        ],
    ];

    public const TYPES = [
        'trigger',
        'send_text',
        'send_media',
        'send_buttons',
        'send_list',
        'save_reply_to_field',
        'condition',
        'add_to_group',
        'remove_from_group',
        'update_contact_field',
        'assign_to_agent',
        'human_handoff',
        'handoff_to_ai_assistant',
        'send_email',
        'delay',
        'end',
    ];

    public function all(array $policy = []): array
    {
        $filtered = collect(self::DEFINITIONS)
            ->except(['trigger', 'end'])
            ->reject(function (array $definition, string $type) use ($policy): bool {
                if (($policy['allow_external_actions'] ?? true) === false && in_array($type, self::EXTERNAL_ACTION_TYPES, true)) {
                    return true;
                }

                if (($policy['allow_crm_actions'] ?? true) === false && in_array($type, self::CRM_ACTION_TYPES, true)) {
                    return true;
                }

                return false;
            });

        return $filtered
            ->map(function (array $definition, string $type): array {
                return [
                    'type' => $type,
                    'category' => $definition['category'],
                    'label' => __($definition['label']),
                    'description' => __($definition['description']),
                    'icon' => $definition['icon'],
                    'builder_scope' => $definition['builder_scope'] ?? 'flow_logic',
                    'whatsapp_native' => (bool) ($definition['whatsapp_native'] ?? false),
                    'persists_contact_data' => (bool) ($definition['persists_contact_data'] ?? false),
                    'requires_external_setup' => (bool) ($definition['requires_external_setup'] ?? false),
                    'demo_safe' => (bool) ($definition['demo_safe'] ?? false),
                ];
            })
            ->values()
            ->all();
    }

    public function isValid(string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }

    public function definition(string $type): array
    {
        return self::DEFINITIONS[$type] ?? [
            'category' => 'actions',
            'label' => $type,
            'description' => $type,
            'icon' => 'circle',
        ];
    }

    public function advancedTypes(): array
    {
        return self::ADVANCED_TYPES;
    }

    public function externalActionTypes(): array
    {
        return self::EXTERNAL_ACTION_TYPES;
    }

    public function serviceActionTypes(): array
    {
        return self::SERVICE_ACTION_TYPES;
    }

    public function crmActionTypes(): array
    {
        return self::CRM_ACTION_TYPES;
    }
}
