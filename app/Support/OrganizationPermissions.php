<?php

namespace App\Support;

class OrganizationPermissions
{
    private const AVAILABLE = [
        'contacts' => [
            'view_all' => 'View All',
            'view_assigned_only' => 'View Assigned Only',
            'create' => 'Create',
            'edit' => 'Edit',
            'delete' => 'Delete',
            'import' => 'Import',
            'export' => 'Export',
        ],
        'chats' => [
            'view_all' => 'View All',
            'view_assigned_only' => 'View Assigned Only',
            'reply' => 'Reply',
            'assign' => 'Assign',
            'delete' => 'Delete',
            'change_status' => 'Change Status',
        ],
        'campaigns' => [
            'view_all' => 'View All',
            'add' => 'Add',
            'view' => 'View',
            'delete' => 'Delete',
        ],
        'message_templates' => [
            'view_all' => 'View All',
            'add' => 'Add',
            'edit' => 'Edit',
            'delete' => 'Delete',
            'sync' => 'Sync',
        ],
        'automations' => [
            'view_all' => 'View All',
            'add' => 'Add',
            'edit' => 'Edit',
            'delete' => 'Delete',
        ],
        'automations.flows' => [
            'view' => 'View',
            'add' => 'Add',
            'edit' => 'Edit',
            'publish' => 'Publish',
            'delete' => 'Delete',
        ],
        'settings' => [
            'manage' => 'Manage',
            'billing_subscription' => 'Billing & Subscription',
        ],
        'developer_tools' => [
            'view' => 'View',
            'add' => 'Add',
            'edit' => 'Edit',
            'delete' => 'Delete',
        ],
    ];

    private const PREVIOUS_EXPANSIONS = [
        'contacts.view_unassigned' => ['contacts.view_all'],
        'contacts.view_assigned' => ['contacts.view_all', 'contacts.view_assigned_only'],
        'chats.view_assigned' => ['chats.view_all', 'chats.view_assigned_only'],
        'tickets.view_all' => ['chats.view_all'],
        'tickets.view_assigned' => ['chats.view_all', 'chats.view_assigned_only'],
        'tickets.assign' => ['chats.assign'],
        'tickets.close' => ['chats.change_status'],
        'chats.send_message' => ['chats.reply'],
        'reports.view' => [],
        'automations.view_all' => ['automations.view_all', 'automations.flows.view'],
        'automations.add' => ['automations.add', 'automations.flows.add'],
        'automations.edit' => ['automations.edit', 'automations.flows.edit', 'automations.flows.publish'],
        'automations.delete' => ['automations.delete', 'automations.flows.delete'],
    ];

    private const DEPENDENCIES = [
        'contacts.view_assigned_only' => ['contacts.view_all', 'contacts.view_assigned_only'],
        'chats.view_assigned_only' => ['chats.view_all', 'chats.view_assigned_only'],
        'chats.reply' => ['chats.view_all', 'chats.reply'],
        'automations.flows.edit' => ['automations.flows.view', 'automations.flows.edit'],
        'automations.flows.publish' => ['automations.flows.view', 'automations.flows.edit', 'automations.flows.publish'],
    ];

    public static function availablePermissions(): array
    {
        return self::AVAILABLE;
    }

    public static function normalizePermissions(?array $permissions): array
    {
        $normalized = [];

        foreach ($permissions ?? [] as $permission) {
            foreach (self::requiredPermissionsFor((string) $permission) as $requiredPermission) {
                if ($requiredPermission === '*') {
                    return ['*'];
                }

                if (self::isValid($requiredPermission)) {
                    $normalized[$requiredPermission] = $requiredPermission;
                }
            }
        }

        return array_values($normalized);
    }

    public static function requiredPermissionsFor(string $permission): array
    {
        $permission = trim($permission);

        if ($permission === '') {
            return [];
        }

        if ($permission === '*') {
            return ['*'];
        }

        if (isset(self::PREVIOUS_EXPANSIONS[$permission])) {
            return self::PREVIOUS_EXPANSIONS[$permission];
        }

        if (isset(self::DEPENDENCIES[$permission])) {
            return self::DEPENDENCIES[$permission];
        }

        return self::isValid($permission) ? [$permission] : [];
    }

    public static function isValid(string $permission): bool
    {
        return in_array($permission, self::flatPermissions(), true);
    }

    private static function flatPermissions(): array
    {
        static $permissions = null;

        if ($permissions !== null) {
            return $permissions;
        }

        $permissions = [];

        foreach (self::AVAILABLE as $module => $actions) {
            foreach (array_keys($actions) as $action) {
                $permissions[] = "{$module}.{$action}";
            }
        }

        return $permissions;
    }
}
