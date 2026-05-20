<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationEmployee;
use App\Models\OrganizationEmployeeAssignment;
use App\Models\OrganizationRole;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class AdminProvisioningCreateFlowTest extends TestCase
{
    use CreatesOrganizationContext;
    use RefreshDatabase;

    public function test_admin_create_pages_use_the_new_surfaces(): void
    {
        $admin = $this->createAdminUser();

        $this->actingAs($admin, 'admin')
            ->get('/admin/users/create')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/User/Create')
                ->has('plans')
                ->has('companies')
                ->has('companyCatalog')
            );

        $this->actingAs($admin, 'admin')
            ->get('/admin/organizations/create')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Organization/Create')
                ->has('plans')
                ->has('parentOrganizations')
            );
    }

    public function test_admin_can_create_main_organization_without_plan_and_assign_a_new_owner(): void
    {
        $this->disableEmails();
        $admin = $this->createAdminUser();
        $ownerEmail = 'new-owner+'.Str::random(6).'@example.com';

        $response = $this->actingAs($admin, 'admin')
            ->post('/admin/organizations', [
                'name' => 'Shell Organization',
                'organization_type' => 'main',
                'billing_setup_mode' => 'later',
                'create_user' => 1,
                'first_name' => 'Shell',
                'last_name' => 'Owner',
                'email' => $ownerEmail,
                'phone' => '+966501112233',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'send_registration_email' => 0,
            ]);

        $organization = Organization::query()->where('name', 'Shell Organization')->first();
        $owner = User::query()->where('email', $ownerEmail)->first();

        $response->assertRedirect('/admin/organizations/'.$organization?->uuid);
        $this->assertNotNull($organization);
        $this->assertNotNull($owner);
        $this->assertSame($admin->id, (int) $organization->created_by);
        $this->assertDatabaseMissing('subscriptions', [
            'organization_id' => $organization->id,
        ]);
        $this->assertDatabaseHas('teams', [
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_create_main_organization_for_an_existing_owner_without_plan(): void
    {
        $this->disableEmails();
        $admin = $this->createAdminUser();
        $existingOwner = $this->createCustomerUser('existing-owner+'.Str::random(6).'@example.com');

        $response = $this->actingAs($admin, 'admin')
            ->post('/admin/organizations', [
                'name' => 'Existing Owner Org',
                'organization_type' => 'main',
                'billing_setup_mode' => 'later',
                'create_user' => 0,
                'owner_user_id' => $existingOwner->id,
            ]);

        $organization = Organization::query()->where('name', 'Existing Owner Org')->first();

        $response->assertRedirect('/admin/organizations/'.$organization?->uuid);
        $this->assertNotNull($organization);
        $this->assertDatabaseMissing('subscriptions', [
            'organization_id' => $organization->id,
        ]);
        $this->assertDatabaseHas('teams', [
            'organization_id' => $organization->id,
            'user_id' => $existingOwner->id,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_create_owner_user_with_main_organization_shell_and_billing_later(): void
    {
        $this->disableEmails();
        $admin = $this->createAdminUser();
        $userEmail = 'owner-flow+'.Str::random(6).'@example.com';

        $response = $this->actingAs($admin, 'admin')
            ->post('/admin/users', [
                'creation_mode' => 'owner_new_org',
                'first_name' => 'Owner',
                'last_name' => 'Flow',
                'email' => $userEmail,
                'phone' => '+966501122334',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'send_registration_email' => 0,
                'organization_name' => 'Owner Flow Company',
                'organization_billing_mode' => 'later',
            ]);

        $user = User::query()->where('email', $userEmail)->first();
        $organization = Organization::query()->where('name', 'Owner Flow Company')->first();

        $response->assertRedirect('/admin/users/'.$user?->id);
        $this->assertNotNull($user);
        $this->assertNotNull($organization);
        $this->assertDatabaseMissing('subscriptions', [
            'organization_id' => $organization->id,
        ]);
        $this->assertDatabaseHas('teams', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_create_employee_user_and_assign_workspace_access_for_an_active_company(): void
    {
        $this->disableEmails();
        $admin = $this->createAdminUser();
        [$owner, $company] = $this->createOwnerContext(withActiveSubscription: true);
        $branch = $this->createBranch($company, $owner, 'Provisioned Branch');
        $mainRole = $this->createLocalRole($company, 'HQ Supervisor', ['settings.manage']);
        $branchRole = $this->createLocalRole($branch, 'Branch Supervisor', ['contacts.view_all']);
        $email = 'employee-create+'.Str::random(6).'@example.com';

        $response = $this->actingAs($admin, 'admin')
            ->post('/admin/users', [
                'creation_mode' => 'employee_existing_org',
                'first_name' => 'Provisioned',
                'last_name' => 'Employee',
                'email' => $email,
                'phone' => '+966502233445',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'send_registration_email' => 0,
                'company_uuid' => (string) $company->uuid,
                'assignments' => [
                    [
                        'organization_uuid' => (string) $company->uuid,
                        'organization_role_id' => $mainRole->id,
                    ],
                    [
                        'organization_uuid' => (string) $branch->uuid,
                        'organization_role_id' => $branchRole->id,
                    ],
                ],
            ]);

        $user = User::query()->where('email', $email)->first();
        $employee = OrganizationEmployee::query()
            ->where('main_organization_id', $company->id)
            ->where('email', strtolower($email))
            ->first();

        $response->assertRedirect('/admin/users/'.$user?->id);
        $this->assertNotNull($user);
        $this->assertNotNull($employee);
        $this->assertSame('active', $employee->status);
        $this->assertSame(2, OrganizationEmployeeAssignment::query()
            ->where('organization_employee_id', $employee->id)
            ->whereNull('deleted_at')
            ->count());
        $this->assertDatabaseHas('teams', [
            'organization_id' => $company->id,
            'user_id' => $user->id,
            'organization_role_id' => $mainRole->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('teams', [
            'organization_id' => $branch->id,
            'user_id' => $user->id,
            'organization_role_id' => $branchRole->id,
            'status' => 'active',
        ]);
    }

    public function test_admin_employee_provisioning_requires_an_operational_company_subscription(): void
    {
        $this->disableEmails();
        $admin = $this->createAdminUser();
        [$owner, $company] = $this->createOwnerContext(withActiveSubscription: false);
        $role = $this->createLocalRole($company, 'Workspace Operator', ['contacts.view_all']);
        $email = 'blocked-employee+'.Str::random(6).'@example.com';

        $response = $this->from('/admin/users/create')
            ->actingAs($admin, 'admin')
            ->post('/admin/users', [
                'creation_mode' => 'employee_existing_org',
                'first_name' => 'Blocked',
                'last_name' => 'Employee',
                'email' => $email,
                'phone' => '+966503344556',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'send_registration_email' => 0,
                'company_uuid' => (string) $company->uuid,
                'assignments' => [
                    [
                        'organization_uuid' => (string) $company->uuid,
                        'organization_role_id' => $role->id,
                    ],
                ],
            ]);

        $response->assertRedirect('/admin/users/create');
        $response->assertSessionHasErrors('company_uuid');
        $this->assertDatabaseMissing('users', [
            'email' => $email,
        ]);
    }

    private function disableEmails(): void
    {
        Setting::updateOrCreate(['key' => 'smtp_email_active'], ['value' => '0']);
    }

    private function createAdminUser(): User
    {
        return User::create([
            'first_name' => 'Admin',
            'last_name' => 'Ops',
            'email' => 'admin-provisioning+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    private function createCustomerUser(string $email): User
    {
        return User::create([
            'first_name' => 'Existing',
            'last_name' => 'Owner',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'user',
            'status' => 1,
            'email_verified_at' => now(),
        ]);
    }

    private function createBranch(Organization $company, User $owner, string $name): Organization
    {
        return Organization::create([
            'identifier' => 'branch-'.Str::lower(Str::random(8)),
            'name' => $name.' '.Str::random(4),
            'created_by' => $owner->id,
            'organization_type' => 'branch',
            'parent_organization_id' => $company->id,
            'metadata' => json_encode([]),
        ]);
    }

    private function createLocalRole(Organization $organization, string $name, array $permissions): OrganizationRole
    {
        return OrganizationRole::create([
            'organization_id' => $organization->id,
            'name' => $name.' '.Str::random(4),
            'description' => $name.' role for provisioning tests',
            'permissions' => $permissions,
        ]);
    }
}
