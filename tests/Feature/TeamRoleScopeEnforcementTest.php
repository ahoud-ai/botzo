<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Team;
use App\Models\TeamInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class TeamRoleScopeEnforcementTest extends TestCase
{
    use CreatesOrganizationContext;
    use RefreshDatabase;

    public function test_invite_rejects_role_from_another_organization(): void
    {
        [$ownerA, $organizationA] = $this->createOwnerContext(withActiveSubscription: true);
        [, $organizationB] = $this->createOwnerContext(withActiveSubscription: true);

        $foreignRole = $this->createOrganizationRole($organizationB, 'Foreign Role');

        $response = $this->actingAs($ownerA, 'user')
            ->withSession(['current_organization' => $organizationA->id])
            ->post('/settings/team/invite', [
                'email' => 'invite+'.Str::random(8).'@example.com',
                'organization_role_id' => $foreignRole->id,
            ]);

        $response->assertSessionHasErrors('organization_role_id');

        $this->assertFalse(
            TeamInvite::where('organization_role_id', $foreignRole->id)->exists()
        );
    }

    public function test_update_cannot_modify_team_member_from_another_organization(): void
    {
        [$ownerA, $organizationA] = $this->createOwnerContext(withActiveSubscription: true);
        [$ownerB, $organizationB] = $this->createOwnerContext(withActiveSubscription: true);

        $roleA = $this->createOrganizationRole($organizationA, 'Ops A');
        $roleB = $this->createOrganizationRole($organizationB, 'Ops B');

        $memberB = $this->createTeamMember($organizationB, $roleB);

        $response = $this->actingAs($ownerA, 'user')
            ->withSession(['current_organization' => $organizationA->id])
            ->put('/settings/team/'.$memberB->uuid, [
                'organization_role_id' => $roleA->id,
            ]);

        $response->assertStatus(302);

        $this->assertDatabaseHas('teams', [
            'id' => $memberB->id,
            'organization_id' => $organizationB->id,
            'organization_role_id' => $roleB->id,
            'deleted_at' => null,
        ]);
    }

    public function test_delete_cannot_remove_team_member_from_another_organization(): void
    {
        [$ownerA, $organizationA] = $this->createOwnerContext(withActiveSubscription: true);
        [, $organizationB] = $this->createOwnerContext(withActiveSubscription: true);

        $roleB = $this->createOrganizationRole($organizationB, 'Ops Delete');
        $memberB = $this->createTeamMember($organizationB, $roleB);

        $response = $this->actingAs($ownerA, 'user')
            ->withSession(['current_organization' => $organizationA->id])
            ->delete('/settings/team/'.$memberB->uuid);

        $response->assertStatus(302);

        $this->assertDatabaseHas('teams', [
            'id' => $memberB->id,
            'organization_id' => $organizationB->id,
            'deleted_at' => null,
        ]);
    }

    public function test_invite_rejects_universal_owner_role_assignment(): void
    {
        [$owner, $organization] = $this->createOwnerContext(withActiveSubscription: true);
        $ownerRole = OrganizationRole::query()
            ->whereNull('organization_id')
            ->where('name', 'Owner')
            ->firstOrFail();

        $response = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/settings/team/invite', [
                'email' => 'owner-invite+'.Str::random(8).'@example.com',
                'organization_role_id' => $ownerRole->id,
            ]);

        $response->assertSessionHasErrors('organization_role_id');

        $this->assertFalse(
            TeamInvite::where('organization_id', $organization->id)
                ->where('organization_role_id', $ownerRole->id)
                ->exists()
        );
    }

    private function createOrganizationRole(Organization $organization, string $name): OrganizationRole
    {
        return OrganizationRole::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'description' => $name.' description',
            'permissions' => [],
        ]);
    }

    private function createTeamMember(Organization $organization, OrganizationRole $role): Team
    {
        $member = User::create([
            'first_name' => 'Member',
            'last_name' => Str::random(5),
            'email' => 'member+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        return Team::create([
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'organization_role_id' => $role->id,
            'status' => 'active',
            'created_by' => $organization->created_by,
        ]);
    }
}
