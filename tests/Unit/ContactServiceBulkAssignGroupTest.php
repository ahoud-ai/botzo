<?php

namespace Tests\Unit;

use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Organization;
use App\Services\ContactService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContactServiceBulkAssignGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_assign_contacts_to_group_creates_only_missing_pivot_rows(): void
    {
        $organization = $this->createOrganization('org-main');
        $otherOrganization = $this->createOrganization('org-other');

        $group = ContactGroup::create([
            'organization_id' => $organization->id,
            'name' => 'VIP Leads',
            'created_by' => 1,
        ]);

        $contactA = $this->createContact($organization->id, 'Alpha', '+966501000001');
        $contactB = $this->createContact($organization->id, 'Beta', '+966501000002');
        $externalContact = $this->createContact($otherOrganization->id, 'Gamma', '+966501000003');

        DB::table('contact_contact_group')->insert([
            'contact_id' => $contactA->id,
            'contact_group_id' => $group->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new ContactService($organization->id);
        $result = $service->assignContactsToGroup(
            [$contactA->uuid, $contactB->uuid, $contactA->uuid, $externalContact->uuid],
            $group->uuid
        );

        $this->assertSame(2, $result['matched_contacts']);
        $this->assertSame(1, $result['new_assignments']);
        $this->assertDatabaseHas('contact_contact_group', [
            'contact_id' => $contactA->id,
            'contact_group_id' => $group->id,
        ]);
        $this->assertDatabaseHas('contact_contact_group', [
            'contact_id' => $contactB->id,
            'contact_group_id' => $group->id,
        ]);
        $this->assertSame(
            2,
            DB::table('contact_contact_group')->where('contact_group_id', $group->id)->count()
        );
    }

    public function test_assign_contacts_to_group_throws_if_group_is_not_in_same_organization(): void
    {
        $organization = $this->createOrganization('org-a');
        $otherOrganization = $this->createOrganization('org-b');

        $contact = $this->createContact($organization->id, 'Delta', '+966501000004');
        $group = ContactGroup::create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Other Group',
            'created_by' => 1,
        ]);

        $this->expectException(ModelNotFoundException::class);

        $service = new ContactService($organization->id);
        $service->assignContactsToGroup([$contact->uuid], $group->uuid);
    }

    private function createOrganization(string $identifier): Organization
    {
        return Organization::create([
            'identifier' => $identifier,
            'name' => 'Organization '.$identifier,
            'created_by' => 1,
            'metadata' => json_encode([]),
            'timezone' => 'UTC',
        ]);
    }

    private function createContact(int $organizationId, string $name, string $phone): Contact
    {
        return Contact::create([
            'organization_id' => $organizationId,
            'first_name' => $name,
            'phone' => $phone,
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
