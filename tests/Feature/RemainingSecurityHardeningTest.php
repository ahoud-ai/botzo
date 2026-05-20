<?php

namespace Tests\Feature;

use App\Models\AutoReply;
use App\Models\ChatNote;
use App\Models\Contact;
use App\Models\ContactField;
use App\Models\Ticket;
use App\Models\TicketCategory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class RemainingSecurityHardeningTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_public_media_route_only_serves_public_storage_files(): void
    {
        Storage::disk('local')->put('public/security-test.txt', 'safe-media');

        $this->get('/media/public/security-test.txt')
            ->assertOk()
            ->assertSee('safe-media');

        $this->get('/media/../.env')->assertNotFound();
        $this->get('/media/private.txt')->assertNotFound();
    }

    public function test_canned_reply_edit_update_and_delete_reject_foreign_workspace_records(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);
        [, $foreignOrganization] = $this->createOwnerContext([], true);

        $foreignReply = AutoReply::create([
            'organization_id' => $foreignOrganization->id,
            'name' => 'Foreign reply',
            'trigger' => 'hello',
            'match_criteria' => 'contains',
            'metadata' => json_encode(['type' => 'text', 'data' => ['text' => 'foreign']]),
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/automation/basic/'.$foreignReply->uuid.'/edit')
            ->assertNotFound();

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->put('/automation/basic/'.$foreignReply->uuid, [
                'name' => 'Hijacked',
                'trigger' => 'hi',
                'match_criteria' => 'contains',
                'response_type' => 'text',
                'response' => 'nope',
            ])
            ->assertNotFound();

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->delete('/automation/basic/'.$foreignReply->uuid)
            ->assertNotFound();

        $this->assertSame('Foreign reply', $foreignReply->fresh()->name);
        $this->assertNull($foreignReply->fresh()->deleted_at);
    }

    public function test_chat_notes_reject_foreign_contacts_and_notes(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);
        [, $foreignOrganization] = $this->createOwnerContext([], true);

        $foreignContact = $this->createContact($foreignOrganization->id, $user->id);
        $foreignNote = ChatNote::create([
            'contact_id' => $foreignContact->id,
            'content' => 'Foreign note',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/notes', [
                'contact' => $foreignContact->uuid,
                'notes' => 'Should not attach',
            ])
            ->assertNotFound();

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->delete('/notes/'.$foreignNote->uuid)
            ->assertNotFound();

        $this->assertNull($foreignNote->fresh()->deleted_at);
    }

    public function test_user_support_routes_reject_other_users_tickets(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);
        [$foreignUser] = $this->createOwnerContext([], true);
        $category = TicketCategory::create(['name' => 'Support']);

        $foreignTicket = Ticket::create([
            'reference' => 'SUP-FOREIGN',
            'user_id' => $foreignUser->id,
            'category_id' => $category->id,
            'subject' => 'Foreign ticket',
            'message' => 'Foreign message',
            'status' => 'open',
            'priority' => 'low',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/support/'.$foreignTicket->uuid)
            ->assertNotFound();

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/support/'.$foreignTicket->uuid.'/comment', ['message' => 'Nope'])
            ->assertNotFound();

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/support/'.$foreignTicket->uuid.'/status', ['status' => 'closed'])
            ->assertNotFound();

        $this->assertSame('open', $foreignTicket->fresh()->status);
    }

    public function test_contact_fields_update_delete_and_reorder_reject_foreign_workspace_fields(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);
        [, $foreignOrganization] = $this->createOwnerContext([], true);

        $foreignField = ContactField::create([
            'organization_id' => $foreignOrganization->id,
            'name' => 'Foreign Field',
            'type' => 'input',
            'value' => 'text',
            'position' => 1,
            'required' => 0,
        ]);

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->put('/contact-fields/'.$foreignField->uuid, [
                'name' => 'Hijacked Field',
                'component' => 'input',
                'type' => 'text',
                'required' => 0,
            ])
            ->assertNotFound();

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->delete('/contact-fields/'.$foreignField->uuid)
            ->assertRedirect();

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson('/contact-fields/update-positions', [
                'orderedIds' => [$foreignField->uuid],
            ])
            ->assertOk();

        $this->assertSame('Foreign Field', $foreignField->fresh()->name);
        $this->assertNull($foreignField->fresh()->deleted_at);
        $this->assertSame(1, (int) $foreignField->fresh()->position);
    }

    private function createContact(int $organizationId, int $userId): Contact
    {
        return Contact::create([
            'organization_id' => $organizationId,
            'first_name' => 'Scoped',
            'last_name' => 'Contact',
            'phone' => '+1555'.random_int(1000000, 9999999),
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
