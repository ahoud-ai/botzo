<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class OrganizationRoleCountScopeTest extends TestCase
{
    use CreatesOrganizationContext;
    use RefreshDatabase;

    public function test_owner_role_member_count_is_scoped_to_current_workspace(): void
    {
        [$owner, $organization, $ownerRole] = $this->createOwnerContext(withActiveSubscription: true);

        $branch = Organization::create([
            'identifier' => 'branch-'.Str::lower(Str::random(8)),
            'name' => 'Branch '.Str::random(4),
            'created_by' => $owner->id,
            'organization_type' => 'branch',
            'parent_organization_id' => $organization->id,
            'metadata' => json_encode([]),
        ]);

        Team::create([
            'organization_id' => $branch->id,
            'user_id' => $owner->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/settings/team/roles');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/Role/Index')
            ->where('rows.data', function ($rows) {
                $ownerRole = collect($rows)->firstWhere('name', 'Owner');

                return $ownerRole !== null
                    && (int) ($ownerRole['teams_count'] ?? 0) === 1;
            })
        );
    }
}
