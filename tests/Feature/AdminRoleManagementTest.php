<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminRoleManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_system_admin_role_cannot_be_updated(): void
    {
        $systemRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['uuid' => Str::uuid()]
        );

        $response = $this->actingAs($this->createAdminUser('admin'), 'admin')
            ->from('/admin/team/roles/'.$systemRole->uuid)
            ->put('/admin/team/roles/'.$systemRole->uuid, [
                'name' => 'admin',
                'permissions' => [],
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['role']);
    }

    public function test_system_admin_role_cannot_be_deleted(): void
    {
        $systemRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['uuid' => Str::uuid()]
        );

        $response = $this->actingAs($this->createAdminUser('admin'), 'admin')
            ->from('/admin/team/roles')
            ->delete('/admin/team/roles/'.$systemRole->uuid);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['role']);
        $this->assertDatabaseHas('roles', ['id' => $systemRole->id, 'name' => 'admin']);
    }

    public function test_role_with_members_cannot_be_deleted(): void
    {
        $role = Role::create([
            'uuid' => Str::uuid(),
            'name' => 'Role With Members '.Str::random(5),
        ]);

        $this->createAdminUser($role->name);

        $response = $this->actingAs($this->createAdminUser('admin'), 'admin')
            ->from('/admin/team/roles')
            ->delete('/admin/team/roles/'.$role->uuid);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['role']);
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => $role->name]);
    }

    public function test_role_without_members_can_be_deleted(): void
    {
        $role = Role::create([
            'uuid' => Str::uuid(),
            'name' => 'Role Empty '.Str::random(5),
        ]);

        $response = $this->actingAs($this->createAdminUser('admin'), 'admin')
            ->delete('/admin/team/roles/'.$role->uuid);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_admin_roles_index_exposes_correct_counts_and_flags(): void
    {
        $role = Role::create([
            'uuid' => Str::uuid(),
            'name' => 'Analytics Staff '.Str::random(5),
        ]);

        RolePermission::create([
            'role_id' => $role->id,
            'module' => 'customers',
            'action' => 'view',
        ]);

        RolePermission::create([
            'role_id' => $role->id,
            'module' => 'support',
            'action' => 'assign',
        ]);

        $this->createAdminUser($role->name);
        $this->createAdminUser($role->name);
        $deleted = $this->createAdminUser($role->name);
        $deleted->delete();

        $response = $this->actingAs($this->createAdminUser('admin'), 'admin')
            ->get('/admin/team/roles');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Role/Index')
            ->where('rows.data', function ($rows) use ($role) {
                $target = collect($rows)->firstWhere('uuid', $role->uuid);

                return $target !== null
                    && (int) $target['permissions_count'] === 2
                    && (int) $target['members_count'] === 2
                    && $target['is_system_role'] === false
                    && $target['can_edit'] === true
                    && $target['can_delete'] === false;
            })
        );
    }

    private function createAdminUser(string $role): User
    {
        return User::create([
            'first_name' => 'Admin',
            'last_name' => 'Ops',
            'email' => 'role-admin+'.Str::random(8).'@example.com',
            'password' => 'password123',
            'role' => $role,
            'email_verified_at' => now(),
        ]);
    }
}
