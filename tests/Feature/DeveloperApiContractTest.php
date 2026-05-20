<?php

namespace Tests\Feature;

use App\Models\OrganizationApiKey;
use App\Services\OrganizationApiTokenHasher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class DeveloperApiContractTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_authentication_errors_use_the_standard_developer_api_contract(): void
    {
        $response = $this->getJson('/api/verify');

        $response->assertStatus(401);
        $response->assertHeader('X-Request-Id');
        $response->assertJson([
            'statusCode' => 401,
            'code' => 'bearer_token_missing',
            'message' => __('Unauthorized. Bearer Token is missing.'),
        ]);
        $response->assertJsonStructure([
            'statusCode',
            'code',
            'message',
            'request_id',
        ]);
    }

    public function test_validation_errors_use_the_standard_developer_api_contract(): void
    {
        [, $organization] = $this->createOwnerContext([], true);

        $response = $this->withApiToken($organization->id)
            ->getJson('/api/contacts?per_page=101');

        $response->assertStatus(400);
        $response->assertJson([
            'statusCode' => 400,
            'code' => 'validation_error',
            'message' => __('The given data was invalid.'),
        ]);
        $response->assertJsonStructure([
            'statusCode',
            'code',
            'message',
            'request_id',
            'errors' => ['per_page'],
        ]);
    }

    public function test_developer_api_rate_limit_uses_the_standard_error_contract(): void
    {
        config(['services.developer_api.rate_limit_per_minute' => 1]);
        [, $organization] = $this->createOwnerContext([], true);
        $token = $this->createApiToken($organization->id);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/contacts')
            ->assertOk();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/contacts');

        $response->assertStatus(429);
        $response->assertJson([
            'statusCode' => 429,
            'code' => 'rate_limited',
            'message' => __('Too many API requests. Please retry shortly.'),
        ]);
        $response->assertJsonStructure([
            'statusCode',
            'code',
            'message',
            'request_id',
        ]);
    }

    public function test_campaign_limit_error_has_a_business_code_and_request_id(): void
    {
        [, $organization] = $this->createOwnerContext([
            'whatsapp' => [
                'app_id' => 'app-123',
                'phone_number_id' => 'phone-123',
                'waba_id' => 'waba-123',
            ],
        ], false);
        $this->createActiveSubscription($organization->id, [
            'campaign_limit' => 0,
        ]);

        $response = $this->withApiToken($organization->id)
            ->postJson('/api/campaigns', [
                'template' => 'template-uuid',
                'contacts' => 'all',
                'skip_schedule' => true,
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'statusCode' => 403,
            'code' => 'campaign_limit_reached',
            'message' => __('You have reached your limit of campaigns. Please upgrade your account to add more!'),
        ]);
        $response->assertJsonStructure([
            'statusCode',
            'code',
            'message',
            'request_id',
        ]);
    }

    public function test_canned_replies_limit_error_has_a_business_code_and_request_id(): void
    {
        [, $organization] = $this->createOwnerContext([], false);
        $this->createActiveSubscription($organization->id, [
            'canned_replies_limit' => 0,
        ]);

        $response = $this->withApiToken($organization->id)
            ->postJson('/api/canned-replies', [
                'name' => 'Quick hello',
                'trigger' => 'hello',
                'match_criteria' => 'contains',
                'response_type' => 'text',
                'response' => 'Hello',
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'statusCode' => 403,
            'code' => 'canned_replies_limit_reached',
            'message' => __('You\'ve reached your limit. Upgrade your account'),
        ]);
        $response->assertJsonStructure([
            'statusCode',
            'code',
            'message',
            'request_id',
        ]);
    }

    private function withApiToken(int $organizationId): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->createApiToken($organizationId));
    }

    private function createApiToken(int $organizationId): string
    {
        $plainToken = 'token-'.bin2hex(random_bytes(12));

        OrganizationApiKey::create([
            'organization_id' => $organizationId,
            'token' => app(OrganizationApiTokenHasher::class)->hashToken($plainToken),
            'token_last_four' => substr($plainToken, -4),
        ]);

        return $plainToken;
    }
}
