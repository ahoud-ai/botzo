<?php

namespace App\Services;

use App\Models\Addon;

class FeatureSetupPolicyService
{
    public function getPolicy(Addon $addon): array
    {
        if ($addon->name === 'AI Assistant') {
            return [
                'setup_supported' => true,
                'coming_soon' => false,
                'setup_defaults' => [
                    'status' => 1,
                    'is_active' => 0,
                    'is_plan_restricted' => 1,
                    'metadata' => $this->aiAssistantMetadataDefaults(),
                ],
                'reason' => null,
            ];
        }

        if ($addon->name === 'Embedded Signup') {
            return [
                'setup_supported' => true,
                'coming_soon' => false,
                'setup_defaults' => [
                    'status' => 1,
                    'is_active' => 0,
                    'is_plan_restricted' => 1,
                    'metadata' => $this->embeddedSignupMetadataDefaults(),
                ],
                'reason' => null,
            ];
        }

        if ($addon->name === 'Flow builder') {
            return [
                'setup_supported' => true,
                'coming_soon' => false,
                'setup_defaults' => [
                    'status' => 1,
                    'is_active' => 0,
                    'is_plan_restricted' => 1,
                    'metadata' => $this->flowBuilderMetadataDefaults(),
                ],
                'reason' => null,
            ];
        }

        return [
            'setup_supported' => false,
            'coming_soon' => ((int) $addon->status) === 0,
            'setup_defaults' => [],
            'status_type' => 'warning',
            'reason' => __('This feature is not available yet and is scheduled for development soon.'),
        ];
    }

    private function embeddedSignupMetadataDefaults(): array
    {
        return [
            'name' => 'EmbeddedSignup',
        ];
    }

    private function aiAssistantMetadataDefaults(): array
    {
        return [
            'name' => 'IntelliReply',
            'input_fields' => [
                [
                    'element' => 'input',
                    'type' => 'password',
                    'name' => 'ai_global_api_key',
                    'label' => __('Global OpenAI API Key'),
                    'class' => 'col-span-2',
                ],
                [
                    'element' => 'select',
                    'type' => 'text',
                    'name' => 'ai_key_policy',
                    'label' => __('AI key policy'),
                    'class' => 'col-span-2',
                    'options' => [
                        ['value' => 'hybrid', 'label' => 'Hybrid'],
                        ['value' => 'global_only', 'label' => __('Global only')],
                        ['value' => 'organization_only', 'label' => __('Organization only')],
                    ],
                ],
                [
                    'element' => 'toggle',
                    'type' => 'checkbox',
                    'name' => 'ai_allow_org_override',
                    'label' => __('Allow organization API key override'),
                    'class' => 'col-span-2',
                ],
            ],
        ];
    }

    private function flowBuilderMetadataDefaults(): array
    {
        return [
            'name' => 'FlowBuilder',
        ];
    }
}
