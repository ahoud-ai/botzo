<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationEmployee;
use App\Models\OrganizationEmployeeAssignment;
use App\Models\OrganizationRole;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminUserDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_users_index_uses_the_new_directory_page_and_exposes_rich_row_data(): void
    {
        $staff = $this->createAdminWithCustomersViewPermission();
        $targetUser = $this->createCustomerUser('directory+'.Str::random(6).'@example.com');
        $organization = $this->createOrganization($targetUser);
        $ownerRole = $this->ownerRole();

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $targetUser->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $targetUser->id,
        ]);

        OrganizationEmployee::create([
            'main_organization_id' => $organization->id,
            'user_id' => $targetUser->id,
            'email' => strtolower((string) $targetUser->email),
            'first_name' => $targetUser->first_name,
            'last_name' => $targetUser->last_name,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        $response = $this->actingAs($staff, 'admin')
            ->get('/admin/users?search='.urlencode($targetUser->email));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/User/Directory')
            ->where('rows.data.0.email', $targetUser->email)
            ->where('rows.data.0.user_type', 'main_owner')
            ->where('rows.data.0.companies_count', 1)
        );
    }

    public function test_company_employee_filter_excludes_main_owners_even_when_previous_sync_created_employee_records(): void
    {
        $staff = $this->createAdminWithCustomersViewPermission();
        $ownerUser = $this->createCustomerUser('owner-filter+'.Str::random(6).'@example.com');
        $employeeUser = $this->createCustomerUser('employee-filter+'.Str::random(6).'@example.com');
        $organization = $this->createOrganization($ownerUser);
        $ownerRole = $this->ownerRole();

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $ownerUser->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $ownerUser->id,
        ]);

        OrganizationEmployee::create([
            'main_organization_id' => $organization->id,
            'user_id' => $ownerUser->id,
            'email' => strtolower((string) $ownerUser->email),
            'first_name' => $ownerUser->first_name,
            'last_name' => $ownerUser->last_name,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        OrganizationEmployee::create([
            'main_organization_id' => $organization->id,
            'user_id' => $employeeUser->id,
            'email' => strtolower((string) $employeeUser->email),
            'first_name' => $employeeUser->first_name,
            'last_name' => $employeeUser->last_name,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        $response = $this->actingAs($staff, 'admin')
            ->get('/admin/users?access_scope=company_employee');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/User/Directory')
            ->has('rows.data', 1)
            ->where('rows.data.0.email', $employeeUser->email)
            ->where('rows.data.0.user_type', 'company_employee')
            ->where('summary.company_members', 1)
        );
    }

    public function test_admin_user_show_uses_the_new_details_page_and_exposes_membership_counts(): void
    {
        $staff = $this->createAdminWithCustomersViewPermission();
        $targetUser = $this->createCustomerUser('details+'.Str::random(6).'@example.com');
        $organization = $this->createOrganization($targetUser);
        $this->createActiveSubscription($organization, 'Growth');
        $ownerRole = $this->ownerRole();

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $targetUser->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $targetUser->id,
        ]);

        $employee = OrganizationEmployee::create([
            'main_organization_id' => $organization->id,
            'user_id' => $targetUser->id,
            'email' => strtolower((string) $targetUser->email),
            'first_name' => $targetUser->first_name,
            'last_name' => $targetUser->last_name,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        OrganizationEmployeeAssignment::create([
            'organization_employee_id' => $employee->id,
            'organization_id' => $organization->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'assigned_by' => $targetUser->id,
            'assigned_at' => now(),
            'activated_at' => now(),
        ]);

        $response = $this->actingAs($staff, 'admin')
            ->get('/admin/users/'.$targetUser->id);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/User/ShowDetails')
            ->where('user.id', $targetUser->id)
            ->where('user.type.primary', 'main_owner')
            ->where('user.actions.can_delete_account', false)
            ->where('user.counts.companies', 1)
            ->where('user.counts.workspaces', 1)
            ->has('user.companies', 1)
            ->where('user.companies.0.subscription.status_key', 'active')
            ->where('user.companies.0.subscription.plan_name', 'Growth')
            ->where('user.companies.0.assignments.0.role_permissions.is_full_access', true)
            ->where('user.direct_workspaces.0.access_source', 'company_assignment')
        );
    }

    public function test_admin_can_suspend_and_restore_platform_account_without_removing_memberships(): void
    {
        $staff = $this->createAdminWithCustomersViewPermission();
        $targetUser = $this->createCustomerUser('suspend+'.Str::random(6).'@example.com');
        $organization = $this->createOrganization($targetUser);
        $ownerRole = $this->ownerRole();

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $targetUser->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $targetUser->id,
        ]);

        OrganizationEmployee::create([
            'main_organization_id' => $organization->id,
            'user_id' => $targetUser->id,
            'email' => strtolower((string) $targetUser->email),
            'first_name' => $targetUser->first_name,
            'last_name' => $targetUser->last_name,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        $this->actingAs($staff, 'admin')
            ->patch('/admin/users/'.$targetUser->id.'/suspend')
            ->assertRedirect();

        $targetUser->refresh();

        $this->assertSame(0, (int) $targetUser->status);
        $this->assertDatabaseHas('teams', [
            'user_id' => $targetUser->id,
            'organization_id' => $organization->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('organization_employees', [
            'user_id' => $targetUser->id,
            'main_organization_id' => $organization->id,
            'deleted_at' => null,
        ]);

        $this->actingAs($staff, 'admin')
            ->patch('/admin/users/'.$targetUser->id.'/restore')
            ->assertRedirect();

        $targetUser->refresh();

        $this->assertSame(1, (int) $targetUser->status);
    }

    public function test_admin_cannot_delete_a_main_organization_owner_platform_account(): void
    {
        $staff = $this->createAdminWithCustomersViewPermission();
        $targetUser = $this->createCustomerUser('owner-delete+'.Str::random(6).'@example.com');
        $organization = $this->createOrganization($targetUser);
        $ownerRole = $this->ownerRole();

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $targetUser->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $targetUser->id,
        ]);

        $this->actingAs($staff, 'admin')
            ->delete('/admin/users/'.$targetUser->id)
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_user_update_redirects_back_to_the_details_edit_tab(): void
    {
        $staff = $this->createAdminWithCustomersViewPermission();
        $targetUser = $this->createCustomerUser('edit-tab+'.Str::random(6).'@example.com');

        $response = $this->actingAs($staff, 'admin')
            ->put('/admin/users/'.$targetUser->id.'?tab=edit', [
                'first_name' => 'Edited',
                'last_name' => 'Account',
                'email' => $targetUser->email,
                'phone' => null,
            ]);

        $response->assertRedirect('/admin/users/'.$targetUser->id.'?tab=edit');

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'first_name' => 'Edited',
        ]);
    }

    public function test_admin_user_update_accepts_form_data_method_spoofing_from_edit_form(): void
    {
        $staff = $this->createAdminWithCustomersViewPermission();
        $targetUser = $this->createCustomerUser('edit-form+'.Str::random(6).'@example.com');
        $updatedEmail = 'edited-form+'.Str::random(6).'@example.com';

        $response = $this->actingAs($staff, 'admin')
            ->post('/admin/users/'.$targetUser->id.'?tab=edit', [
                '_method' => 'put',
                'first_name' => 'Edited',
                'last_name' => 'Form',
                'email' => $updatedEmail,
                'phone' => null,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $response->assertRedirect('/admin/users/'.$targetUser->id.'?tab=edit');

        $targetUser->refresh();

        $this->assertSame('Edited', $targetUser->first_name);
        $this->assertSame('Form', $targetUser->last_name);
        $this->assertSame($updatedEmail, $targetUser->email);
        $this->assertTrue(Hash::check('new-password-123', $targetUser->password));
    }

    public function test_admin_user_sensitive_update_changes_identity_fields(): void
    {
        $staff = $this->createAdminWithCustomersViewPermission();
        $targetUser = $this->createCustomerUser('identity-update+'.Str::random(6).'@example.com');
        $updatedEmail = 'identity-updated+'.Str::random(6).'@example.com';

        $response = $this->actingAs($staff, 'admin')
            ->post('/admin/users/'.$targetUser->id.'?tab=edit', [
                '_method' => 'put',
                'first_name' => 'Edited',
                'last_name' => 'Sensitive',
                'email' => $updatedEmail,
                'phone' => null,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $response->assertRedirect('/admin/users/'.$targetUser->id.'?tab=edit');

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'email' => $updatedEmail,
        ]);
    }

    public function test_customer_user_routes_do_not_manage_admin_staff_accounts(): void
    {
        $staff = $this->createAdminWithCustomersViewPermission();
        $otherAdmin = User::create([
            'first_name' => 'Other',
            'last_name' => 'Admin',
            'email' => 'other-admin+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 1,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($staff, 'admin')
            ->patch('/admin/users/'.$otherAdmin->id.'/suspend')
            ->assertNotFound();

        $this->actingAs($staff, 'admin')
            ->delete('/admin/users/'.$otherAdmin->id)
            ->assertNotFound();

        $this->assertDatabaseHas('users', [
            'id' => $otherAdmin->id,
            'status' => 1,
            'deleted_at' => null,
        ]);
    }

    private function createAdminWithCustomersViewPermission(): User
    {
        return User::create([
            'first_name' => 'Admin',
            'last_name' => 'Ops',
            'email' => 'admin+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    private function createCustomerUser(string $email): User
    {
        return User::create([
            'first_name' => 'Customer',
            'last_name' => 'Account',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'user',
            'status' => 1,
            'email_verified_at' => now(),
        ]);
    }

    private function createOrganization(User $creator): Organization
    {
        return Organization::create([
            'name' => 'Organization '.Str::random(4),
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => $creator->id,
            'organization_type' => 'main',
            'parent_organization_id' => null,
            'metadata' => json_encode(['addons' => []]),
        ]);
    }

    private function ownerRole(): OrganizationRole
    {
        return OrganizationRole::firstOrCreate(
            ['organization_id' => null, 'name' => 'Owner'],
            ['description' => 'Owner', 'permissions' => ['*']]
        );
    }

    private function createActiveSubscription(Organization $organization, string $planName = 'Plan'): Subscription
    {
        $plan = SubscriptionPlan::create([
            'name' => $planName,
            'price' => 10,
            'period' => 'monthly',
            'metadata' => json_encode([]),
            'status' => 'active',
        ]);

        return Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'payment_details' => null,
            'start_date' => now(),
            'valid_until' => now()->addMonth(),
            'status' => 'active',
        ]);
    }
}
