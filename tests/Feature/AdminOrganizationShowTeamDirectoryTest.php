<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationEmployee;
use App\Models\OrganizationEmployeeAssignment;
use App\Models\OrganizationRole;
use App\Models\Team;
use App\Models\User;
use App\Services\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminOrganizationShowTeamDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_team_directory_hides_removed_previous_rows_and_collapses_duplicate_assignments(): void
    {
        $owner = $this->createUser('owner+'.Str::random(6).'@example.com', 'Owner', 'Account');
        $member = $this->createUser('member+'.Str::random(6).'@example.com', 'Member', 'Access');
        $organization = $this->createOrganization($owner);
        $ownerRole = $this->ownerRole();
        $workspaceRole = OrganizationRole::create([
            'organization_id' => $organization->id,
            'name' => 'Developer Integrations',
            'description' => 'Developer Integrations',
            'permissions' => ['developer_tools.view'],
        ]);

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'organization_role_id' => $workspaceRole->id,
            'status' => 'active',
            'created_by' => $owner->id,
        ])->delete();

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'organization_role_id' => $workspaceRole->id,
            'status' => 'active',
            'created_by' => $owner->id,
        ])->delete();

        $employee = OrganizationEmployee::create([
            'main_organization_id' => $organization->id,
            'user_id' => $member->id,
            'email' => strtolower((string) $member->email),
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        OrganizationEmployeeAssignment::create([
            'organization_employee_id' => $employee->id,
            'organization_id' => $organization->id,
            'organization_role_id' => $workspaceRole->id,
            'status' => 'active',
            'assigned_by' => $owner->id,
            'assigned_at' => now(),
            'activated_at' => now(),
        ]);

        OrganizationEmployeeAssignment::create([
            'organization_employee_id' => $employee->id,
            'organization_id' => $organization->id,
            'organization_role_id' => $workspaceRole->id,
            'status' => 'active',
            'assigned_by' => $owner->id,
            'assigned_at' => now(),
            'activated_at' => now(),
        ]);

        $request = Request::create('/admin/organizations/'.$organization->uuid, 'GET', ['tab' => 'team']);
        app()->instance('request', $request);

        $payload = app(OrganizationService::class)->getByUuid($request, $organization->uuid);
        $rows = collect($payload['users']->items());

        $this->assertSame(2, $payload['teamSummary']['members_count']);
        $this->assertSame(2, $payload['teamSummary']['hidden_inactive_rows_count']);
        $this->assertSame(1, $payload['teamSummary']['collapsed_assignment_duplicates_count']);
        $this->assertCount(2, $rows);
        $this->assertSame(1, $rows->where('email', $member->email)->count());
        $this->assertSame('Developer Integrations', $rows->firstWhere('email', $member->email)['role']);
        $this->assertSame(2, $payload['profileSummary']['team_members_count']);
    }

    public function test_admin_organization_show_marks_main_shell_as_billing_pending(): void
    {
        $owner = $this->createUser('shell-owner+'.Str::random(6).'@example.com', 'Shell', 'Owner');
        $organization = $this->createOrganization($owner);

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'organization_role_id' => $this->ownerRole()->id,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        $request = Request::create('/admin/organizations/'.$organization->uuid, 'GET');
        app()->instance('request', $request);

        $payload = app(OrganizationService::class)->getByUuid($request, $organization->uuid);

        $this->assertSame('billing_pending', $payload['profileSummary']['subscription']['status']);
        $this->assertSame('Billing setup required', $payload['profileSummary']['subscription']['status_label']);
        $this->assertSame('Not selected yet', $payload['profileSummary']['subscription']['plan_name']);
    }

    private function createUser(string $email, string $firstName, string $lastName): User
    {
        return User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
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
}
