<?php

namespace Tests\Feature;

use App\Models\ContactField;
use App\Models\ContactGroup;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class UserSettingsPermissionBoundaryTest extends TestCase
{
    use CreatesOrganizationContext;
    use RefreshDatabase;

    public function test_custom_role_without_settings_manage_cannot_update_workspace_settings_via_profile_endpoint(): void
    {
        [$user, $organization] = $this->createCustomRoleContext([]);
        $organization = $this->seedWorkspaceIdentity($organization);

        $response = $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->put('/profile/organization', $this->validOrganizationSettingsPayload($organization, [
                'timezone' => $this->secondaryAllowedTimezone(),
            ]));

        $response->assertForbidden();
    }

    public function test_non_owner_with_settings_manage_cannot_change_workspace_identity_via_profile_endpoint(): void
    {
        [$user, $organization] = $this->createCustomRoleContext(['settings.manage']);
        $organization = $this->seedWorkspaceIdentity($organization);

        $response = $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->put('/profile/organization', $this->validOrganizationSettingsPayload($organization, [
                'organization_name' => 'Renamed Workspace '.Str::random(4),
            ]));

        $response->assertForbidden();
    }

    public function test_non_owner_with_settings_manage_can_update_operational_workspace_settings_without_touching_identity(): void
    {
        [$user, $organization] = $this->createCustomRoleContext(['settings.manage']);
        $organization = $this->seedWorkspaceIdentity($organization);

        $response = $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->put('/profile/organization', $this->validOrganizationSettingsPayload($organization, [
                'timezone' => $this->secondaryAllowedTimezone(),
                'enable_campaign_resend' => true,
                'resend_intervals' => [2, 4, 8],
            ]));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status.type', 'success');

        $updatedOrganization = $organization->fresh();
        $metadata = json_decode($updatedOrganization->metadata ?? '{}', true);

        $this->assertSame($organization->name, $updatedOrganization->name);
        $this->assertSame($this->secondaryAllowedTimezone(), $metadata['timezone'] ?? null);
        $this->assertTrue((bool) ($metadata['campaigns']['enable_resend'] ?? false));
        $this->assertSame([2, 4, 8], $metadata['campaigns']['resend_intervals'] ?? null);
    }

    public function test_owner_can_update_workspace_identity_via_profile_endpoint(): void
    {
        [$owner, $organization] = $this->createOwnerContext(withActiveSubscription: true);
        $organization = $this->seedWorkspaceIdentity($organization);

        $response = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->put('/profile/organization', $this->validOrganizationSettingsPayload($organization, [
                'organization_name' => 'Owner Workspace '.Str::random(4),
                'city' => 'Alexandria',
            ]));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $updatedOrganization = $organization->fresh();
        $this->assertStringStartsWith('Owner Workspace ', $updatedOrganization->name);
        $this->assertSame('Alexandria', json_decode($updatedOrganization->address ?? '{}', true)['city'] ?? null);
    }

    public function test_profile_endpoint_rejects_failed_campaign_group_from_another_workspace(): void
    {
        [$user, $organization] = $this->createCustomRoleContext(['settings.manage']);
        $organization = $this->seedWorkspaceIdentity($organization);
        [, $otherOrganization] = $this->createOwnerContext();

        $foreignGroup = ContactGroup::create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Foreign Group '.Str::random(4),
            'created_by' => $otherOrganization->created_by,
        ]);

        $response = $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->from('/settings')
            ->put('/profile/organization', $this->validOrganizationSettingsPayload($organization, [
                'enable_campaign_resend' => true,
                'move_failed_contacts_to_group' => true,
                'resend_intervals' => [1, 3, 6],
                'failed_campaign_group' => $foreignGroup->uuid,
            ]));

        $response->assertRedirect('/settings');
        $response->assertSessionHasErrors('failed_campaign_group');
    }

    public function test_non_owner_cannot_access_team_workspace_even_with_settings_manage_permission(): void
    {
        [$user, $organization, $role] = $this->createCustomRoleContext(['settings.manage']);

        $member = $this->createTeamMember($organization, $role);

        $requests = [
            ['method' => 'get', 'uri' => '/settings/team', 'payload' => []],
            ['method' => 'post', 'uri' => '/settings/team/invite', 'payload' => [
                'email' => 'invite+'.Str::random(8).'@example.com',
                'organization_role_id' => $role->id,
            ]],
            ['method' => 'put', 'uri' => '/settings/team/'.$member->uuid, 'payload' => [
                'organization_role_id' => $role->id,
            ]],
            ['method' => 'delete', 'uri' => '/settings/team/'.$member->uuid, 'payload' => []],
        ];

        foreach ($requests as $request) {
            $response = $this->actingAs($user, 'user')
                ->withSession(['current_organization' => $organization->id])
                ->{$request['method']}($request['uri'], $request['payload']);

            $response->assertForbidden();
        }
    }

    public function test_non_owner_cannot_fetch_team_role_options_even_with_settings_manage_permission(): void
    {
        [$user, $organization] = $this->createCustomRoleContext(['settings.manage']);

        $response = $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/settings/team/roles/select');

        $response->assertForbidden();
    }

    public function test_custom_role_without_settings_manage_cannot_manage_contact_fields(): void
    {
        [$user, $organization] = $this->createCustomRoleContext([]);

        $field = $this->createContactField($organization);

        $requests = [
            ['method' => 'get', 'uri' => '/contact-fields/'.$field->uuid, 'payload' => []],
            ['method' => 'post', 'uri' => '/contact-fields', 'payload' => [
                'name' => 'Status '.Str::random(4),
                'component' => 'input',
                'type' => 'text',
                'required' => false,
            ]],
            ['method' => 'put', 'uri' => '/contact-fields/'.$field->uuid, 'payload' => [
                'name' => 'Updated '.Str::random(4),
                'component' => 'input',
                'type' => 'text',
                'required' => false,
            ]],
            ['method' => 'delete', 'uri' => '/contact-fields/'.$field->uuid, 'payload' => []],
            ['method' => 'post', 'uri' => '/contact-fields/update-positions', 'payload' => [
                'orderedIds' => [$field->uuid],
            ]],
        ];

        foreach ($requests as $request) {
            $response = $this->actingAs($user, 'user')
                ->withSession(['current_organization' => $organization->id])
                ->{$request['method']}($request['uri'], $request['payload']);

            $response->assertRedirect(route('dashboard'));
        }
    }

    public function test_custom_role_with_settings_manage_can_fetch_contact_field_details(): void
    {
        [$user, $organization] = $this->createCustomRoleContext(['settings.manage']);
        $field = $this->createContactField($organization);

        $response = $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/contact-fields/'.$field->uuid);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('item.uuid', (string) $field->uuid);
    }

    public function test_owner_can_access_team_workspace_and_role_options(): void
    {
        [$owner, $organization] = $this->createOwnerContext(withActiveSubscription: true);

        $teamResponse = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/settings/team');

        $teamResponse->assertOk();
        $teamResponse->assertInertia(fn (Assert $page) => $page
            ->component('User/Team/Index')
            ->where('title', 'Team')
        );

        $roleResponse = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/settings/team/roles/select');

        $roleResponse->assertOk()
            ->assertJsonStructure([
                'roles' => [
                    '*' => ['id', 'uuid', 'name', 'organization_id'],
                ],
            ]);
    }

    public function test_parent_owner_can_access_branch_team_workspace_without_direct_branch_membership(): void
    {
        [$owner, $parentOrganization] = $this->createOwnerContext(withActiveSubscription: true);

        $branchOrganization = Organization::create([
            'identifier' => 'branch-'.Str::lower(Str::random(8)),
            'name' => 'Branch '.Str::random(5),
            'created_by' => $owner->id,
            'organization_type' => 'branch',
            'parent_organization_id' => $parentOrganization->id,
            'metadata' => json_encode([]),
        ]);

        $branchRole = OrganizationRole::create([
            'organization_id' => $branchOrganization->id,
            'name' => 'Branch Manager '.Str::random(4),
            'description' => 'Local branch role',
            'permissions' => ['contacts.view_all', 'settings.manage'],
        ]);

        $teamResponse = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $branchOrganization->id])
            ->get('/settings/team');

        $teamResponse->assertOk();
        $teamResponse->assertInertia(fn (Assert $page) => $page
            ->component('User/Team/Index')
            ->where('workspaceAccess.isOwner', true)
            ->where('workspaceAccess.hasDirectMembership', false)
            ->where('workspaceAccess.authoritySource', 'inherited_parent_owner')
        );

        $roleResponse = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $branchOrganization->id])
            ->get('/settings/team/roles/select');

        $roleResponse->assertOk()
            ->assertJsonCount(1, 'roles')
            ->assertJsonPath('roles.0.id', $branchRole->id)
            ->assertJsonPath('roles.0.organization_id', $branchOrganization->id);
    }

    public function test_branch_general_settings_expose_parent_managed_company_profile_context(): void
    {
        [$owner, $parentOrganization] = $this->createOwnerContext(withActiveSubscription: true);
        $parentOrganization = $this->seedWorkspaceIdentity($parentOrganization);

        $branchOrganization = Organization::create([
            'identifier' => 'branch-'.Str::lower(Str::random(8)),
            'name' => 'Branch Settings '.Str::random(4),
            'created_by' => $owner->id,
            'organization_type' => 'branch',
            'parent_organization_id' => $parentOrganization->id,
            'metadata' => json_encode([]),
        ]);

        $branchOrganization = $this->seedWorkspaceIdentity($branchOrganization);

        $response = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $branchOrganization->id])
            ->get('/settings');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/Settings/General')
            ->where('workspaceAccess.isOwner', true)
            ->where('workspaceAccess.hasDirectMembership', false)
            ->where('workspaceAccess.canManageTeam', false)
            ->where('workspaceAccess.canManageRoles', false)
            ->where('workspaceAccess.companyProfileManagedByParent', true)
            ->where('organizationProfileContext.companyProfileManagedByParent', true)
            ->where('organizationProfileContext.companyProfile.name', $parentOrganization->name)
            ->where('organizationProfileContext.branchWorkspace.name', $branchOrganization->name)
        );
    }

    public function test_branch_settings_update_keeps_branch_identity_frozen_while_saving_operational_settings(): void
    {
        [$owner, $parentOrganization] = $this->createOwnerContext(withActiveSubscription: true);
        $parentOrganization = $this->seedWorkspaceIdentity($parentOrganization);

        $branchOrganization = Organization::create([
            'identifier' => 'branch-'.Str::lower(Str::random(8)),
            'name' => 'Branch Immutable '.Str::random(4),
            'created_by' => $owner->id,
            'organization_type' => 'branch',
            'parent_organization_id' => $parentOrganization->id,
            'metadata' => json_encode([]),
        ]);

        $branchOrganization = $this->seedWorkspaceIdentity($branchOrganization);
        $originalBranchName = $branchOrganization->name;
        $originalBranchAddress = json_decode($branchOrganization->address ?? '{}', true);

        $response = $this->actingAs($owner, 'user')
            ->withSession(['current_organization' => $branchOrganization->id])
            ->put('/profile/organization', $this->validOrganizationSettingsPayload($branchOrganization, [
                'organization_name' => 'Tampered Branch '.Str::random(4),
                'city' => 'Alexandria',
                'timezone' => $this->secondaryAllowedTimezone(),
                'enable_campaign_resend' => true,
                'resend_intervals' => [2, 4, 8],
            ]));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status.type', 'success');

        $updatedBranch = $branchOrganization->fresh();
        $updatedMetadata = json_decode($updatedBranch->metadata ?? '{}', true);

        $this->assertSame($originalBranchName, $updatedBranch->name);
        $this->assertSame($originalBranchAddress, json_decode($updatedBranch->address ?? '{}', true));
        $this->assertSame($this->secondaryAllowedTimezone(), $updatedMetadata['timezone'] ?? null);
        $this->assertTrue((bool) ($updatedMetadata['campaigns']['enable_resend'] ?? false));
        $this->assertSame([2, 4, 8], $updatedMetadata['campaigns']['resend_intervals'] ?? null);
    }

    private function createCustomRoleContext(array $permissions): array
    {
        $user = User::create([
            'first_name' => 'Settings',
            'last_name' => 'User',
            'email' => 'settings+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $organization = Organization::create([
            'identifier' => 'org-'.Str::lower(Str::random(8)),
            'name' => 'Settings Org '.Str::random(5),
            'created_by' => $user->id,
            'metadata' => json_encode([]),
        ]);

        $role = OrganizationRole::create([
            'organization_id' => $organization->id,
            'name' => 'Operations '.Str::random(4),
            'description' => 'Custom role for settings boundary checks',
            'permissions' => $permissions,
        ]);

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'organization_role_id' => $role->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $this->createActiveSubscription($organization->id, [
            'team_limit' => 10,
            'branches_limit' => 3,
            'campaign_limit' => 10,
            'message_limit' => 100,
            'contacts_limit' => 100,
            'canned_replies_limit' => 10,
            'addons' => [],
        ]);

        return [$user, $organization, $role];
    }

    private function createTeamMember(Organization $organization, OrganizationRole $role): Team
    {
        $member = User::create([
            'first_name' => 'Member',
            'last_name' => 'User',
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

    private function createContactField(Organization $organization): ContactField
    {
        return ContactField::create([
            'organization_id' => $organization->id,
            'name' => 'Preference '.Str::random(4),
            'type' => 'input',
            'value' => 'text',
            'position' => 1,
            'required' => false,
        ]);
    }

    private function seedWorkspaceIdentity(Organization $organization): Organization
    {
        $organization->forceFill([
            'name' => 'Settings Workspace '.Str::random(4),
            'address' => json_encode([
                'street' => '10 Main Street',
                'city' => 'Cairo',
                'state' => 'Cairo',
                'zip' => '11511',
                'country' => $this->primaryAllowedCountry(),
            ]),
            'metadata' => json_encode([
                'timezone' => $this->primaryAllowedTimezone(),
                'campaigns' => [
                    'enable_resend' => false,
                    'move_failed_contacts_to_group' => false,
                    'resend_intervals' => [1, 3, 6],
                    'failed_campaign_group' => null,
                ],
            ]),
        ])->save();

        return $organization->fresh();
    }

    private function validOrganizationSettingsPayload(Organization $organization, array $overrides = []): array
    {
        $address = json_decode($organization->address ?? '{}', true);
        $metadata = json_decode($organization->metadata ?? '{}', true);

        return array_merge([
            'organization_name' => $organization->name,
            'address' => $address['street'] ?? '10 Main Street',
            'city' => $address['city'] ?? 'Cairo',
            'state' => $address['state'] ?? 'Cairo',
            'zip' => $address['zip'] ?? '11511',
            'country' => $address['country'] ?? $this->primaryAllowedCountry(),
            'timezone' => $metadata['timezone'] ?? $this->primaryAllowedTimezone(),
            'enable_campaign_resend' => (bool) ($metadata['campaigns']['enable_resend'] ?? false),
            'move_failed_contacts_to_group' => (bool) ($metadata['campaigns']['move_failed_contacts_to_group'] ?? false),
            'resend_intervals' => $metadata['campaigns']['resend_intervals'] ?? [1, 3, 6],
            'failed_campaign_group' => $metadata['campaigns']['failed_campaign_group'] ?? null,
        ], $overrides);
    }

    private function primaryAllowedCountry(): string
    {
        return (string) (collect(config('formats.countries', []))->pluck('value')->filter()->first() ?? 'Saudi Arabia');
    }

    private function primaryAllowedTimezone(): string
    {
        return (string) (collect(config('formats.timezones', []))->pluck('value')->filter()->first() ?? 'Asia/Riyadh');
    }

    private function secondaryAllowedTimezone(): string
    {
        return (string) (collect(config('formats.timezones', []))->pluck('value')->filter()->skip(1)->first()
            ?? $this->primaryAllowedTimezone());
    }
}
