<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationEmployee;
use App\Models\OrganizationEmployeeAssignment;
use App\Models\OrganizationRole;
use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class CompanyWorkforceTest extends TestCase
{
    use CreatesOrganizationContext;
    use RefreshDatabase;

    public function test_owner_can_view_company_team_page_with_synced_previous_family_memberships(): void
    {
        $this->disableEmails();
        [$owner, $company] = $this->createOwnerContext(withActiveSubscription: true);

        $branch = $this->createBranch($company, $owner, 'North Branch');
        $branchRole = $this->createLocalRole($branch, 'Branch Manager', ['settings.manage']);
        $branchUser = $this->createUser('branch-manager+'.Str::random(8).'@example.com');

        Team::create([
            'organization_id' => $branch->id,
            'user_id' => $branchUser->id,
            'organization_role_id' => $branchRole->id,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->get('/settings/company-team');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/Team/CompanyIndex')
            ->where('title', 'Company team')
            ->where('summary.employees', 2)
            ->where('summary.active', 2)
            ->where('summary.pending', 0)
            ->where('summary.workspaces', 2)
            ->has('rows.data', 2)
            ->has('workspaces', 2)
        );

        $this->assertDatabaseHas('organization_employees', [
            'main_organization_id' => $company->id,
            'email' => $owner->email,
        ]);
        $this->assertDatabaseHas('organization_employees', [
            'main_organization_id' => $company->id,
            'email' => $branchUser->email,
        ]);
    }

    public function test_owner_can_invite_existing_user_to_multiple_family_workspaces_after_acceptance(): void
    {
        $this->disableEmails();
        [$owner, $company] = $this->createOwnerContext(withActiveSubscription: true);

        $hqRole = $this->createLocalRole($company, 'HQ Manager', ['settings.manage']);
        $branch = $this->createBranch($company, $owner, 'South Branch');
        $branchRole = $this->createLocalRole($branch, 'Branch Supervisor', ['contacts.view_all']);
        $employeeUser = $this->createUser('existing-employee+'.Str::random(8).'@example.com');

        $response = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->post('/settings/company-team/invite', [
                'email' => $employeeUser->email,
                'assignments' => [
                    [
                        'organization_uuid' => (string) $company->uuid,
                        'organization_role_id' => $hqRole->id,
                    ],
                    [
                        'organization_uuid' => (string) $branch->uuid,
                        'organization_role_id' => $branchRole->id,
                    ],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status.type', 'success');

        $employee = OrganizationEmployee::query()
            ->where('main_organization_id', $company->id)
            ->where('email', $employeeUser->email)
            ->first();

        $this->assertNotNull($employee);
        $this->assertSame($employeeUser->id, $employee->user_id);
        $this->assertSame('pending', $employee->status);
        $this->assertNotNull($employee->invite_code);

        $this->assertSame(2, OrganizationEmployeeAssignment::query()
            ->where('organization_employee_id', $employee->id)
            ->count());

        $this->assertDatabaseMissing('teams', [
            'organization_id' => $company->id,
            'user_id' => $employeeUser->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseMissing('teams', [
            'organization_id' => $branch->id,
            'user_id' => $employeeUser->id,
            'deleted_at' => null,
        ]);

        $this->post('/invite/'.$employee->invite_code, [
            'first_name' => $employeeUser->first_name,
            'last_name' => $employeeUser->last_name,
            'email' => $employeeUser->email,
        ])->assertRedirect('/dashboard');

        $employee->refresh();
        $this->assertSame('active', $employee->status);

        $this->assertDatabaseHas('teams', [
            'organization_id' => $company->id,
            'user_id' => $employeeUser->id,
            'organization_role_id' => $hqRole->id,
        ]);
        $this->assertDatabaseHas('teams', [
            'organization_id' => $branch->id,
            'user_id' => $employeeUser->id,
            'organization_role_id' => $branchRole->id,
        ]);
    }

    public function test_company_team_page_reconciles_duplicate_employee_rows(): void
    {
        $this->disableEmails();
        [$owner, $company] = $this->createOwnerContext(withActiveSubscription: true);
        $role = $this->createLocalRole($company, 'HQ Supervisor', ['settings.manage']);
        $employeeUser = $this->createUser('duplicate-row+'.Str::random(8).'@example.com');

        $firstEmployee = OrganizationEmployee::create([
            'main_organization_id' => $company->id,
            'user_id' => $employeeUser->id,
            'email' => strtolower($employeeUser->email),
            'first_name' => $employeeUser->first_name,
            'last_name' => $employeeUser->last_name,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        $secondEmployee = OrganizationEmployee::create([
            'main_organization_id' => $company->id,
            'user_id' => $employeeUser->id,
            'email' => strtolower($employeeUser->email),
            'first_name' => $employeeUser->first_name,
            'last_name' => $employeeUser->last_name,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        OrganizationEmployeeAssignment::create([
            'organization_employee_id' => $firstEmployee->id,
            'organization_id' => $company->id,
            'organization_role_id' => $role->id,
            'status' => 'active',
            'assigned_by' => $owner->id,
            'assigned_at' => now(),
            'activated_at' => now(),
        ]);

        OrganizationEmployeeAssignment::create([
            'organization_employee_id' => $secondEmployee->id,
            'organization_id' => $company->id,
            'organization_role_id' => $role->id,
            'status' => 'active',
            'assigned_by' => $owner->id,
            'assigned_at' => now(),
            'activated_at' => now(),
        ]);

        $response = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->get('/settings/company-team');

        $response->assertOk();

        $this->assertSame(1, OrganizationEmployee::query()
            ->where('main_organization_id', $company->id)
            ->where('email', strtolower($employeeUser->email))
            ->whereNull('deleted_at')
            ->count());
        $this->assertSame(1, OrganizationEmployee::onlyTrashed()
            ->where('main_organization_id', $company->id)
            ->where('email', strtolower($employeeUser->email))
            ->count());
        $this->assertSame(1, OrganizationEmployeeAssignment::query()
            ->whereIn('organization_employee_id', [$firstEmployee->id, $secondEmployee->id])
            ->where('organization_id', $company->id)
            ->whereNull('deleted_at')
            ->count());
    }

    public function test_new_company_employee_invite_can_be_viewed_and_accepted(): void
    {
        $this->disableEmails();
        [$owner, $company] = $this->createOwnerContext(withActiveSubscription: true);
        $hqRole = $this->createLocalRole($company, 'HQ Operator', ['settings.manage']);

        $inviteeEmail = 'new-employee+'.Str::lower(Str::random(8)).'@example.com';

        $inviteResponse = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->post('/settings/company-team/invite', [
                'email' => $inviteeEmail,
                'assignments' => [
                    [
                        'organization_uuid' => (string) $company->uuid,
                        'organization_role_id' => $hqRole->id,
                    ],
                ],
            ]);

        $inviteResponse->assertRedirect();
        $inviteResponse->assertSessionHasNoErrors();

        $employee = OrganizationEmployee::query()
            ->where('main_organization_id', $company->id)
            ->where('email', $inviteeEmail)
            ->first();

        $this->assertNotNull($employee);
        $this->assertSame('pending', $employee->status);
        $this->assertNotNull($employee->invite_code);

        $viewResponse = $this->get('/invite/'.$employee->invite_code);

        $viewResponse->assertOk();
        $viewResponse->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Invite')
            ->where('organization.name', $company->name)
            ->where('invite.email', strtolower($inviteeEmail))
        );

        $acceptResponse = $this->post('/invite/'.$employee->invite_code, [
            'first_name' => 'Invited',
            'last_name' => 'Employee',
            'email' => $inviteeEmail,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $acceptResponse->assertRedirect('/dashboard');
        $this->assertAuthenticated('user');

        $acceptedUser = User::query()->where('email', $inviteeEmail)->first();

        $this->assertNotNull($acceptedUser);
        $this->assertDatabaseHas('organization_employees', [
            'id' => $employee->id,
            'user_id' => $acceptedUser->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('teams', [
            'organization_id' => $company->id,
            'user_id' => $acceptedUser->id,
            'organization_role_id' => $hqRole->id,
        ]);
    }

    public function test_owner_can_resend_pending_company_employee_invite(): void
    {
        $this->disableEmails();
        [$owner, $company] = $this->createOwnerContext(withActiveSubscription: true);
        $hqRole = $this->createLocalRole($company, 'HQ Operator', ['settings.manage']);

        $inviteeEmail = 'resend-employee+'.Str::lower(Str::random(8)).'@example.com';

        $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->post('/settings/company-team/invite', [
                'email' => $inviteeEmail,
                'assignments' => [
                    [
                        'organization_uuid' => (string) $company->uuid,
                        'organization_role_id' => $hqRole->id,
                    ],
                ],
            ]);

        $employee = OrganizationEmployee::query()
            ->where('main_organization_id', $company->id)
            ->where('email', $inviteeEmail)
            ->firstOrFail();

        $originalInviteCode = $employee->invite_code;

        $response = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->post('/settings/company-team/'.$employee->uuid.'/resend-invite');

        $response->assertRedirect();
        $response->assertSessionHas('status.type', 'success');

        $employee->refresh();

        $this->assertSame('pending', $employee->status);
        $this->assertNotNull($employee->invite_code);
        $this->assertNotSame($originalInviteCode, $employee->invite_code);
        $this->assertNotNull($employee->invite_expires_at);
    }

    public function test_existing_platform_user_invite_requires_acceptance_before_company_access_is_created(): void
    {
        $this->disableEmails();
        [$owner, $company] = $this->createOwnerContext(withActiveSubscription: true);

        [$externalUser, $externalOrganization] = $this->createOwnerContext(withActiveSubscription: true);
        $companyRole = $this->createLocalRole($company, 'Company Agent', ['contacts.view_all']);

        $response = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->post('/settings/company-team/invite', [
                'email' => $externalUser->email,
                'assignments' => [
                    [
                        'organization_uuid' => (string) $company->uuid,
                        'organization_role_id' => $companyRole->id,
                    ],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $employee = OrganizationEmployee::query()
            ->where('main_organization_id', $company->id)
            ->where('email', $externalUser->email)
            ->firstOrFail();

        $this->assertSame('pending', $employee->status);
        $this->assertNotNull($employee->invite_code);
        $this->assertDatabaseMissing('teams', [
            'organization_id' => $company->id,
            'user_id' => $externalUser->id,
            'deleted_at' => null,
        ]);

        $this->post('/invite/'.$employee->invite_code, [
            'first_name' => $externalUser->first_name,
            'last_name' => $externalUser->last_name,
            'email' => $externalUser->email,
        ])->assertRedirect('/dashboard');

        $employee->refresh();

        $this->assertSame('active', $employee->status);
        $this->assertDatabaseHas('teams', [
            'organization_id' => $company->id,
            'user_id' => $externalUser->id,
            'organization_role_id' => $companyRole->id,
            'deleted_at' => null,
        ]);
    }

    public function test_owner_cannot_submit_duplicate_workspace_assignments_for_company_employee(): void
    {
        $this->disableEmails();
        [$owner, $company] = $this->createOwnerContext(withActiveSubscription: true);
        $firstRole = $this->createLocalRole($company, 'HQ Manager', ['settings.manage']);
        $secondRole = $this->createLocalRole($company, 'HQ Analyst', ['contacts.view_all']);
        $inviteeEmail = 'duplicate-assignment+'.Str::lower(Str::random(8)).'@example.com';

        $response = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->post('/settings/company-team/invite', [
                'email' => $inviteeEmail,
                'assignments' => [
                    [
                        'organization_uuid' => (string) $company->uuid,
                        'organization_role_id' => $firstRole->id,
                    ],
                    [
                        'organization_uuid' => (string) $company->uuid,
                        'organization_role_id' => $secondRole->id,
                    ],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors();

        $this->assertDatabaseMissing('organization_employees', [
            'main_organization_id' => $company->id,
            'email' => $inviteeEmail,
        ]);
    }

    public function test_owner_cannot_invite_same_pending_company_employee_twice(): void
    {
        $this->disableEmails();
        [$owner, $company] = $this->createOwnerContext(withActiveSubscription: true);
        $hqRole = $this->createLocalRole($company, 'HQ Operator', ['settings.manage']);
        $inviteeEmail = 'duplicate-pending+'.Str::lower(Str::random(8)).'@example.com';

        $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->post('/settings/company-team/invite', [
                'email' => $inviteeEmail,
                'assignments' => [
                    [
                        'organization_uuid' => (string) $company->uuid,
                        'organization_role_id' => $hqRole->id,
                    ],
                ],
            ])
            ->assertRedirect();

        $employee = OrganizationEmployee::query()
            ->where('main_organization_id', $company->id)
            ->where('email', $inviteeEmail)
            ->firstOrFail();

        $response = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->post('/settings/company-team/invite', [
                'email' => $inviteeEmail,
                'assignments' => [
                    [
                        'organization_uuid' => (string) $company->uuid,
                        'organization_role_id' => $hqRole->id,
                    ],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
        $response->assertSessionHas('status.type', 'error');

        $this->assertSame(1, OrganizationEmployee::query()
            ->where('main_organization_id', $company->id)
            ->where('email', $inviteeEmail)
            ->count());
        $this->assertSame(1, OrganizationEmployeeAssignment::query()
            ->where('organization_employee_id', $employee->id)
            ->whereNull('deleted_at')
            ->count());
    }

    public function test_owner_cannot_invite_existing_active_company_employee_again(): void
    {
        $this->disableEmails();
        [$owner, $company] = $this->createOwnerContext(withActiveSubscription: true);
        $hqRole = $this->createLocalRole($company, 'HQ Manager', ['settings.manage']);
        $branch = $this->createBranch($company, $owner, 'Repeat Branch');
        $branchRole = $this->createLocalRole($branch, 'Branch Supervisor', ['contacts.view_all']);
        $employeeUser = $this->createUser('duplicate-active+'.Str::random(8).'@example.com');

        $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->post('/settings/company-team/invite', [
                'email' => $employeeUser->email,
                'assignments' => [
                    [
                        'organization_uuid' => (string) $company->uuid,
                        'organization_role_id' => $hqRole->id,
                    ],
                ],
            ])
            ->assertRedirect();

        $employee = OrganizationEmployee::query()
            ->where('main_organization_id', $company->id)
            ->where('email', $employeeUser->email)
            ->firstOrFail();

        $response = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->post('/settings/company-team/invite', [
                'email' => $employeeUser->email,
                'assignments' => [
                    [
                        'organization_uuid' => (string) $company->uuid,
                        'organization_role_id' => $hqRole->id,
                    ],
                    [
                        'organization_uuid' => (string) $branch->uuid,
                        'organization_role_id' => $branchRole->id,
                    ],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
        $response->assertSessionHas('status.type', 'error');

        $this->assertSame(1, OrganizationEmployeeAssignment::query()
            ->where('organization_employee_id', $employee->id)
            ->whereNull('deleted_at')
            ->count());
        $this->assertDatabaseMissing('organization_employee_assignments', [
            'organization_employee_id' => $employee->id,
            'organization_id' => $branch->id,
            'deleted_at' => null,
        ]);
    }

    public function test_owner_can_delete_pending_company_employee(): void
    {
        $this->disableEmails();
        [$owner, $company] = $this->createOwnerContext(withActiveSubscription: true);
        $hqRole = $this->createLocalRole($company, 'HQ Operator', ['settings.manage']);

        $inviteeEmail = 'delete-pending+'.Str::lower(Str::random(8)).'@example.com';

        $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->post('/settings/company-team/invite', [
                'email' => $inviteeEmail,
                'assignments' => [
                    [
                        'organization_uuid' => (string) $company->uuid,
                        'organization_role_id' => $hqRole->id,
                    ],
                ],
            ]);

        $employee = OrganizationEmployee::query()
            ->where('main_organization_id', $company->id)
            ->where('email', $inviteeEmail)
            ->firstOrFail();

        $assignment = OrganizationEmployeeAssignment::query()
            ->where('organization_employee_id', $employee->id)
            ->firstOrFail();

        $response = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->delete('/settings/company-team/'.$employee->uuid);

        $response->assertRedirect();
        $response->assertSessionHas('status.type', 'success');

        $this->assertSoftDeleted('organization_employees', [
            'id' => $employee->id,
        ]);
        $this->assertSoftDeleted('organization_employee_assignments', [
            'id' => $assignment->id,
        ]);
    }

    public function test_owner_can_delete_active_company_employee_and_remove_operational_memberships(): void
    {
        $this->disableEmails();
        [$owner, $company] = $this->createOwnerContext(withActiveSubscription: true);

        $hqRole = $this->createLocalRole($company, 'HQ Manager', ['settings.manage']);
        $branch = $this->createBranch($company, $owner, 'West Branch');
        $branchRole = $this->createLocalRole($branch, 'Branch Supervisor', ['contacts.view_all']);
        $employeeUser = $this->createUser('delete-active+'.Str::random(8).'@example.com');

        $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->post('/settings/company-team/invite', [
                'email' => $employeeUser->email,
                'assignments' => [
                    [
                        'organization_uuid' => (string) $company->uuid,
                        'organization_role_id' => $hqRole->id,
                    ],
                    [
                        'organization_uuid' => (string) $branch->uuid,
                        'organization_role_id' => $branchRole->id,
                    ],
                ],
            ]);

        $employee = OrganizationEmployee::query()
            ->where('main_organization_id', $company->id)
            ->where('email', $employeeUser->email)
            ->firstOrFail();

        $this->post('/invite/'.$employee->invite_code, [
            'first_name' => $employeeUser->first_name,
            'last_name' => $employeeUser->last_name,
            'email' => $employeeUser->email,
        ])->assertRedirect('/dashboard');

        $response = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->delete('/settings/company-team/'.$employee->uuid);

        $response->assertRedirect();
        $response->assertSessionHas('status.type', 'success');

        $this->assertSoftDeleted('organization_employees', [
            'id' => $employee->id,
        ]);
        $this->assertSoftDeleted('teams', [
            'organization_id' => $company->id,
            'user_id' => $employeeUser->id,
        ]);
        $this->assertSoftDeleted('teams', [
            'organization_id' => $branch->id,
            'user_id' => $employeeUser->id,
        ]);
    }

    public function test_owner_can_update_company_employee_profile_password_and_assignments(): void
    {
        $this->disableEmails();
        [$owner, $company] = $this->createOwnerContext(withActiveSubscription: true);

        $hqRole = $this->createLocalRole($company, 'HQ Manager', ['settings.manage']);
        $oldBranch = $this->createBranch($company, $owner, 'Old Branch');
        $oldBranchRole = $this->createLocalRole($oldBranch, 'Old Supervisor', ['contacts.view_all']);
        $newBranch = $this->createBranch($company, $owner, 'New Branch');
        $newBranchRole = $this->createLocalRole($newBranch, 'New Supervisor', ['contacts.view_all']);
        $employeeUser = $this->createUser('update-active+'.Str::random(8).'@example.com');

        $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->post('/settings/company-team/invite', [
                'email' => $employeeUser->email,
                'assignments' => [
                    [
                        'organization_uuid' => (string) $company->uuid,
                        'organization_role_id' => $hqRole->id,
                    ],
                    [
                        'organization_uuid' => (string) $oldBranch->uuid,
                        'organization_role_id' => $oldBranchRole->id,
                    ],
                ],
            ]);

        $employee = OrganizationEmployee::query()
            ->where('main_organization_id', $company->id)
            ->where('email', $employeeUser->email)
            ->firstOrFail();

        $this->post('/invite/'.$employee->invite_code, [
            'first_name' => $employeeUser->first_name,
            'last_name' => $employeeUser->last_name,
            'email' => $employeeUser->email,
        ])->assertRedirect('/dashboard');

        $updatedEmail = 'updated-active+'.Str::lower(Str::random(8)).'@example.com';

        $response = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->put('/settings/company-team/'.$employee->uuid, [
                'first_name' => 'Updated',
                'last_name' => 'Employee',
                'email' => $updatedEmail,
                'password' => 'new-password-456',
                'password_confirmation' => 'new-password-456',
                'assignments' => [
                    [
                        'organization_uuid' => (string) $company->uuid,
                        'organization_role_id' => $hqRole->id,
                    ],
                    [
                        'organization_uuid' => (string) $newBranch->uuid,
                        'organization_role_id' => $newBranchRole->id,
                    ],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status.type', 'success');

        $employee->refresh();
        $employeeUser->refresh();

        $this->assertSame($updatedEmail, $employee->email);
        $this->assertSame('Updated', $employee->first_name);
        $this->assertSame('Employee', $employee->last_name);
        $this->assertSame($updatedEmail, $employeeUser->email);
        $this->assertSame('Updated', $employeeUser->first_name);
        $this->assertSame('Employee', $employeeUser->last_name);
        $this->assertTrue(Hash::check('new-password-456', $employeeUser->password));

        $this->assertDatabaseHas('organization_employee_assignments', [
            'organization_employee_id' => $employee->id,
            'organization_id' => $newBranch->id,
            'organization_role_id' => $newBranchRole->id,
            'deleted_at' => null,
        ]);
        $this->assertSoftDeleted('organization_employee_assignments', [
            'organization_employee_id' => $employee->id,
            'organization_id' => $oldBranch->id,
        ]);
        $this->assertDatabaseHas('teams', [
            'organization_id' => $newBranch->id,
            'user_id' => $employeeUser->id,
            'organization_role_id' => $newBranchRole->id,
            'deleted_at' => null,
        ]);
        $this->assertSoftDeleted('teams', [
            'organization_id' => $oldBranch->id,
            'user_id' => $employeeUser->id,
        ]);
    }

    public function test_owner_can_update_company_employee_without_changing_email(): void
    {
        $this->disableEmails();
        [$owner, $company] = $this->createOwnerContext(withActiveSubscription: true);

        $hqRole = $this->createLocalRole($company, 'HQ Manager', ['settings.manage']);
        $employeeUser = $this->createUser('same-email-update+'.Str::random(8).'@example.com');

        $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->post('/settings/company-team/invite', [
                'email' => $employeeUser->email,
                'assignments' => [
                    [
                        'organization_uuid' => (string) $company->uuid,
                        'organization_role_id' => $hqRole->id,
                    ],
                ],
            ])
            ->assertRedirect();

        $employee = OrganizationEmployee::query()
            ->where('main_organization_id', $company->id)
            ->where('email', $employeeUser->email)
            ->firstOrFail();

        $this->post('/invite/'.$employee->invite_code, [
            'first_name' => $employeeUser->first_name,
            'last_name' => $employeeUser->last_name,
            'email' => $employeeUser->email,
        ])->assertRedirect('/dashboard');

        $response = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->put('/settings/company-team/'.$employee->uuid, [
                'first_name' => 'Ahmed',
                'last_name' => 'Updated',
                'email' => $employeeUser->email,
                'assignments' => [
                    [
                        'organization_uuid' => (string) $company->uuid,
                        'organization_role_id' => $hqRole->id,
                    ],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status.type', 'success');

        $employee->refresh();
        $employeeUser->refresh();

        $this->assertSame($employeeUser->email, $employee->email);
        $this->assertSame('Ahmed', $employee->first_name);
        $this->assertSame('Updated', $employee->last_name);
        $this->assertSame('Ahmed', $employeeUser->first_name);
        $this->assertSame('Updated', $employeeUser->last_name);
    }

    public function test_owner_can_suspend_and_restore_active_company_employee(): void
    {
        $this->disableEmails();
        [$owner, $company] = $this->createOwnerContext(withActiveSubscription: true);

        $hqRole = $this->createLocalRole($company, 'HQ Manager', ['settings.manage']);
        $employeeUser = $this->createUser('suspend-restore+'.Str::random(8).'@example.com');

        $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->post('/settings/company-team/invite', [
                'email' => $employeeUser->email,
                'assignments' => [
                    [
                        'organization_uuid' => (string) $company->uuid,
                        'organization_role_id' => $hqRole->id,
                    ],
                ],
            ])
            ->assertRedirect();

        $employee = OrganizationEmployee::query()
            ->where('main_organization_id', $company->id)
            ->where('email', $employeeUser->email)
            ->firstOrFail();

        $this->post('/invite/'.$employee->invite_code, [
            'first_name' => $employeeUser->first_name,
            'last_name' => $employeeUser->last_name,
            'email' => $employeeUser->email,
        ])->assertRedirect('/dashboard');

        $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->post('/settings/company-team/'.$employee->uuid.'/suspend')
            ->assertRedirect();

        $employee->refresh();
        $this->assertSame('suspended', $employee->status);
        $this->assertNotNull($employee->suspended_at);
        $this->assertSoftDeleted('teams', [
            'organization_id' => $company->id,
            'user_id' => $employeeUser->id,
        ]);

        $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->post('/settings/company-team/'.$employee->uuid.'/restore')
            ->assertRedirect();

        $employee->refresh();
        $this->assertSame('active', $employee->status);
        $this->assertNull($employee->invite_code);
        $this->assertDatabaseHas('teams', [
            'organization_id' => $company->id,
            'user_id' => $employeeUser->id,
            'organization_role_id' => $hqRole->id,
            'deleted_at' => null,
        ]);
    }

    public function test_readding_deleted_company_employee_restores_active_access_without_new_acceptance(): void
    {
        $this->disableEmails();
        [$owner, $company] = $this->createOwnerContext(withActiveSubscription: true);

        $hqRole = $this->createLocalRole($company, 'HQ Manager', ['settings.manage']);
        $employeeUser = $this->createUser('restore-deleted+'.Str::random(8).'@example.com');

        $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->post('/settings/company-team/invite', [
                'email' => $employeeUser->email,
                'assignments' => [
                    [
                        'organization_uuid' => (string) $company->uuid,
                        'organization_role_id' => $hqRole->id,
                    ],
                ],
            ])
            ->assertRedirect();

        $employee = OrganizationEmployee::query()
            ->where('main_organization_id', $company->id)
            ->where('email', $employeeUser->email)
            ->firstOrFail();

        $this->post('/invite/'.$employee->invite_code, [
            'first_name' => $employeeUser->first_name,
            'last_name' => $employeeUser->last_name,
            'email' => $employeeUser->email,
        ])->assertRedirect('/dashboard');

        $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->delete('/settings/company-team/'.$employee->uuid)
            ->assertRedirect();

        $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->post('/settings/company-team/invite', [
                'email' => $employeeUser->email,
                'assignments' => [
                    [
                        'organization_uuid' => (string) $company->uuid,
                        'organization_role_id' => $hqRole->id,
                    ],
                ],
            ])
            ->assertRedirect();

        $restoredEmployee = OrganizationEmployee::query()
            ->where('main_organization_id', $company->id)
            ->where('email', $employeeUser->email)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $this->assertSame('active', $restoredEmployee->status);
        $this->assertNull($restoredEmployee->invite_code);
        $this->assertSame($employeeUser->id, $restoredEmployee->user_id);
        $this->assertDatabaseHas('teams', [
            'organization_id' => $company->id,
            'user_id' => $employeeUser->id,
            'organization_role_id' => $hqRole->id,
            'deleted_at' => null,
        ]);
    }

    public function test_owner_cannot_delete_main_admin_from_company_team(): void
    {
        $this->disableEmails();
        [$owner, $company] = $this->createOwnerContext(withActiveSubscription: true);

        $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->get('/settings/company-team');

        $employee = OrganizationEmployee::query()
            ->where('main_organization_id', $company->id)
            ->where('email', $owner->email)
            ->firstOrFail();

        $response = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->delete('/settings/company-team/'.$employee->uuid);

        $response->assertRedirect();
        $response->assertSessionHas('status.type', 'error');

        $employee->refresh();
        $this->assertNull($employee->deleted_at);
    }

    public function test_parent_owner_can_open_company_team_from_branch_context(): void
    {
        $this->disableEmails();
        [$owner, $company] = $this->createOwnerContext(withActiveSubscription: true);
        $branch = $this->createBranch($company, $owner, 'Inherited Branch');

        $response = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $branch->id])
            ->get('/settings/company-team');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/Team/CompanyIndex')
            ->where('company.uuid', (string) $company->uuid)
            ->where('company.name', $company->name)
        );
    }

    private function disableEmails(): void
    {
        Setting::updateOrCreate(['key' => 'smtp_email_active'], ['value' => '0']);
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
            'description' => $name.' role for workforce tests',
            'permissions' => $permissions,
        ]);
    }

    private function createUser(string $email): User
    {
        return User::create([
            'first_name' => 'Workforce',
            'last_name' => 'User',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'user',
            'status' => 1,
            'language' => 'en',
            'email_verified_at' => now(),
        ]);
    }
}
