<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationEmployee;
use App\Models\OrganizationEmployeeAssignment;
use App\Models\OrganizationRole;
use App\Models\Team;
use App\Models\TeamInvite;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminUserDeletionSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_destroying_user_cleans_team_company_employee_and_invite_records(): void
    {
        $owner = $this->createUser('owner+'.Str::random(8).'@example.com');
        $employee = $this->createUser('employee+'.Str::random(8).'@example.com');
        $organization = $this->createOrganization($owner);
        $ownerRole = $this->ownerRole();
        $managerRole = $this->createWorkspaceRole($organization, 'Manager', ['settings.manage']);

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        $team = Team::create([
            'organization_id' => $organization->id,
            'user_id' => $employee->id,
            'organization_role_id' => $managerRole->id,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        $organizationEmployee = OrganizationEmployee::create([
            'main_organization_id' => $organization->id,
            'user_id' => $employee->id,
            'email' => strtolower((string) $employee->email),
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        $assignment = OrganizationEmployeeAssignment::create([
            'organization_employee_id' => $organizationEmployee->id,
            'organization_id' => $organization->id,
            'organization_role_id' => $managerRole->id,
            'status' => 'active',
            'assigned_by' => $owner->id,
            'assigned_at' => now(),
            'activated_at' => now(),
        ]);

        TeamInvite::create([
            'organization_id' => $organization->id,
            'email' => strtolower((string) $employee->email),
            'code' => md5(now()->timestamp.Str::random(16)),
            'invited_by' => $owner->id,
            'expire_at' => now()->addDay(),
            'organization_role_id' => $managerRole->id,
        ]);

        (new UserService('user'))->destroy($employee->id);

        $this->assertSoftDeleted('users', [
            'id' => $employee->id,
        ]);
        $this->assertSoftDeleted('teams', [
            'id' => $team->id,
        ]);
        $this->assertSoftDeleted('organization_employees', [
            'id' => $organizationEmployee->id,
        ]);
        $this->assertSoftDeleted('organization_employee_assignments', [
            'id' => $assignment->id,
        ]);
        $this->assertDatabaseMissing('team_invites', [
            'email' => strtolower((string) $employee->email),
        ]);
    }

    public function test_destroying_main_owner_is_blocked_until_ownership_is_transferred(): void
    {
        $owner = $this->createUser('owner+'.Str::random(8).'@example.com');
        $nextUser = $this->createUser('next+'.Str::random(8).'@example.com');
        $organization = $this->createOrganization($owner);
        $ownerRole = $this->ownerRole();
        $managerRole = $this->createWorkspaceRole($organization, 'Manager', ['settings.manage']);

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        $promotedTeam = Team::create([
            'organization_id' => $organization->id,
            'user_id' => $nextUser->id,
            'organization_role_id' => $managerRole->id,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        $organizationEmployee = OrganizationEmployee::create([
            'main_organization_id' => $organization->id,
            'user_id' => $nextUser->id,
            'email' => strtolower((string) $nextUser->email),
            'first_name' => $nextUser->first_name,
            'last_name' => $nextUser->last_name,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        OrganizationEmployeeAssignment::create([
            'organization_employee_id' => $organizationEmployee->id,
            'organization_id' => $organization->id,
            'organization_role_id' => $managerRole->id,
            'status' => 'active',
            'assigned_by' => $owner->id,
            'assigned_at' => now(),
            'activated_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('This account owns one or more main organizations.');

        try {
            (new UserService('user'))->destroy($owner->id);
        } finally {
            $promotedTeam->refresh();

            $this->assertSame($managerRole->id, $promotedTeam->organization_role_id);
            $this->assertDatabaseHas('users', [
                'id' => $owner->id,
                'deleted_at' => null,
            ]);
            $this->assertDatabaseHas('organization_employee_assignments', [
                'organization_employee_id' => $organizationEmployee->id,
                'organization_id' => $organization->id,
                'organization_role_id' => $managerRole->id,
                'deleted_at' => null,
            ]);
        }
    }

    public function test_updating_user_service_can_change_password_for_existing_user(): void
    {
        $user = $this->createUser('update-password+'.Str::random(8).'@example.com');

        $request = Request::create('/admin/users/'.$user->id, 'PUT', [
            'first_name' => 'Updated',
            'last_name' => 'User',
            'email' => $user->email,
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ]);

        (new UserService('user'))->update($request, $user->id);

        $user->refresh();

        $this->assertSame('Updated', $user->first_name);
        $this->assertSame('User', $user->last_name);
        $this->assertTrue(Hash::check('new-password-456', $user->password));
    }

    private function createOrganization(User $creator): Organization
    {
        return Organization::create([
            'name' => 'Organization '.Str::random(4),
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => $creator->id,
            'organization_type' => 'main',
            'parent_organization_id' => null,
            'metadata' => json_encode([
                'addons' => [],
            ]),
        ]);
    }

    private function createWorkspaceRole(Organization $organization, string $name, array $permissions): OrganizationRole
    {
        return OrganizationRole::create([
            'organization_id' => $organization->id,
            'name' => $name.' '.Str::random(4),
            'description' => $name,
            'permissions' => $permissions,
        ]);
    }

    private function ownerRole(): OrganizationRole
    {
        return OrganizationRole::firstOrCreate(
            ['organization_id' => null, 'name' => 'Owner'],
            ['description' => 'Owner', 'permissions' => ['*']]
        );
    }

    private function createUser(string $email): User
    {
        return User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);
    }
}
