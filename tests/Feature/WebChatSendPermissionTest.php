<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class WebChatSendPermissionTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_chat_send_get_route_is_not_available(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/chat/send')
            ->assertNotFound();
    }

    public function test_web_chat_send_requires_reply_permission(): void
    {
        [$owner, $organization] = $this->createOwnerContext([], true);
        $agent = $this->createAgentWithPermissions($organization, $owner, ['chats.view_all']);
        $contact = $this->createContact($organization, $owner);

        $this->actingAs($agent, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson('/chats', [
                'uuid' => $contact->uuid,
                'type' => 'text',
                'message' => 'Should not send',
            ])
            ->assertForbidden();
    }

    private function createAgentWithPermissions(Organization $organization, User $owner, array $permissions): User
    {
        $agent = User::create([
            'first_name' => 'View',
            'last_name' => 'Only',
            'email' => 'view-only+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $role = OrganizationRole::create([
            'organization_id' => $organization->id,
            'name' => 'View Only '.Str::random(5),
            'description' => 'View-only chat role',
            'permissions' => $permissions,
        ]);

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $agent->id,
            'organization_role_id' => $role->id,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        return $agent;
    }

    private function createContact(Organization $organization, User $owner): Contact
    {
        return Contact::create([
            'organization_id' => $organization->id,
            'first_name' => 'Chat',
            'last_name' => 'Contact',
            'phone' => '+1555'.random_int(1000000, 9999999),
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
