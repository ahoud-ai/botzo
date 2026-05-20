<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationEmployee;
use App\Models\OrganizationEmployeeAssignment;
use App\Models\OrganizationRole;
use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class UserSeatEnforcementTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_existing_family_user_can_be_invited_to_another_workspace_when_limit_is_full(): void
    {
        $this->disableEmails();

        [$owner, $company, $ownerRole] = $this->createOwnerContext();
        $this->createActiveSubscription($company->id, [
            'team_limit' => 2,
            'branches_limit' => 2,
        ]);

        $branch = $this->createBranch($company, $owner, 'Operations');
        $branchRole = $this->createLocalRole($branch, 'Branch Agent', ['contacts.view_all']);
        $employee = $this->createUser('seat-existing+'.Str::lower(Str::random(8)).'@example.com');

        Team::create([
            'organization_id' => $company->id,
            'user_id' => $employee->id,
            'organization_role_id' => $branchRole->id,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        Team::create([
            'organization_id' => $branch->id,
            'user_id' => $owner->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $branch->id])
            ->post('/settings/team/invite', [
                'email' => $employee->email,
                'organization_role_id' => $branchRole->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status.type', 'success');

        $this->assertDatabaseHas('team_invites', [
            'organization_id' => $branch->id,
            'email' => strtolower($employee->email),
        ]);
    }

    public function test_new_workspace_user_is_blocked_when_limit_is_full(): void
    {
        $this->disableEmails();

        [$owner, $company] = $this->createOwnerContext();
        $this->createActiveSubscription($company->id, [
            'team_limit' => 2,
            'branches_limit' => 1,
        ]);

        $role = $this->createLocalRole($company, 'Company Agent', ['contacts.view_all']);
        $existingEmployee = $this->createUser('seat-member+'.Str::lower(Str::random(8)).'@example.com');

        Team::create([
            'organization_id' => $company->id,
            'user_id' => $existingEmployee->id,
            'organization_role_id' => $role->id,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        $newInviteEmail = 'seat-new+'.Str::lower(Str::random(8)).'@example.com';

        $response = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->post('/settings/team/invite', [
                'email' => $newInviteEmail,
                'organization_role_id' => $role->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
        $response->assertSessionHas('status.type', 'error');

        $this->assertDatabaseMissing('team_invites', [
            'organization_id' => $company->id,
            'email' => strtolower($newInviteEmail),
        ]);
    }

    public function test_restoring_suspended_employee_is_blocked_when_limit_is_full(): void
    {
        $this->disableEmails();

        [$owner, $company] = $this->createOwnerContext();
        $this->createActiveSubscription($company->id, [
            'team_limit' => 2,
            'branches_limit' => 1,
        ]);

        $role = $this->createLocalRole($company, 'Company Agent', ['contacts.view_all']);
        $activeEmployee = $this->createUser('seat-active+'.Str::lower(Str::random(8)).'@example.com');
        $suspendedEmployeeUser = $this->createUser('seat-suspended+'.Str::lower(Str::random(8)).'@example.com');

        Team::create([
            'organization_id' => $company->id,
            'user_id' => $activeEmployee->id,
            'organization_role_id' => $role->id,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        $employee = OrganizationEmployee::create([
            'main_organization_id' => $company->id,
            'user_id' => $suspendedEmployeeUser->id,
            'email' => strtolower($suspendedEmployeeUser->email),
            'first_name' => $suspendedEmployeeUser->first_name,
            'last_name' => $suspendedEmployeeUser->last_name,
            'status' => 'suspended',
            'invited_by' => $owner->id,
            'invited_at' => now()->subDay(),
            'accepted_at' => now()->subDay(),
            'suspended_at' => now()->subHour(),
        ]);

        OrganizationEmployeeAssignment::create([
            'organization_employee_id' => $employee->id,
            'organization_id' => $company->id,
            'organization_role_id' => $role->id,
            'status' => 'suspended',
            'assigned_by' => $owner->id,
            'assigned_at' => now()->subDay(),
            'activated_at' => now()->subDay(),
            'suspended_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $company->id])
            ->post('/settings/company-team/'.$employee->uuid.'/restore');

        $response->assertRedirect();
        $response->assertSessionHas('status.type', 'error');

        $employee->refresh();

        $this->assertSame('suspended', $employee->status);
        $this->assertDatabaseMissing('teams', [
            'organization_id' => $company->id,
            'user_id' => $suspendedEmployeeUser->id,
            'deleted_at' => null,
        ]);
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
            'description' => $name.' role for user seat enforcement tests',
            'permissions' => $permissions,
        ]);
    }

    private function createUser(string $email): User
    {
        return User::create([
            'first_name' => 'Seat',
            'last_name' => 'User',
            'email' => strtolower($email),
            'password' => Hash::make('password123'),
            'role' => 'user',
            'status' => 1,
            'language' => 'en',
            'email_verified_at' => now(),
        ]);
    }
}
