<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthOrganizationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_without_organization_name_redirects_directly_to_workspace_selection(): void
    {
        Setting::updateOrCreate(['key' => 'smtp_email_active'], ['value' => '0']);

        $response = $this->post('/signup', [
            'first_name' => 'Signup',
            'last_name' => 'User',
            'email' => 'signup+'.Str::random(8).'@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('user.organization.index'));
        $response->assertSessionMissing('current_organization');
        $this->assertAuthenticated('user');
    }

    public function test_signup_with_organization_name_creates_workspace_shell_without_auto_subscription(): void
    {
        Setting::updateOrCreate(['key' => 'smtp_email_active'], ['value' => '0']);
        Setting::updateOrCreate(['key' => 'trial_period'], ['value' => '0']);

        $plan = SubscriptionPlan::create([
            'name' => 'Starter Plan '.Str::random(4),
            'price' => 49.00,
            'period' => 'monthly',
            'metadata' => json_encode([
                'tier_rank' => 1,
                'branches_limit' => 1,
                'addons' => [],
            ]),
            'status' => 'active',
        ]);

        $response = $this->post('/signup', [
            'first_name' => 'Signup',
            'last_name' => 'Owner',
            'organization_name' => 'Signup Workspace',
            'email' => 'signup-org+'.Str::random(8).'@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');

        $organization = Organization::where('name', 'Signup Workspace')->first();

        $this->assertNotNull($organization);
        $this->assertDatabaseMissing('subscriptions', [
            'organization_id' => $organization->id,
        ]);
        $this->assertDatabaseHas('teams', [
            'organization_id' => $organization->id,
        ]);
    }

    public function test_first_workspace_created_from_selection_page_does_not_auto_provision_subscription(): void
    {
        Setting::updateOrCreate(['key' => 'smtp_email_active'], ['value' => '0']);
        Setting::updateOrCreate(['key' => 'trial_period'], ['value' => '0']);

        $user = $this->createUser('first-workspace+'.Str::random(8).'@example.com');

        SubscriptionPlan::create([
            'name' => 'Starter Plan '.Str::random(4),
            'price' => 49.00,
            'period' => 'monthly',
            'metadata' => json_encode([
                'tier_rank' => 1,
                'branches_limit' => 1,
                'addons' => [],
            ]),
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->post('/organization', [
                'name' => 'Shell Workspace',
            ]);

        $response->assertRedirect('/dashboard');

        $organization = Organization::where('name', 'Shell Workspace')->first();
        $this->assertNotNull($organization);
        $this->assertDatabaseMissing('subscriptions', [
            'organization_id' => $organization->id,
        ]);
        $this->assertDatabaseHas('teams', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_login_with_single_workspace_sets_current_organization(): void
    {
        $user = $this->createUser('single+'.Str::random(8).'@example.com');
        $organization = $this->createOrganization($user, 'Single Workspace');
        $this->attachUserToOrganization($user, $organization);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('current_organization', $organization->id);
    }

    public function test_login_with_multiple_workspaces_clears_current_organization_and_requires_selection(): void
    {
        $user = $this->createUser('multi+'.Str::random(8).'@example.com');
        $firstOrganization = $this->createOrganization($user, 'First Workspace');
        $secondOrganization = $this->createOrganization($user, 'Second Workspace');
        $this->attachUserToOrganization($user, $firstOrganization);
        $this->attachUserToOrganization($user, $secondOrganization);

        $response = $this->withSession(['current_organization' => $firstOrganization->id])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password123',
            ]);

        $response->assertRedirect(route('user.organization.index'));
        $response->assertSessionMissing('current_organization');
    }

    public function test_login_with_no_workspace_redirects_directly_to_workspace_selection(): void
    {
        $user = $this->createUser('no-workspace+'.Str::random(8).'@example.com');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('user.organization.index'));
        $response->assertSessionMissing('current_organization');
    }

    public function test_select_organization_exposes_branch_creation_context_with_blocking_reason_when_parent_subscription_is_inactive(): void
    {
        $user = $this->createUser('branch-context+'.Str::random(8).'@example.com');
        $parentOrganization = $this->createOrganization($user, 'Parent Workspace');
        $this->attachUserToOrganization($user, $parentOrganization);

        $plan = SubscriptionPlan::create([
            'name' => 'Starter Plan '.Str::random(4),
            'price' => 49.00,
            'period' => 'monthly',
            'metadata' => json_encode([
                'tier_rank' => 1,
                'branches_limit' => 1,
                'addons' => [],
            ]),
            'status' => 'active',
        ]);

        Subscription::create([
            'organization_id' => $parentOrganization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subMonth(),
            'valid_until' => now()->subMinute(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_organization' => $parentOrganization->id])
            ->get('/select-organization');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/OrganizationSelect')
            ->where('branchCreationContext.type', 'branch')
            ->where('branchCreationContext.parentOrganization.uuid', (string) $parentOrganization->uuid)
            ->where('branchCreationContext.canCreateBranch', false)
            ->where('branchCreationContext.blockingCode', 'subscription_inactive')
            ->where(
                'branchCreationContext.blockingMessage',
                __('The parent organization subscription is inactive. Please renew or upgrade your plan before creating a new branch.')
            )
            ->where('branchCreationContext.limitSnapshot.used', 1)
            ->where('branchCreationContext.limitSnapshot.limit', 1)
            ->where('branchCreationContext.limitSnapshot.remaining', 0)
        );
    }

    public function test_dashboard_exposes_shared_branch_creation_context_for_menu_when_branch_limit_is_reached(): void
    {
        $user = $this->createUser('branch-menu-context+'.Str::random(8).'@example.com');
        $parentOrganization = $this->createOrganization($user, 'Parent Workspace');
        $this->attachUserToOrganization($user, $parentOrganization);

        Organization::create([
            'name' => 'Existing Branch',
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => $user->id,
            'organization_type' => 'branch',
            'parent_organization_id' => $parentOrganization->id,
            'metadata' => json_encode([
                'addons' => [],
            ]),
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Growth Plan '.Str::random(4),
            'price' => 89.00,
            'period' => 'monthly',
            'metadata' => json_encode([
                'tier_rank' => 2,
                'branches_limit' => 1,
                'addons' => [],
            ]),
            'status' => 'active',
        ]);

        Subscription::create([
            'organization_id' => $parentOrganization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_organization' => $parentOrganization->id])
            ->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/Dashboard')
            ->where('branchCreationContext.type', 'branch')
            ->where('branchCreationContext.parentOrganization.uuid', (string) $parentOrganization->uuid)
            ->where('branchCreationContext.canCreateBranch', false)
            ->where('branchCreationContext.blockingCode', 'branch_limit_reached')
            ->where(
                'branchCreationContext.blockingMessage',
                __('Branch limit reached for this organization plan. Please upgrade your plan or contact support to add more branches.')
            )
            ->where('branchCreationContext.limitSnapshot.used', 2)
            ->where('branchCreationContext.limitSnapshot.limit', 1)
            ->where('branchCreationContext.limitSnapshot.remaining', 0)
        );
    }

    public function test_dashboard_shared_props_expose_inherited_branch_access_for_parent_owner(): void
    {
        $user = $this->createUser('branch-shared-props+'.Str::random(8).'@example.com');
        $parentOrganization = $this->createOrganization($user, 'Parent Workspace');
        $branchOrganization = Organization::create([
            'name' => 'Branch Workspace',
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => $user->id,
            'organization_type' => 'branch',
            'parent_organization_id' => $parentOrganization->id,
            'metadata' => json_encode([
                'addons' => [],
            ]),
        ]);

        $this->attachUserToOrganization($user, $parentOrganization);

        $response = $this->actingAs($user)
            ->withSession(['current_organization' => $branchOrganization->id])
            ->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/Dashboard')
            ->where('workspaceAccess.isOwner', true)
            ->where('workspaceAccess.hasDirectMembership', false)
            ->where('workspaceAccess.authoritySource', 'inherited_parent_owner')
            ->where('workspaceAccess.canManageTeam', false)
            ->where('workspaceAccess.canManageRoles', false)
            ->where('workspaceAccess.companyProfileManagedByParent', true)
            ->has('organizations', 2)
            ->where('organizations.0.organization.uuid', (string) $branchOrganization->uuid)
            ->where('organizations.0.access.source', 'inherited_parent_owner')
            ->where('organizations.1.organization.uuid', (string) $parentOrganization->uuid)
            ->where('organizations.1.access.source', 'direct')
        );
    }

    private function createUser(string $email): User
    {
        return User::create([
            'first_name' => 'Auth',
            'last_name' => 'User',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'user',
            'status' => 1,
            'language' => 'en',
            'email_verified_at' => now(),
        ]);
    }

    private function createOrganization(User $creator, string $name): Organization
    {
        return Organization::create([
            'name' => $name,
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => $creator->id,
            'organization_type' => 'main',
            'parent_organization_id' => null,
            'metadata' => json_encode([
                'addons' => [],
            ]),
        ]);
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
                'description' => 'Owner role for auth organization flow tests',
                'permissions' => ['*'],
            ]
        );
    }
}
