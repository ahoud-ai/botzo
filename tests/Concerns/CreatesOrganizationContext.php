<?php

namespace Tests\Concerns;

use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

trait CreatesOrganizationContext
{
    protected function createOwnerContext(array $organizationMetadata = [], bool $withActiveSubscription = false): array
    {
        $user = User::create([
            'first_name' => 'Owner',
            'last_name' => 'Tester',
            'email' => 'owner+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $organization = Organization::create([
            'identifier' => 'org-'.Str::lower(Str::random(8)),
            'name' => 'Org '.Str::random(5),
            'created_by' => $user->id,
            'metadata' => json_encode($organizationMetadata),
        ]);

        $ownerRole = OrganizationRole::firstOrCreate(
            [
                'organization_id' => null,
                'name' => 'Owner',
            ],
            [
                'description' => __('Universal owner role'),
                'permissions' => ['*'],
            ]
        );

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        if ($withActiveSubscription) {
            $this->createActiveSubscription($organization->id);
        }

        return [$user, $organization, $ownerRole];
    }

    protected function createActiveSubscription(int $organizationId, array $planMetadata = []): Subscription
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Plan '.Str::random(5),
            'price' => 10,
            'period' => 'monthly',
            'metadata' => json_encode($planMetadata),
            'status' => 'active',
        ]);

        return Subscription::create([
            'organization_id' => $organizationId,
            'plan_id' => $plan->id,
            'payment_details' => null,
            'start_date' => now(),
            'valid_until' => now()->addMonth(),
            'status' => 'active',
        ]);
    }
}
