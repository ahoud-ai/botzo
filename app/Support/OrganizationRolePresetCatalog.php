<?php

namespace App\Support;

class OrganizationRolePresetCatalog
{
    public const SEED_VERSION = 1;

    public static function presets(): array
    {
        return [
            [
                'name' => 'Executive Management',
                'description' => 'Executive leadership with broad operational oversight across the workspace.',
                'permissions' => [
                    'contacts.view_all',
                    'contacts.create',
                    'contacts.edit',
                    'contacts.delete',
                    'contacts.import',
                    'contacts.export',
                    'chats.view_all',
                    'chats.reply',
                    'chats.assign',
                    'chats.delete',
                    'chats.change_status',
                    'campaigns.view_all',
                    'campaigns.add',
                    'campaigns.view',
                    'campaigns.delete',
                    'message_templates.view_all',
                    'message_templates.add',
                    'message_templates.edit',
                    'message_templates.delete',
                    'message_templates.sync',
                    'automations.view_all',
                    'automations.add',
                    'automations.edit',
                    'automations.delete',
                    'automations.flows.view',
                    'automations.flows.add',
                    'automations.flows.edit',
                    'automations.flows.publish',
                    'automations.flows.delete',
                    'settings.manage',
                ],
            ],
            [
                'name' => 'Operations Manager',
                'description' => 'Day-to-day operational manager for workspace settings, campaigns, and service workflows.',
                'permissions' => [
                    'contacts.view_all',
                    'contacts.create',
                    'contacts.edit',
                    'contacts.import',
                    'contacts.export',
                    'chats.view_all',
                    'chats.reply',
                    'chats.assign',
                    'chats.change_status',
                    'campaigns.view_all',
                    'campaigns.add',
                    'campaigns.view',
                    'campaigns.delete',
                    'message_templates.view_all',
                    'message_templates.add',
                    'message_templates.edit',
                    'message_templates.sync',
                    'automations.view_all',
                    'automations.add',
                    'automations.edit',
                    'automations.flows.view',
                    'automations.flows.add',
                    'automations.flows.edit',
                    'automations.flows.publish',
                    'settings.manage',
                ],
            ],
            [
                'name' => 'Customer Support Supervisor',
                'description' => 'Supervises support queues, assignment quality, and customer-service response performance.',
                'permissions' => [
                    'contacts.view_all',
                    'contacts.create',
                    'contacts.edit',
                    'contacts.export',
                    'chats.view_all',
                    'chats.reply',
                    'chats.assign',
                    'chats.change_status',
                    'message_templates.view_all',
                    'message_templates.sync',
                ],
            ],
            [
                'name' => 'Reply Employee',
                'description' => 'Handles assigned conversations and updates contact details during customer follow-up.',
                'permissions' => [
                    'contacts.view_assigned_only',
                    'contacts.edit',
                    'chats.view_assigned_only',
                    'chats.reply',
                    'chats.change_status',
                    'message_templates.view_all',
                ],
            ],
            [
                'name' => 'Marketing Specialist',
                'description' => 'Prepares campaigns, manages templates, and runs marketing automation tasks.',
                'permissions' => [
                    'contacts.view_all',
                    'contacts.import',
                    'contacts.export',
                    'campaigns.view_all',
                    'campaigns.add',
                    'campaigns.view',
                    'campaigns.delete',
                    'message_templates.view_all',
                    'message_templates.add',
                    'message_templates.edit',
                    'message_templates.delete',
                    'message_templates.sync',
                    'automations.view_all',
                    'automations.add',
                    'automations.edit',
                    'automations.flows.view',
                    'automations.flows.add',
                    'automations.flows.edit',
                    'automations.flows.publish',
                ],
            ],
            [
                'name' => 'Developer Integrations',
                'description' => 'Manages API keys and technical integration surfaces.',
                'permissions' => [
                    'developer_tools.view',
                    'developer_tools.add',
                    'developer_tools.edit',
                    'developer_tools.delete',
                ],
            ],
        ];
    }

    public static function names(): array
    {
        return array_map(
            static fn (array $preset): string => (string) $preset['name'],
            self::presets()
        );
    }
}
