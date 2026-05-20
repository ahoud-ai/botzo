<?php

namespace Tests\Feature;

use App\Helpers\CustomHelper;
use App\Models\Addon;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use App\Services\BillingService;
use App\Services\OrganizationService;
use App\Services\SubscriptionPlanLimitService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrganizationBranchSubscriptionInheritanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_branch_inherits_parent_subscription_limits_and_addons(): void
    {
        $admin = $this->createUser('owner');
        $parent = $this->createOrganization($admin, [
            'name' => 'Main Organization',
        ]);
        $branch = $this->createOrganization($admin, [
            'name' => 'Main Branch',
            'organization_type' => 'branch',
            'parent_organization_id' => $parent->id,
        ]);

        $plan = $this->createPlan([
            'campaign_limit' => 7,
            'addons' => [
                'Flow builder' => 1,
            ],
        ]);

        Subscription::create([
            'organization_id' => $parent->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        Addon::create([
            'category' => 'automation',
            'name' => 'Flow builder',
            'logo' => 'flow-builder.svg',
            'status' => 1,
            'is_active' => 1,
            'is_plan_restricted' => 1,
        ]);

        $planLimitService = app(SubscriptionPlanLimitService::class);

        $this->assertSame(7, $planLimitService->limitForOrganization($branch->id, 'campaign_limit'));
        $this->assertTrue(CustomHelper::isModuleEnabled('Flow builder', $branch->id));
    }

    public function test_creating_branch_does_not_create_standalone_subscription(): void
    {
        $admin = $this->createUser('owner');
        $ownerRole = $this->ownerRole();

        $parent = $this->createOrganization($admin, [
            'name' => 'Parent Organization',
        ]);

        Team::create([
            'organization_id' => $parent->id,
            'user_id' => $admin->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $plan = $this->createPlan([
            'branches_limit' => 3,
            'addons' => [],
        ]);

        Subscription::create([
            'organization_id' => $parent->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $this->actingAs($admin);

        $request = Request::create('/admin/organizations', 'POST', [
            'name' => 'Inherited Branch',
            'organization_type' => 'branch',
            'parent_organization_uuid' => $parent->uuid,
            'create_user' => 1,
            'first_name' => 'Branch',
            'last_name' => 'Owner',
            'email' => 'branch+'.Str::random(8).'@example.com',
            'phone' => null,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'street' => null,
            'city' => null,
            'state' => null,
            'zip' => null,
            'country' => null,
        ]);

        $organization = app(OrganizationService::class)->store($request);

        $this->assertSame('branch', $organization->organization_type);
        $this->assertSame($parent->id, $organization->parent_organization_id);
        $this->assertDatabaseMissing('subscriptions', [
            'organization_id' => $organization->id,
        ]);
    }

    public function test_creating_branch_is_blocked_when_parent_subscription_is_inactive(): void
    {
        $admin = $this->createUser('owner');
        $ownerRole = $this->ownerRole();

        $parent = $this->createOrganization($admin, [
            'name' => 'Inactive Parent Organization',
        ]);

        Team::create([
            'organization_id' => $parent->id,
            'user_id' => $admin->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $plan = $this->createPlan([
            'branches_limit' => 3,
            'addons' => [],
        ]);

        Subscription::create([
            'organization_id' => $parent->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subMonths(2),
            'valid_until' => now()->subMinute(),
        ]);

        $this->actingAs($admin);

        $request = Request::create('/admin/organizations', 'POST', [
            'name' => 'Blocked Branch',
            'organization_type' => 'branch',
            'parent_organization_uuid' => $parent->uuid,
            'create_user' => 1,
            'first_name' => 'Branch',
            'last_name' => 'Owner',
            'email' => 'blocked+'.Str::random(8).'@example.com',
            'phone' => null,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'street' => null,
            'city' => null,
            'state' => null,
            'zip' => null,
            'country' => null,
        ]);

        try {
            app(OrganizationService::class)->store($request);
            $this->fail('Branch creation should be blocked when parent subscription is inactive.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                __('The parent organization subscription is inactive. Please renew or upgrade your plan before creating a new branch.'),
                $exception->errors()['parent_organization_uuid'][0] ?? null
            );
        }
    }

    public function test_creating_branch_is_blocked_when_branch_limit_is_reached(): void
    {
        $admin = $this->createUser('owner');
        $ownerRole = $this->ownerRole();

        $parent = $this->createOrganization($admin, [
            'name' => 'Limited Parent Organization',
        ]);

        Team::create([
            'organization_id' => $parent->id,
            'user_id' => $admin->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $plan = $this->createPlan([
            'branches_limit' => 1,
            'addons' => [],
        ]);

        Subscription::create([
            'organization_id' => $parent->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $this->createOrganization($admin, [
            'name' => 'Existing Branch',
            'organization_type' => 'branch',
            'parent_organization_id' => $parent->id,
        ]);

        $this->actingAs($admin);

        $request = Request::create('/admin/organizations', 'POST', [
            'name' => 'Second Branch',
            'organization_type' => 'branch',
            'parent_organization_uuid' => $parent->uuid,
            'create_user' => 1,
            'first_name' => 'Branch',
            'last_name' => 'Owner',
            'email' => 'limited+'.Str::random(8).'@example.com',
            'phone' => null,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'street' => null,
            'city' => null,
            'state' => null,
            'zip' => null,
            'country' => null,
        ]);

        try {
            app(OrganizationService::class)->store($request);
            $this->fail('Branch creation should be blocked when branch limit is reached.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                __('Branch limit reached for this organization plan. Please upgrade your plan or contact support to add more branches.'),
                $exception->errors()['parent_organization_uuid'][0] ?? null
            );
        }
    }

    public function test_admin_cannot_create_direct_billing_transaction_for_branch(): void
    {
        $admin = $this->createUser('owner');
        $parent = $this->createOrganization($admin, [
            'name' => 'Billing Owner Organization',
        ]);
        $branch = $this->createOrganization($admin, [
            'name' => 'Billing Branch',
            'organization_type' => 'branch',
            'parent_organization_id' => $parent->id,
        ]);

        $this->actingAs($admin);

        $request = Request::create('/admin/billing', 'POST', [
            'uuid' => $branch->uuid,
            'type' => 'credit',
            'amount' => 50,
            'description' => 'Manual credit',
        ]);

        $this->expectException(ValidationException::class);

        try {
            app(BillingService::class)->store($request);
        } finally {
            $this->assertDatabaseMissing('billing_transactions', [
                'organization_id' => $branch->id,
                'description' => 'Manual credit',
            ]);
        }
    }

    public function test_user_workspace_creation_from_current_organization_creates_branch(): void
    {
        $owner = $this->createUser('user');
        $parent = $this->createOrganization($owner, [
            'name' => 'Workspace Parent',
        ]);

        Team::create([
            'organization_id' => $parent->id,
            'user_id' => $owner->id,
            'organization_role_id' => $this->ownerRole()->id,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        $plan = $this->createPlan([
            'branches_limit' => 3,
            'addons' => [],
        ]);

        Subscription::create([
            'organization_id' => $parent->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_organization' => $parent->id])
            ->post('/organization', [
                'name' => 'Workspace Branch',
            ]);

        $response->assertRedirect(route('dashboard'));

        $branch = Organization::where('name', 'Workspace Branch')->first();

        $this->assertNotNull($branch);
        $this->assertSame('branch', $branch->organization_type);
        $this->assertSame($parent->id, $branch->parent_organization_id);
        $this->assertDatabaseMissing('subscriptions', [
            'organization_id' => $branch->id,
        ]);
    }

    public function test_user_without_any_workspace_can_create_first_main_workspace_shell_without_auto_subscription(): void
    {
        $user = $this->createUser('user');
        $this->createPlan([
            'tier_rank' => 1,
            'campaign_limit' => 5,
            'addons' => [],
        ]);

        $response = $this->actingAs($user)->post('/organization', [
            'name' => 'First Workspace',
        ]);

        $response->assertRedirect(route('dashboard'));

        $organization = Organization::where('name', 'First Workspace')->first();

        $this->assertNotNull($organization);
        $this->assertSame('main', $organization->organization_type);
        $this->assertDatabaseHas('teams', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('subscriptions', [
            'organization_id' => $organization->id,
        ]);
    }

    public function test_non_owner_cannot_create_branch_from_workspace_selector(): void
    {
        $user = $this->createUser('user');
        $parent = $this->createOrganization($user, [
            'name' => 'Protected Parent',
        ]);

        $managerRole = OrganizationRole::create([
            'name' => 'Manager '.Str::random(5),
            'organization_id' => $parent->id,
            'permissions' => [],
        ]);

        Team::create([
            'organization_id' => $parent->id,
            'user_id' => $user->id,
            'organization_role_id' => $managerRole->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $plan = $this->createPlan([
            'branches_limit' => 3,
            'addons' => [],
        ]);

        Subscription::create([
            'organization_id' => $parent->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_organization' => $parent->id])
            ->post('/organization', [
                'name' => 'Blocked Branch From Selector',
            ]);

        $response->assertSessionHasErrors('organization');
        $this->assertDatabaseMissing('organizations', [
            'name' => 'Blocked Branch From Selector',
        ]);
    }

    public function test_main_organization_with_branches_cannot_be_deleted(): void
    {
        $owner = $this->createUser('owner');
        $parent = $this->createOrganization($owner, [
            'name' => 'Parent For Deletion Guard',
        ]);
        $this->createOrganization($owner, [
            'name' => 'Existing Branch',
            'organization_type' => 'branch',
            'parent_organization_id' => $parent->id,
        ]);

        $this->actingAs($owner);

        $this->expectException(ValidationException::class);

        app(OrganizationService::class)->destroy($parent->uuid);
    }

    public function test_branch_with_operational_data_cannot_be_deleted(): void
    {
        $owner = $this->createUser('owner');
        $parent = $this->createOrganization($owner, [
            'name' => 'Parent Workspace',
        ]);
        $branch = $this->createOrganization($owner, [
            'name' => 'Branch With Contacts',
            'organization_type' => 'branch',
            'parent_organization_id' => $parent->id,
        ]);

        Contact::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $branch->id,
            'first_name' => 'Branch',
            'last_name' => 'Customer',
            'phone' => '+966501000000',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner);

        $this->expectException(ValidationException::class);

        app(OrganizationService::class)->destroy($branch->uuid);
    }

    public function test_direct_organization_type_change_is_blocked(): void
    {
        $owner = $this->createUser('owner');
        $organization = $this->createOrganization($owner, [
            'name' => 'Type Guard Organization',
        ]);

        $plan = $this->createPlan([
            'branches_limit' => 3,
            'addons' => [],
        ]);

        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $this->actingAs($owner);

        $request = Request::create('/admin/organizations/'.$organization->uuid, 'PUT', [
            'name' => $organization->name,
            'organization_type' => 'branch',
            'parent_organization_uuid' => (string) Str::uuid(),
            'plan' => $plan->uuid,
        ]);

        $this->expectException(ValidationException::class);

        app(OrganizationService::class)->update($request, $organization->uuid);
    }

    private function createUser(string $role = 'user'): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => Str::lower($role).'+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => $role,
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
                'addons' => [
                    'embedded_signup_enabled' => true,
                ],
            ]),
        ], $attributes));
    }

    private function createPlan(array $metadata): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Plan '.Str::random(4),
            'price' => 99.00,
            'period' => 'monthly',
            'metadata' => json_encode($metadata),
            'status' => 'active',
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
                'description' => 'Owner role for tests',
                'permissions' => ['*'],
            ]
        );
    }
}
