<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Addon;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserAiAssistantSetupTest extends TestCase
{
    use DatabaseTransactions;

    public function test_setup_encrypts_api_key_and_removes_plaintext_key(): void
    {
        [$user, $organization] = $this->createUserAndOrganization([
            'ai' => [
                'api_key' => 'sk-previous-plain',
            ],
        ]);

        $response = $this->withoutMiddleware([
                CheckSubscriptionStatus::class,
                VerifyCsrfToken::class,
            ])
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/automation/ai/setup', [
                'active' => true,
                'api_key' => 'sk-test-encrypted',
                'model' => 'gpt-4o-mini',
                'voice' => null,
                'allow_audio_response' => false,
                'max_tokens' => 512,
                'temperature' => 0.7,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status', function ($status): bool {
            return is_array($status)
                && ($status['type'] ?? null) === 'success'
                && ($status['message'] ?? null) === __('Open AI settings updated successfully');
        });

        $organization->refresh();
        $metadata = json_decode($organization->metadata ?? '{}', true);

        $this->assertIsArray($metadata);
        $this->assertIsArray($metadata['ai'] ?? null);
        $this->assertTrue((bool) data_get($metadata, 'ai.active'));
        $this->assertSame('OpenAI', data_get($metadata, 'ai.platform'));
        $this->assertSame('gpt-4o-mini', data_get($metadata, 'ai.model'));
        $this->assertSame('text-embedding-3-small', data_get($metadata, 'ai.embedding_model'));
        $this->assertArrayNotHasKey('api_key', $metadata['ai']);
        $this->assertNotEmpty(data_get($metadata, 'ai.api_key_encrypted'));
        $this->assertSame('sk-test-encrypted', Crypt::decryptString($metadata['ai']['api_key_encrypted']));
    }

    public function test_setup_keeps_existing_encrypted_key_when_api_key_input_is_blank(): void
    {
        $existingEncrypted = Crypt::encryptString('sk-existing-value');

        [$user, $organization] = $this->createUserAndOrganization([
            'ai' => [
                'api_key_encrypted' => $existingEncrypted,
            ],
        ]);

        $response = $this->withoutMiddleware([
                CheckSubscriptionStatus::class,
                VerifyCsrfToken::class,
            ])
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/automation/ai/setup', [
                'active' => true,
                'api_key' => '',
                'model' => 'gpt-4o-mini',
                'voice' => null,
                'allow_audio_response' => false,
                'max_tokens' => 512,
                'temperature' => 0.7,
            ]);

        $response->assertStatus(302);

        $organization->refresh();
        $metadata = json_decode($organization->metadata ?? '{}', true);

        $this->assertIsArray($metadata);
        $this->assertArrayNotHasKey('api_key', $metadata['ai']);
        $this->assertSame('sk-existing-value', Crypt::decryptString($metadata['ai']['api_key_encrypted']));
    }

    public function test_setup_rejects_unknown_embedding_model(): void
    {
        [$user, $organization] = $this->createUserAndOrganization([
            'ai' => [],
        ]);

        $response = $this->withoutMiddleware([
                CheckSubscriptionStatus::class,
                VerifyCsrfToken::class,
            ])
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/automation/ai/setup', [
                'active' => true,
                'api_key' => 'sk-test-encrypted',
                'model' => 'gpt-4o-mini',
                'embedding_model' => 'not-a-valid-embedding-model',
                'voice' => null,
                'allow_audio_response' => false,
                'max_tokens' => 512,
                'temperature' => 0.7,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('embedding_model');
    }

    public function test_setup_rejects_audio_responses_for_non_audio_model(): void
    {
        [$user, $organization] = $this->createUserAndOrganization([
            'ai' => [],
        ]);

        $response = $this->withoutMiddleware([
                CheckSubscriptionStatus::class,
                VerifyCsrfToken::class,
            ])
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/automation/ai/setup', [
                'active' => true,
                'api_key' => 'sk-test-encrypted',
                'model' => 'gpt-4o-mini',
                'voice' => 'alloy',
                'allow_audio_response' => true,
                'max_tokens' => 512,
                'temperature' => 0.7,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status', function ($status): bool {
            return is_array($status)
                && ($status['type'] ?? null) === 'error'
                && ($status['message'] ?? null) === __('Selected model does not support audio responses. Choose an audio-capable model.');
        });
    }

    public function test_setup_accepts_audio_responses_for_audio_capable_model(): void
    {
        [$user, $organization] = $this->createUserAndOrganization([
            'ai' => [],
        ]);

        $response = $this->withoutMiddleware([
                CheckSubscriptionStatus::class,
                VerifyCsrfToken::class,
            ])
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/automation/ai/setup', [
                'active' => true,
                'api_key' => 'sk-test-encrypted',
                'model' => 'gpt-audio-1.5',
                'voice' => 'alloy',
                'allow_audio_response' => true,
                'max_tokens' => 512,
                'temperature' => 0.7,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status', function ($status): bool {
            return is_array($status)
                && ($status['type'] ?? null) === 'success'
                && ($status['message'] ?? null) === __('Open AI settings updated successfully');
        });

        $organization->refresh();
        $metadata = json_decode($organization->metadata ?? '{}', true);

        $this->assertSame('gpt-audio-1.5', data_get($metadata, 'ai.model'));
        $this->assertTrue((bool) data_get($metadata, 'ai.allow_audio_response'));
    }

    private function createUserAndOrganization(array $metadata): array
    {
        $user = User::create([
            'first_name' => 'User',
            'last_name' => 'Tester',
            'email' => 'ai-user+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $organization = Organization::create([
            'identifier' => 'org-'.Str::lower(Str::random(8)),
            'name' => 'Org '.Str::random(4),
            'created_by' => $user->id,
            'metadata' => json_encode($metadata),
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

        $addon = Addon::firstOrCreate(
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

        $addon->update([
            'status' => 1,
            'is_active' => 1,
            'is_plan_restricted' => 1,
        ]);

        $plan = SubscriptionPlan::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'AI Plan '.Str::random(4),
            'price' => 49.99,
            'period' => 'monthly',
            'metadata' => json_encode([
                'addons' => [
                    'AI Assistant' => true,
                ],
            ]),
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
