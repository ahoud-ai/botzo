<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\OrganizationDefaultRoleService;
use App\Services\OrganizationService;
use App\Support\OrganizationRolePresetCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrganizationDefaultRoleProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_workspace_creation_provisions_default_roles(): void
    {
        $user = $this->createUser();
        $this->ensureUniversalOwnerRole();

        $organization = app(OrganizationService::class)->createOwnedMainOrganizationShell(
            $user,
            'HQ '.Str::random(4),
            $user->id
        );

        $this->assertPresetRolesExistForOrganization($organization->id);
        $this->assertSame(
            OrganizationRolePresetCatalog::SEED_VERSION,
            (int) data_get(json_decode((string) $organization->fresh()->metadata, true), 'system.default_role_seed_version')
        );
    }

    public function test_branch_creation_route_provisions_default_roles(): void
    {
        $user = $this->createUser();
        $this->ensureUniversalOwnerRole();
        $plan = $this->createActivePlan();

        $company = app(OrganizationService::class)->createOwnedBillableMainOrganization(
            $user,
            'Parent '.Str::random(4),
            $user->id,
            $plan
        );

        $response = $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $company->id])
            ->post('/organization', [
                'name' => 'Branch '.Str::random(4),
            ]);

        $response->assertRedirect('/dashboard');

        $branch = Organization::query()
            ->where('parent_organization_id', $company->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertPresetRolesExistForOrganization($branch->id);
    }

    public function test_default_role_service_backfills_existing_workspace_once(): void
    {
        $organization = Organization::create([
            'name' => 'Previous '.Str::random(4),
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => 1,
            'organization_type' => 'main',
            'parent_organization_id' => null,
            'metadata' => json_encode([]),
        ]);

        $service = app(OrganizationDefaultRoleService::class);
        $service->ensureDefaultsForOrganization($organization);
        $service->ensureDefaultsForOrganization($organization->fresh());

        $this->assertSame(
            count(OrganizationRolePresetCatalog::names()),
            OrganizationRole::query()
                ->where('organization_id', $organization->id)
                ->whereIn('name', OrganizationRolePresetCatalog::names())
                ->count()
        );
    }

    private function assertPresetRolesExistForOrganization(int $organizationId): void
    {
        foreach (OrganizationRolePresetCatalog::names() as $roleName) {
            $this->assertDatabaseHas('organization_roles', [
                'organization_id' => $organizationId,
                'name' => $roleName,
                'deleted_at' => null,
            ]);
        }
    }

    private function ensureUniversalOwnerRole(): void
    {
        OrganizationRole::query()->firstOrCreate(
            [
                'organization_id' => null,
                'name' => 'Owner',
            ],
            [
                'description' => 'Universal owner role',
                'permissions' => ['*'],
            ]
        );
    }

    private function createUser(): User
    {
        return User::create([
            'first_name' => 'Preset',
            'last_name' => 'Owner',
            'email' => 'preset-owner+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);
    }

    private function createActivePlan(): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Preset Plan '.Str::random(4),
            'name_ar' => 'خطة جاهزة '.Str::random(4),
            'name_en' => 'Preset Plan '.Str::random(4),
            'price' => 49.00,
            'period' => 'monthly',
            'metadata' => json_encode([
                'tier_rank' => 1,
                'team_limit' => 10,
                'branches_limit' => 5,
                'contacts_limit' => 500,
                'message_limit' => 1000,
                'campaign_limit' => 50,
                'canned_replies_limit' => 20,
                'addons' => [],
            ]),
            'status' => 'active',
        ]);
    }
}
