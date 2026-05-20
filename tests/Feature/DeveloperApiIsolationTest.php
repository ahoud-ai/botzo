<?php

namespace Tests\Feature;

use App\Models\AutoReply;
use App\Models\Chat;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\OrganizationApiKey;
use App\Services\OrganizationApiTokenHasher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class DeveloperApiIsolationTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_contact_update_cannot_modify_contact_from_another_organization(): void
    {
        [, $organizationA] = $this->createOwnerContext([], true);
        [, $organizationB] = $this->createOwnerContext([], true);

        $foreignContact = $this->createContact($organizationB->id, '+966501235001', [
            'first_name' => 'Foreign',
        ]);

        $response = $this->withApiToken($organizationA->id)
            ->putJson('/api/contacts/'.$foreignContact->uuid, [
                'first_name' => 'Hijacked',
                'phone' => '+966501235002',
            ]);

        $response->assertStatus(404);
        $response->assertJson([
            'statusCode' => 404,
            'message' => __('Contact not found.'),
        ]);

        $this->assertDatabaseHas('contacts', [
            'id' => $foreignContact->id,
            'organization_id' => $organizationB->id,
            'first_name' => 'Foreign',
            'phone' => '+966501235001',
        ]);
    }

    public function test_contact_delete_cannot_touch_contact_or_chats_from_another_organization(): void
    {
        [, $organizationA] = $this->createOwnerContext([], true);
        [, $organizationB] = $this->createOwnerContext([], true);

        $foreignContact = $this->createContact($organizationB->id, '+966501235003');
        $foreignChat = Chat::create([
            'organization_id' => $organizationB->id,
            'contact_id' => $foreignContact->id,
            'type' => 'inbound',
            'metadata' => json_encode(['text' => ['body' => 'Hello']]),
            'is_read' => 0,
            'created_at' => now(),
        ]);

        $response = $this->withApiToken($organizationA->id)
            ->deleteJson('/api/contacts/'.$foreignContact->uuid);

        $response->assertStatus(404);
        $response->assertJson([
            'statusCode' => 404,
            'message' => __('Contact not found.'),
        ]);

        $this->assertDatabaseHas('contacts', [
            'id' => $foreignContact->id,
            'organization_id' => $organizationB->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('chats', [
            'id' => $foreignChat->id,
            'organization_id' => $organizationB->id,
            'is_read' => 0,
        ]);
    }

    public function test_contact_update_cannot_attach_group_from_another_organization(): void
    {
        [, $organizationA] = $this->createOwnerContext([], true);
        [, $organizationB] = $this->createOwnerContext([], true);

        $contact = $this->createContact($organizationA->id, '+966501235004', [
            'first_name' => 'Local',
        ]);
        $foreignGroup = ContactGroup::create([
            'organization_id' => $organizationB->id,
            'name' => 'Foreign Group',
            'created_by' => 0,
        ]);

        $response = $this->withApiToken($organizationA->id)
            ->putJson('/api/contacts/'.$contact->uuid, [
                'first_name' => 'Local Updated',
                'phone' => '+966501235004',
                'group' => [$foreignGroup->uuid],
            ]);

        $response->assertOk();
        $this->assertDatabaseMissing('contact_contact_group', [
            'contact_id' => $contact->id,
            'contact_group_id' => $foreignGroup->id,
        ]);
    }

    public function test_contact_group_update_cannot_reassign_group_from_another_organization(): void
    {
        [, $organizationA] = $this->createOwnerContext([], true);
        [, $organizationB] = $this->createOwnerContext([], true);

        $foreignGroup = ContactGroup::create([
            'organization_id' => $organizationB->id,
            'name' => 'Foreign Group',
            'created_by' => 0,
        ]);

        $response = $this->withApiToken($organizationA->id)
            ->putJson('/api/contact-groups/'.$foreignGroup->uuid, [
                'name' => 'Hijacked Group',
            ]);

        $response->assertStatus(404);
        $response->assertJson([
            'statusCode' => 404,
            'message' => __('Contact group not found.'),
        ]);

        $this->assertDatabaseHas('contact_groups', [
            'id' => $foreignGroup->id,
            'organization_id' => $organizationB->id,
            'name' => 'Foreign Group',
        ]);
    }

    public function test_canned_reply_update_cannot_modify_reply_from_another_organization(): void
    {
        [, $organizationA] = $this->createOwnerContext([], true);
        [, $organizationB] = $this->createOwnerContext([], true);

        $foreignReply = AutoReply::create([
            'organization_id' => $organizationB->id,
            'name' => 'Foreign Reply',
            'trigger' => 'hello',
            'match_criteria' => 'contains',
            'metadata' => json_encode([
                'type' => 'text',
                'data' => [
                    'text' => 'Original response',
                ],
            ]),
            'created_by' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withApiToken($organizationA->id)
            ->putJson('/api/canned-replies/'.$foreignReply->uuid, [
                'name' => 'Hijacked Reply',
                'trigger' => 'hello',
                'match_criteria' => 'contains',
                'response_type' => 'text',
                'response' => 'Changed response',
            ]);

        $response->assertStatus(404);
        $response->assertJson([
            'statusCode' => 404,
            'message' => __('Canned reply not found.'),
        ]);

        $this->assertDatabaseHas('auto_replies', [
            'id' => $foreignReply->id,
            'organization_id' => $organizationB->id,
            'name' => 'Foreign Reply',
            'trigger' => 'hello',
        ]);
    }

    private function createContact(int $organizationId, string $phone, array $overrides = []): Contact
    {
        return Contact::create(array_merge([
            'organization_id' => $organizationId,
            'first_name' => 'Contact',
            'last_name' => 'Tester',
            'phone' => $phone,
            'email' => null,
            'created_by' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function withApiToken(int $organizationId): self
    {
        $plainToken = 'token-'.bin2hex(random_bytes(12));

        OrganizationApiKey::create([
            'organization_id' => $organizationId,
            'token' => app(OrganizationApiTokenHasher::class)->hashToken($plainToken),
            'token_last_four' => substr($plainToken, -4),
        ]);

        return $this->withHeader('Authorization', 'Bearer '.$plainToken);
    }
}
