<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckSubscriptionStatus;
use App\Models\Addon;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserSettingsFeaturePayloadTest extends TestCase
{
    use DatabaseTransactions;

    public function test_workspace_settings_payload_only_exposes_sa_client_features(): void
    {
        [$user, $organization] = $this->createOwnedOrganization();

        foreach (['Embedded Signup', 'AI Assistant', 'Flow builder', 'Webhooks', 'Instagram', 'Google Maps'] as $addonName) {
            $this->createAddon($addonName);
        }

        $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('User/Settings/General')
                ->where('modules', fn ($modules): bool => $this->moduleNamesAreOnlyAllowedFeatures($modules))
            );

        $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/settings/whatsapp')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('User/Settings/Whatsapp')
                ->where('modules', fn ($modules): bool => $this->moduleNamesAreOnlyAllowedFeatures($modules))
            );
    }

    private function moduleNamesAreOnlyAllowedFeatures($modules): bool
    {
        $names = collect($modules)
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        return $names === ['AI Assistant', 'Embedded Signup', 'Flow builder'];
    }

    private function createOwnedOrganization(): array
    {
        $user = User::create([
            'first_name' => 'Settings',
            'last_name' => 'Reviewer',
            'email' => 'settings-review+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $organization = Organization::create([
            'name' => 'Settings Review Org',
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => $user->id,
            'organization_type' => 'main',
            'parent_organization_id' => null,
            'metadata' => json_encode(['addons' => []]),
        ]);

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'organization_role_id' => $this->ownerRole()->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        return [$user, $organization];
    }

    private function ownerRole(): OrganizationRole
    {
        return OrganizationRole::firstOrCreate(
            [
                'organization_id' => null,
                'name' => 'Owner',
            ],
            [
                'description' => 'Owner role',
                'permissions' => ['*'],
            ]
        );
    }

    private function createAddon(string $name): Addon
    {
        return Addon::create([
            'uuid' => (string) Str::uuid(),
            'category' => 'feature',
            'name' => $name,
            'logo' => 'feature.svg',
            'description' => $name,
            'metadata' => json_encode(['name' => Str::studly($name)]),
            'status' => 1,
            'is_active' => 1,
            'is_plan_restricted' => 1,
        ]);
    }
}
