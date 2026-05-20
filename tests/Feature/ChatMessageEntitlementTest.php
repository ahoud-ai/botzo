<?php

namespace Tests\Feature;

use App\Models\Contact;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class ChatMessageEntitlementTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_chat_send_blocks_when_the_current_subscription_cycle_message_limit_is_reached(): void
    {
        [$user, $organization] = $this->createOwnerContext([
            'whatsapp' => [
                'app_id' => 'app-123',
                'phone_number_id' => 'phone-123',
                'waba_id' => 'waba-123',
            ],
        ], false);

        $this->createActiveSubscription($organization->id, [
            'message_limit' => 0,
            'contacts_limit' => 10,
        ]);

        $contact = Contact::create([
            'organization_id' => $organization->id,
            'first_name' => 'Existing',
            'last_name' => 'Contact',
            'phone' => '+14155550130',
            'email' => 'existing-chat@example.com',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/chats', [
                'uuid' => $contact->uuid,
                'message' => 'Hello from chat',
                'type' => 'text',
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => __('You have reached your message limit for the current subscription cycle. Please upgrade your plan to continue sending messages.'),
        ]);
        $this->assertDatabaseCount('chats', 0);
    }
}
