<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClientRoleAccessBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_billing_permission_is_redirected_away_from_billing_workspace(): void
    {
        [$user, $organization] = $this->createUserContextWithRole('Reply Employee', [
            'chats.view_assigned_only',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_organization' => $organization->id])
            ->get('/billing');

        $response->assertRedirect(route('dashboard'));
    }

    public function test_user_without_settings_permission_is_redirected_away_from_settings_workspace(): void
    {
        [$user, $organization] = $this->createUserContextWithRole('Reply Employee', [
            'chats.view_assigned_only',
        ], withSubscription: true);

        $response = $this->actingAs($user)
            ->withSession(['current_organization' => $organization->id])
            ->get('/settings');

        $response->assertRedirect(route('dashboard'));
    }

    public function test_user_with_settings_permission_can_open_settings_workspace(): void
    {
        [$user, $organization] = $this->createUserContextWithRole('Operations Manager', [
            'settings.manage',
        ], withSubscription: true);

        $response = $this->actingAs($user)
            ->withSession(['current_organization' => $organization->id])
            ->get('/settings');

        $response->assertOk();
    }

    private function createUserContextWithRole(string $roleName, array $permissions = [], bool $withSubscription = false): array
    {
        $user = User::create([
            'first_name' => 'Role',
            'last_name' => $roleName,
            'email' => Str::lower($roleName).'+'.Str::random(8).'@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $organization = Organization::create([
            'name' => 'Workspace '.Str::random(4),
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => $user->id,
            'organization_type' => 'main',
            'parent_organization_id' => null,
            'metadata' => json_encode([
                'addons' => [],
            ]),
        ]);

        $organizationRole = OrganizationRole::create([
            'organization_id' => $organization->id,
            'name' => $roleName,
            'description' => $roleName.' role for access boundary tests',
            'permissions' => $permissions,
        ]);

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'organization_role_id' => $organizationRole->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        if ($withSubscription) {
            $plan = SubscriptionPlan::create([
                'name' => 'Plan '.Str::random(4),
                'name_ar' => 'خطة '.Str::random(4),
                'name_en' => 'Plan '.Str::random(4),
                'price' => 49.00,
                'period' => 'monthly',
                'metadata' => json_encode([
                    'tier_rank' => 1,
                    'campaign_limit' => 10,
                    'message_limit' => 100,
                    'contacts_limit' => 100,
                    'canned_replies_limit' => 10,
                    'team_limit' => 5,
                    'branches_limit' => 2,
                    'addons' => [],
                ]),
                'status' => 'active',
            ]);

            Subscription::create([
                'organization_id' => $organization->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'start_date' => now()->subDay(),
                'valid_until' => now()->addMonth(),
            ]);
        }

        return [$user, $organization];
    }
}
