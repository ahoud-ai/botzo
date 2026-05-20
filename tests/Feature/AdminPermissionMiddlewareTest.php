<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminPermissionMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    public function test_non_super_admin_is_forbidden_without_matching_permission(): void
    {
        $role = Role::create(['name' => 'Settings Viewer '.Str::random(5)]);
        $staff = $this->createAdminUser($role->name);

        $response = $this->actingAs($staff, 'admin')
            ->get('/admin/settings/smtp');

        $response->assertForbidden();
    }

    public function test_non_super_admin_can_access_route_with_matching_permission(): void
    {
        $role = Role::create(['name' => 'Settings SMTP '.Str::random(5)]);

        RolePermission::create([
            'role_id' => $role->id,
            'module' => 'settings',
            'action' => 'smtp',
        ]);

        $staff = $this->createAdminUser($role->name);

        $response = $this->actingAs($staff, 'admin')
            ->get('/admin/settings/smtp');

        $response->assertOk();
    }

    public function test_dashboard_requires_customers_view_permission_for_non_super_admin(): void
    {
        $role = Role::create(['name' => 'Support Staff '.Str::random(5)]);
        $staff = $this->createAdminUser($role->name);

        $response = $this->actingAs($staff, 'admin')
            ->get('/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_dashboard_is_accessible_when_customers_view_permission_exists(): void
    {
        $role = Role::create(['name' => 'Support Manager '.Str::random(5)]);

        RolePermission::create([
            'role_id' => $role->id,
            'module' => 'customers',
            'action' => 'view',
        ]);

        $staff = $this->createAdminUser($role->name);

        $response = $this->actingAs($staff, 'admin')
            ->get('/admin/dashboard');

        $response->assertOk();
    }

    public function test_settings_auto_permission_allows_matching_type(): void
    {
        $role = Role::create(['name' => 'Frontend Ops '.Str::random(5)]);

        RolePermission::create([
            'role_id' => $role->id,
            'module' => 'settings',
            'action' => 'frontend',
        ]);

        $staff = $this->createAdminUser($role->name);

        $response = $this->actingAs($staff, 'admin')
            ->put('/admin/settings?type=frontend-seo', [
                'seo_site_name_en' => 'Botzo',
            ]);

        $response->assertStatus(302);
    }

    public function test_settings_auto_permission_blocks_when_type_permission_missing(): void
    {
        $role = Role::create(['name' => 'General Ops '.Str::random(5)]);

        RolePermission::create([
            'role_id' => $role->id,
            'module' => 'settings',
            'action' => 'general',
        ]);

        $staff = $this->createAdminUser($role->name);

        $response = $this->actingAs($staff, 'admin')
            ->put('/admin/settings?type=frontend-seo', [
                'seo_site_name_en' => 'Botzo',
            ]);

        $response->assertForbidden();
    }

    public function test_admin_permissions_payload_is_shared_for_non_super_admin(): void
    {
        $role = Role::create(['name' => 'SMTP Admin '.Str::random(5)]);

        RolePermission::create([
            'role_id' => $role->id,
            'module' => 'settings',
            'action' => 'smtp',
        ]);

        $staff = $this->createAdminUser($role->name);

        $response = $this->actingAs($staff, 'admin')
            ->get('/admin/settings/smtp');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('adminPermissions.enabled', true)
            ->where('adminPermissions.isSuperAdmin', false)
            ->where('adminPermissions.moduleActions.settings.0', 'smtp')
        );
    }

    public function test_inertia_403_returns_error_component_for_unauthorized_admin_request(): void
    {
        $role = Role::create(['name' => 'Restricted Ops '.Str::random(5)]);
        $staff = $this->createAdminUser($role->name);
        $inertiaVersion = app(\App\Http\Middleware\HandleInertiaRequests::class)
            ->version(\Illuminate\Http\Request::create('/admin/settings/smtp', 'GET'));

        $response = $this->actingAs($staff, 'admin')
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-Inertia-Version', (string) $inertiaVersion)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get('/admin/settings/smtp');

        $response->assertStatus(403);
        $response->assertHeader('X-Inertia', 'true');
        $response->assertJsonPath('component', 'Error');
        $response->assertJsonPath('props.status', 403);
    }

    public function test_logs_route_accepts_customer_view_permission(): void
    {
        $role = Role::create(['name' => 'Customer Ops '.Str::random(5)]);

        RolePermission::create([
            'role_id' => $role->id,
            'module' => 'customers',
            'action' => 'view',
        ]);

        $staff = $this->createAdminUser($role->name);

        $response = $this->actingAs($staff, 'admin')
            ->get('/admin/user-logs/emails');

        $response->assertOk();
    }

    public function test_admin_permissions_payload_exposes_companion_modules(): void
    {
        $role = Role::create(['name' => 'Companion Alias '.Str::random(5)]);

        RolePermission::create([
            'role_id' => $role->id,
            'module' => 'settings',
            'action' => 'general',
        ]);

        RolePermission::create([
            'role_id' => $role->id,
            'module' => 'customers',
            'action' => 'view',
        ]);

        $staff = $this->createAdminUser($role->name);

        $response = $this->actingAs($staff, 'admin')
            ->get('/admin/settings/general');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('adminPermissions.moduleActions.languages', ['create', 'delete', 'edit', 'view'])
            ->where('adminPermissions.moduleActions.logs', ['view'])
        );
    }

    private function createAdminUser(string $role): User
    {
        return User::create([
            'first_name' => 'Admin',
            'last_name' => 'RBAC',
            'email' => 'admin+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => $role,
            'email_verified_at' => now(),
        ]);
    }
}
