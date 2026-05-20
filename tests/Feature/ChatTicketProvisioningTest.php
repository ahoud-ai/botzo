<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckSubscriptionStatus;
use App\Models\Chat;
use App\Models\ChatTicket;
use App\Models\Contact;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class ChatTicketProvisioningTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_chat_index_does_not_create_missing_tickets_on_read(): void
    {
        [$user, $organization] = $this->createOwnerContext([
            'tickets' => [
                'active' => true,
                'auto_assignment' => false,
                'reassign_reopened_chats' => true,
                'allow_agents_to_view_all_chats' => true,
            ],
        ], true);

        $contact = Contact::create([
            'organization_id' => $organization->id,
            'first_name' => 'Chat',
            'last_name' => 'Reader',
            'phone' => '+15550001111',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
            'latest_chat_created_at' => now(),
        ]);

        Chat::create([
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'type' => 'inbound',
            'metadata' => json_encode(['text' => ['body' => 'Hello']]),
            'status' => 'delivered',
            'created_at' => now(),
        ]);

        $this->assertDatabaseMissing('chat_tickets', ['contact_id' => $contact->id]);

        $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/chats')
            ->assertOk();

        $this->assertDatabaseMissing('chat_tickets', ['contact_id' => $contact->id]);
    }

    public function test_repair_command_creates_missing_chat_tickets_idempotently(): void
    {
        [, $organization] = $this->createOwnerContext([
            'tickets' => [
                'active' => true,
                'auto_assignment' => false,
                'reassign_reopened_chats' => true,
                'allow_agents_to_view_all_chats' => true,
            ],
        ], true);

        $contact = Contact::create([
            'organization_id' => $organization->id,
            'first_name' => 'Repair',
            'last_name' => 'Target',
            'phone' => '+15550002222',
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'latest_chat_created_at' => now(),
        ]);

        Chat::create([
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'type' => 'inbound',
            'metadata' => json_encode(['text' => ['body' => 'Hello']]),
            'status' => 'delivered',
            'created_at' => now(),
        ]);

        $this->artisan('chats:repair-tickets', ['organization_id' => $organization->id])
            ->assertSuccessful();

        $this->assertSame(1, ChatTicket::where('contact_id', $contact->id)->count());

        $this->artisan('chats:repair-tickets', ['organization_id' => $organization->id])
            ->assertSuccessful();

        $this->assertSame(1, ChatTicket::where('contact_id', $contact->id)->count());
    }
}
