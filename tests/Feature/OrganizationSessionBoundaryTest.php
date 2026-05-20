<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganizationSessionBoundaryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_cannot_switch_to_organization_outside_his_membership(): void
    {
        $user = $this->createUser();
        $ownOrganization = $this->createOrganization($user, ['name' => 'Own Workspace']);
        $foreignOrganization = $this->createOrganization($user, ['name' => 'Foreign Workspace']);

        $this->attachUserToOrganization($user, $ownOrganization);

        $response = $this->actingAs($user)
            ->withSession(['current_organization' => $ownOrganization->id])
            ->post('/select-organization', [
                'uuid' => $foreignOrganization->uuid,
            ]);

        $response->assertRedirect(route('user.organization.index'));
        $response->assertSessionHas('current_organization', $ownOrganization->id);
    }

    public function test_invalid_current_organization_session_redirects_dashboard_and_clears_session(): void
    {
        $user = $this->createUser();
        $ownOrganization = $this->createOrganization($user, ['name' => 'Own Workspace']);
        $foreignOrganization = $this->createOrganization($user, ['name' => 'Foreign Workspace']);

        $this->attachUserToOrganization($user, $ownOrganization);

        $response = $this->actingAs($user)
            ->withSession(['current_organization' => $foreignOrganization->id])
            ->get('/dashboard');

        $response->assertRedirect(route('user.organization.index'));
        $response->assertSessionMissing('current_organization');
    }

    public function test_select_organization_page_clears_stale_session_and_lists_only_accessible_workspaces(): void
    {
        $user = $this->createUser();
        $ownOrganization = $this->createOrganization($user, ['name' => 'Own Workspace']);
        $foreignOrganization = $this->createOrganization($user, ['name' => 'Foreign Workspace']);

        $this->attachUserToOrganization($user, $ownOrganization);

        $response = $this->actingAs($user)
            ->withSession(['current_organization' => $foreignOrganization->id])
            ->get('/select-organization');

        $response->assertOk();
        $response->assertSessionMissing('current_organization');
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/OrganizationSelect')
            ->has('organizations', 1)
            ->where('organizations.0.organization.uuid', (string) $ownOrganization->uuid)
            ->where('organizations.0.organization.name', 'Own Workspace')
        );
    }

    public function test_parent_owner_can_switch_to_branch_workspace_without_direct_branch_membership(): void
    {
        $user = $this->createUser();
        $parentOrganization = $this->createOrganization($user, ['name' => 'Parent Workspace']);
        $branchOrganization = $this->createOrganization($user, [
            'name' => 'Branch Workspace',
            'organization_type' => 'branch',
            'parent_organization_id' => $parentOrganization->id,
        ]);

        $this->attachUserToOrganization($user, $parentOrganization);

        $response = $this->actingAs($user)
            ->withSession(['current_organization' => $parentOrganization->id])
            ->post('/select-organization', [
                'uuid' => $branchOrganization->uuid,
            ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('current_organization', $branchOrganization->id);
    }

    public function test_select_organization_page_lists_branch_access_inherited_from_parent_owner(): void
    {
        $user = $this->createUser();
        $parentOrganization = $this->createOrganization($user, ['name' => 'B Parent Workspace']);
        $branchOrganization = $this->createOrganization($user, [
            'name' => 'A Branch Workspace',
            'organization_type' => 'branch',
            'parent_organization_id' => $parentOrganization->id,
        ]);

        $this->attachUserToOrganization($user, $parentOrganization);

        $response = $this->actingAs($user)
            ->withSession(['current_organization' => $parentOrganization->id])
            ->get('/select-organization');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/OrganizationSelect')
            ->has('organizations', 2)
            ->where('organizations.0.organization.uuid', (string) $branchOrganization->uuid)
            ->where('organizations.0.organization.organization_type', 'branch')
            ->where('organizations.0.organization.parent_organization.name', 'B Parent Workspace')
            ->where('organizations.0.access.source', 'inherited_parent_owner')
            ->where('organizations.1.organization.uuid', (string) $parentOrganization->uuid)
            ->where('organizations.1.access.source', 'direct')
        );
    }

    private function createUser(): User
    {
        return User::create([
            'first_name' => 'Session',
            'last_name' => 'User',
            'email' => 'session+'.Str::random(8).'@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);
    }

    private function createOrganization(User $creator, array $attributes = []): Organization
    {
        return Organization::create(array_merge([
            'name' => 'Organization '.Str::random(4),
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => $creator->id,
            'organization_type' => 'main',
            'parent_organization_id' => null,
            'metadata' => json_encode([
                'addons' => [],
            ]),
        ], $attributes));
    }

    private function attachUserToOrganization(User $user, Organization $organization): void
    {
        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'organization_role_id' => $this->ownerRole()->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);
    }

    private function ownerRole(): OrganizationRole
    {
        return OrganizationRole::firstOrCreate(
            [
                'organization_id' => null,
                'name' => 'Owner',
            ],
            [
                'description' => 'Owner role for organization session boundary tests',
                'permissions' => ['*'],
            ]
        );
    }
}
