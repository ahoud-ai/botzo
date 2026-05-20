<?php

namespace Tests\Feature;

use App\Models\OrganizationRole;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackfillOrganizationRolePermissionsMigrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_migration_normalizes_previous_permissions_to_canonical_names(): void
    {
        $role = OrganizationRole::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => null,
            'name' => 'Previous Role '.Str::random(5),
            'permissions' => [
                'contacts.view_assigned',
                'chats.view_assigned',
                'tickets.assign',
                'tickets.close',
            ],
        ]);

        $migration = require base_path('database/migrations/2026_03_12_090200_normalize_organization_role_permissions.php');
        $migration->up();

        $role->refresh();

        $this->assertEqualsCanonicalizing([
            'contacts.view_all',
            'contacts.view_assigned_only',
            'chats.view_all',
            'chats.view_assigned_only',
            'chats.assign',
            'chats.change_status',
        ], $role->permissions);
    }

    public function test_chat_reply_backfill_grants_reply_to_previous_send_roles(): void
    {
        $role = OrganizationRole::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => null,
            'name' => 'Previous Send Role '.Str::random(5),
            'permissions' => [
                'chats.view_all',
                'chats.send_message',
            ],
        ]);

        $migration = require base_path('database/migrations/2026_05_01_121500_backfill_chat_reply_permission.php');
        $migration->up();

        $role->refresh();

        $this->assertContains('chats.view_all', $role->permissions);
        $this->assertContains('chats.reply', $role->permissions);
        $this->assertNotContains('chats.send_message', $role->permissions);
    }
}
