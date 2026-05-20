<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckSubscriptionStatus;
use App\Models\Addon;
use App\Models\Chat;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\OrganizationAiUsageCounter;
use App\Models\OrganizationRole;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Modules\IntelliReply\Services\AIResponseService;
use Tests\TestCase;

class IntelliReplyChatSuggestionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_chat_suggestion_returns_draft_and_consumes_ai_text_usage(): void
    {
        [$user, $organization, $subscription] = $this->createUserContext([
            'ai_text_response_limit' => 3,
            'ai_system_key_monthly_quota' => 3,
        ]);
        $contact = $this->createContactWithInboundMessage($organization, 'What is the price?');

        $mock = Mockery::mock(AIResponseService::class);
        $mock->shouldReceive('suggestReply')
            ->once()
            ->withArgs(fn (int $organizationId, Contact $passedContact, array $keyBundle): bool => $organizationId === $organization->id
                && $passedContact->id === $contact->id
                && ($keyBundle['source'] ?? null) === 'global')
            ->andReturn([
                'success' => true,
                'text' => 'The price starts from 49 SAR. Would you like me to share the available options?',
            ]);
        $this->app->instance(AIResponseService::class, $mock);

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->getJson('/automation/chat/suggestion?contact='.$contact->uuid);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.text', 'The price starts from 49 SAR. Would you like me to share the available options?');

        $this->assertDatabaseHas('organization_ai_usage_counters', [
            'organization_id' => $organization->id,
            'subscription_id' => $subscription->id,
            'text_count' => 1,
        ]);
        $this->assertDatabaseHas('organization_ai_usage_counters', [
            'organization_id' => $organization->id,
            'subscription_id' => null,
            'text_count' => 1,
        ]);
    }

    public function test_chat_suggestion_respects_ai_text_response_limit_before_calling_openai(): void
    {
        [$user, $organization] = $this->createUserContext([
            'ai_text_response_limit' => 0,
            'ai_system_key_monthly_quota' => 3,
        ]);
        $contact = $this->createContactWithInboundMessage($organization, 'Can you help?');

        $mock = Mockery::mock(AIResponseService::class);
        $mock->shouldNotReceive('suggestReply');
        $this->app->instance(AIResponseService::class, $mock);

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->getJson('/automation/chat/suggestion?contact='.$contact->uuid);

        $response->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('AI response limit reached for this subscription.'));

        $this->assertSame(
            0,
            (int) OrganizationAiUsageCounter::query()
                ->where('organization_id', $organization->id)
                ->sum('text_count')
        );
    }

    public function test_chat_suggestion_cannot_read_contact_from_another_organization(): void
    {
        [$user, $organization] = $this->createUserContext();
        [, $otherOrganization] = $this->createUserContext();
        $foreignContact = $this->createContactWithInboundMessage($otherOrganization, 'Private question');

        $mock = Mockery::mock(AIResponseService::class);
        $mock->shouldNotReceive('suggestReply');
        $this->app->instance(AIResponseService::class, $mock);

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->getJson('/automation/chat/suggestion?contact='.$foreignContact->uuid);

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    private function createUserContext(array $limits = []): array
    {
        Setting::updateOrCreate(['key' => 'enable_ai_billing'], ['value' => 1]);
        Setting::updateOrCreate(['key' => 'ai_key_policy'], ['value' => 'hybrid']);
        Setting::updateOrCreate(['key' => 'ai_allow_org_override'], ['value' => 1]);
        Setting::updateOrCreate([
            'key' => 'ai_global_api_key_encrypted',
        ], [
            'value' => Crypt::encryptString('sk-test-global'),
        ]);

        $user = User::create([
            'first_name' => 'AI',
            'last_name' => 'Agent',
            'email' => 'ai-agent+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $organization = Organization::create([
            'identifier' => 'org-'.Str::lower(Str::random(8)),
            'name' => 'Org '.Str::random(5),
            'created_by' => $user->id,
            'metadata' => json_encode([
                'ai' => [
                    'active' => true,
                    'key_source' => 'global',
                    'model' => 'gpt-4o-mini',
                    'max_tokens' => 512,
                    'temperature' => 0.7,
                ],
            ]),
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

        Addon::updateOrCreate(
            ['name' => 'AI Assistant'],
            [
                'uuid' => (string) Str::uuid(),
                'category' => 'Automation',
                'logo' => 'ai.svg',
                'description' => __('AI assistant module'),
                'metadata' => json_encode(['name' => 'IntelliReply']),
                'status' => 1,
                'is_active' => 1,
                'is_plan_restricted' => 1,
            ]
        );

        $plan = SubscriptionPlan::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'AI Plan '.Str::random(5),
            'price' => 39.99,
            'period' => 'monthly',
            'metadata' => json_encode(array_merge([
                'addons' => ['AI Assistant' => true],
                'ai_text_response_limit' => 5,
                'ai_audio_response_limit' => 2,
                'ai_system_key_monthly_quota' => 5,
                'ai_organization_key_enabled' => 0,
            ], $limits)),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subscription = Subscription::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now(),
            'valid_until' => now()->addMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $organization, $subscription];
    }

    private function createContactWithInboundMessage(Organization $organization, string $message): Contact
    {
        $contact = Contact::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'first_name' => 'Customer',
            'last_name' => Str::random(4),
            'phone' => '+1555555'.random_int(1000, 9999),
            'created_by' => $organization->created_by,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Chat::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'type' => 'inbound',
            'metadata' => json_encode([
                'type' => 'text',
                'text' => ['body' => $message],
            ]),
            'created_at' => now(),
        ]);

        return $contact->refresh();
    }
}
