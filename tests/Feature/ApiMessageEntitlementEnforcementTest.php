<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\OrganizationApiKey;
use App\Services\OrganizationApiTokenHasher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class ApiMessageEntitlementEnforcementTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_api_send_blocks_when_the_message_limit_for_the_current_cycle_is_reached(): void
    {
        [, $organization] = $this->createOwnerContext([
            'whatsapp' => [
                'app_id' => 'app-123',
                'phone_number_id' => 'phone-123',
                'waba_id' => 'waba-123',
            ],
        ], false);

        $this->createActiveSubscription($organization->id, [
            'message_limit' => 0,
            'contacts_limit' => 5,
        ]);

        Contact::create([
            'organization_id' => $organization->id,
            'first_name' => 'Existing',
            'last_name' => 'Contact',
            'phone' => '+966501234111',
            'email' => 'existing@example.com',
            'created_by' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $plainToken = $this->createApiToken($organization->id);

        $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->postJson('/api/send', [
                'phone' => '+966501234111',
                'message' => 'Hello world',
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'statusCode' => 403,
            'code' => 'message_limit_reached',
            'message' => __('You have reached your message limit for the current subscription cycle. Please upgrade your plan to continue sending messages.'),
        ]);
        $response->assertJsonStructure(['request_id']);
        $this->assertDatabaseCount('chats', 0);
    }

    public function test_api_send_does_not_auto_create_a_contact_when_contacts_limit_is_reached(): void
    {
        [, $organization] = $this->createOwnerContext([
            'whatsapp' => [
                'app_id' => 'app-123',
                'phone_number_id' => 'phone-123',
                'waba_id' => 'waba-123',
            ],
        ], false);

        $this->createActiveSubscription($organization->id, [
            'message_limit' => 10,
            'contacts_limit' => 0,
        ]);

        $plainToken = $this->createApiToken($organization->id);

        $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->postJson('/api/send', [
                'phone' => '+966501234112',
                'message' => 'Hello world',
                'first_name' => 'New',
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'statusCode' => 403,
            'code' => 'contacts_limit_reached',
            'message' => __('You have reached your limit of contacts. Please upgrade your account to add more!'),
        ]);
        $response->assertJsonStructure(['request_id']);
        $this->assertDatabaseMissing('contacts', [
            'organization_id' => $organization->id,
            'phone' => '+966501234112',
        ]);
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
