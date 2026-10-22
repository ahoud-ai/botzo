<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class AdminOrganizationContactsTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_admins_see_the_contacts_of_the_organization_and_its_branches(): void
    {
        [$main, $branch, $other] = $this->organizations();
        $this->contact($main, 'Sara', '+966500000001');
        $branchContact = $this->contact($branch, 'Omar', '+966500000002');
        $this->contact($other, 'Hidden', '+966500000003');
        Chat::create(['organization_id' => $branch->id, 'wam_id' => 'wamid.'.Str::random(10), 'contact_id' => $branchContact->id, 'type' => 'inbound', 'metadata' => '{}', 'status' => 'delivered', 'created_at' => now()]);

        $response = $this->actingAs($this->admin(), 'admin')->getJson('/admin/organizations/'.$main->uuid.'/contacts');

        $response->assertOk()->assertJsonPath('meta.total', 2);
        $rows = collect($response->json('data'))->keyBy('name');
        $this->assertSame(['Omar', 'Sara'], $rows->keys()->sort()->values()->all());
        $this->assertSame($branch->name, $rows['Omar']['workspace']);
        $this->assertNotNull($rows['Omar']['last_message_at']);
        $this->assertNull($rows['Sara']['last_message_at']);
    }

    public function test_a_branch_page_shows_only_the_branch_contacts(): void
    {
        [$main, $branch] = $this->organizations();
        $this->contact($main, 'Sara', '+966500000001');
        $this->contact($branch, 'Omar', '+966500000002');

        $this->actingAs($this->admin(), 'admin')
            ->getJson('/admin/organizations/'.$branch->uuid.'/contacts')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Omar')
            ->assertJsonPath('data.0.workspace', null);
    }

    public function test_search_and_pagination(): void
    {
        [$main] = $this->organizations();
        foreach (range(1, 27) as $i) {
            $this->contact($main, 'Customer '.$i, '+9665100000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
        }
        $this->contact($main, 'Nadia 100%', '+966520000000');
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->getJson('/admin/organizations/'.$main->uuid.'/contacts?page=2')
            ->assertOk()
            ->assertJsonPath('meta.total', 28)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(3, 'data');

        $this->actingAs($admin, 'admin')->getJson('/admin/organizations/'.$main->uuid.'/contacts?search=Nadia')
            ->assertJsonPath('meta.total', 1);

        // "%" is matched literally, not as a wildcard.
        $this->actingAs($admin, 'admin')->getJson('/admin/organizations/'.$main->uuid.'/contacts?search=%25')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_guests_and_customer_users_cannot_list_contacts(): void
    {
        [$main] = $this->organizations();
        [$customer] = $this->createOwnerContext([], true);

        $this->getJson('/admin/organizations/'.$main->uuid.'/contacts')->assertUnauthorized();
        $this->actingAs($customer, 'user')->get('/admin/organizations/'.$main->uuid.'/contacts')->assertRedirect();
    }

    public function test_the_organization_page_shows_the_contacts_count(): void
    {
        [$main, $branch] = $this->organizations();
        $this->contact($main, 'Sara', '+966500000001');
        $this->contact($branch, 'Omar', '+966500000002');

        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/organizations/'.$main->uuid)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('contactsCount', 2));
    }

    /**
     * @return array{0:Organization,1:Organization,2:Organization}
     */
    private function organizations(): array
    {
        [, $main] = $this->createOwnerContext([], true);
        [, $other] = $this->createOwnerContext([], true);
        $branch = Organization::create([
            'identifier' => 'branch-'.Str::lower(Str::random(8)),
            'name' => 'Branch '.Str::random(4),
            'organization_type' => 'branch',
            'parent_organization_id' => $main->id,
            'created_by' => $main->created_by,
            'metadata' => json_encode([]),
        ]);

        return [$main, $branch, $other];
    }

    private function contact(Organization $organization, string $firstName, string $phone): Contact
    {
        return Contact::create([
            'first_name' => $firstName,
            'phone' => $phone,
            'organization_id' => $organization->id,
            'created_by' => 0,
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'first_name' => 'Admin',
            'last_name' => 'Tester',
            'email' => 'admin+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'email_verified_at' => now(),
        ]);
    }
}
