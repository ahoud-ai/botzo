<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckSubscriptionStatus;
use App\Models\Addon;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class IntelliReplyEndpointGateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_ai_setup_endpoint_returns_404_when_ai_assistant_addon_is_disabled(): void
    {
        [$user, $organization] = $this->createUserContext(false);

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/automation/ai/setup', [
                'active' => true,
                'model' => 'gpt-4o-mini',
                'allow_audio_response' => false,
                'max_tokens' => 512,
                'temperature' => 0.7,
            ]);

        $response->assertStatus(404);
    }

    public function test_document_delete_endpoint_returns_404_when_ai_assistant_addon_is_disabled(): void
    {
        [$user, $organization] = $this->createUserContext(false);

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->delete('/automation/upload/document/non-existent-uuid');

        $response->assertStatus(404);
    }

    public function test_chat_suggestion_endpoint_returns_404_when_ai_assistant_addon_is_disabled(): void
    {
        [$user, $organization] = $this->createUserContext(false);

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->getJson('/automation/chat/suggestion?contact=missing-contact');

        $response->assertStatus(404);
    }

    private function createUserContext(bool $addonActive): array
    {
        $user = User::create([
            'first_name' => 'Gate',
            'last_name' => 'Tester',
            'email' => 'ai-gate+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $organization = Organization::create([
            'identifier' => 'org-'.Str::lower(Str::random(8)),
            'name' => 'Org '.Str::random(5),
            'created_by' => $user->id,
            'metadata' => json_encode([]),
        ]);

        $ownerRole = OrganizationRole::firstOrCreate(
            ['organization_id' => null, 'name' => 'Owner'],
            ['description' => __('Universal owner role'), 'permissions' => ['*']]
        );

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $addon = Addon::firstOrCreate(
            ['name' => 'AI Assistant'],
            [
                'uuid' => (string) Str::uuid(),
                'category' => 'Automation',
                'logo' => 'ai.svg',
                'description' => __('AI assistant module'),
                'metadata' => json_encode(['name' => 'IntelliReply']),
                'status' => 1,
                'is_active' => 0,
                'is_plan_restricted' => 1,
            ]
        );

        $addon->update([
            'status' => 1,
            'is_active' => $addonActive ? 1 : 0,
            'is_plan_restricted' => 1,
        ]);

        $plan = SubscriptionPlan::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Plan '.Str::random(5),
            'price' => 39.99,
            'period' => 'monthly',
            'metadata' => json_encode(['addons' => ['AI Assistant' => true]]),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Subscription::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now(),
            'valid_until' => now()->addMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $organization];
    }
}
