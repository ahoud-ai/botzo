<?php

namespace Tests\Feature;

use App\Events\NewChatEvent;
use App\Jobs\ProcessWebhookJob;
use App\Jobs\ProcessWebhookMediaJob;
use App\Models\Chat;
use App\Models\ChatLog;
use App\Models\ChatMedia;
use App\Models\ChatTicket;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Team;
use App\Models\User;
use App\Services\Whatsapp\WhatsappTokenVault;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class ChatMessagingIsolationTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_chat_send_rejects_contact_uuid_from_another_organization(): void
    {
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata('own'), true);
        [, $foreignOrganization] = $this->createOwnerContext($this->whatsappMetadata('foreign'), true);
        $foreignContact = $this->createContact($foreignOrganization->id);

        $response = $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/chats', [
                'uuid' => $foreignContact->uuid,
                'message' => 'Cross-org send should not happen',
                'type' => 'text',
            ]);

        $response->assertNotFound();
        $this->assertSame(0, Chat::where('organization_id', $organization->id)->count());
    }

    public function test_load_more_messages_rejects_contact_id_from_another_organization(): void
    {
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata('own'), true);
        [, $foreignOrganization] = $this->createOwnerContext($this->whatsappMetadata('foreign'), true);
        $foreignContact = $this->createContact($foreignOrganization->id);
        $foreignChat = $this->createChat($foreignOrganization->id, $foreignContact->id, 'foreign-wam-load-more');

        ChatLog::create([
            'contact_id' => $foreignContact->id,
            'entity_type' => 'chat',
            'entity_id' => $foreignChat->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/chats/'.$foreignContact->id.'/messages?page=1');

        $response->assertNotFound();
    }

    public function test_bulk_action_rejects_contact_uuids_from_another_organization(): void
    {
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata('own'), true);
        [, $foreignOrganization] = $this->createOwnerContext($this->whatsappMetadata('foreign'), true);
        $foreignContact = $this->createContact($foreignOrganization->id);
        $foreignChat = $this->createChat($foreignOrganization->id, $foreignContact->id, 'foreign-wam-bulk');

        $response = $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson('/chats/bulk-action', [
                'action' => 'clear',
                'contact_uuids' => [$foreignContact->uuid],
            ]);

        $response->assertNotFound();
        $this->assertNull($foreignChat->fresh()->deleted_at);
    }

    public function test_ticket_update_rejects_contact_uuid_from_another_organization(): void
    {
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata('own'), true);
        [, $foreignOrganization] = $this->createOwnerContext($this->whatsappMetadata('foreign'), true);
        $foreignContact = $this->createContact($foreignOrganization->id);
        $ticket = ChatTicket::create([
            'contact_id' => $foreignContact->id,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->put('/tickets/'.$foreignContact->uuid.'/update', [
                'status' => 'closed',
            ]);

        $response->assertNotFound();
        $this->assertSame('open', $ticket->fresh()->status);
    }

    public function test_assigned_only_agent_cannot_change_status_for_unassigned_contact(): void
    {
        [$owner, $organization] = $this->createOwnerContext($this->whatsappMetadata('own') + [
            'tickets' => [
                'active' => true,
                'allow_agents_to_view_all_chats' => false,
            ],
        ], true);
        $agent = $this->createAgentWithPermissions($organization, $owner, [
            'chats.change_status',
            'chats.view_assigned_only',
        ]);
        $contact = $this->createContact($organization->id);
        $ticket = ChatTicket::create([
            'contact_id' => $contact->id,
            'assigned_to' => $owner->id,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($agent, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->put('/tickets/'.$contact->uuid.'/update', [
                'status' => 'closed',
            ])
            ->assertNotFound();

        $this->assertSame('open', $ticket->fresh()->status);
        $this->assertSame($owner->id, $ticket->fresh()->assigned_to);
    }

    public function test_bulk_status_rejects_contacts_not_visible_to_assigned_only_agent(): void
    {
        [$owner, $organization] = $this->createOwnerContext($this->whatsappMetadata('own') + [
            'tickets' => [
                'active' => true,
                'allow_agents_to_view_all_chats' => false,
            ],
        ], true);
        $agent = $this->createAgentWithPermissions($organization, $owner, [
            'chats.change_status',
            'chats.view_assigned_only',
        ]);
        $contact = $this->createContact($organization->id);
        $ticket = ChatTicket::create([
            'contact_id' => $contact->id,
            'assigned_to' => $owner->id,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($agent, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson('/chats/bulk-action', [
                'action' => 'mark_closed',
                'contact_uuids' => [$contact->uuid],
            ])
            ->assertNotFound();

        $this->assertSame('open', $ticket->fresh()->status);
        $this->assertSame($owner->id, $ticket->fresh()->assigned_to);
    }

    public function test_webhook_status_update_does_not_touch_message_from_another_organization(): void
    {
        [, $organization] = $this->createOwnerContext($this->whatsappMetadata('own'), true);
        [, $foreignOrganization] = $this->createOwnerContext($this->whatsappMetadata('foreign'), true);
        $foreignContact = $this->createContact($foreignOrganization->id);
        $foreignChat = $this->createChat($foreignOrganization->id, $foreignContact->id, 'shared-wam-id');

        $payload = json_encode([
            'entry' => [[
                'id' => 'own-waba',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => [
                            'phone_number_id' => 'own-phone',
                        ],
                        'statuses' => [[
                            'id' => 'shared-wam-id',
                            'status' => 'read',
                        ]],
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'own-secret');

        $response = $this->call(
            'POST',
            '/webhook/whatsapp/'.$organization->identifier,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
            ],
            $payload
        );

        $response->assertOk();
        $this->assertSame('sent', $foreignChat->fresh()->status);
    }

    public function test_webhook_status_update_broadcasts_status_progress(): void
    {
        Event::fake([NewChatEvent::class]);
        [, $organization] = $this->createOwnerContext($this->whatsappMetadata('own'), true);
        $contact = $this->createContact($organization->id);
        $chat = $this->createChat($organization->id, $contact->id, 'status-progress-wam');
        $chat->update(['status' => 'delivered']);
        ChatLog::create([
            'contact_id' => $contact->id,
            'entity_type' => 'chat',
            'entity_id' => $chat->id,
            'created_at' => now(),
        ]);

        (new ProcessWebhookJob($this->statusPayload('status-progress-wam', 'read'), $organization->id))->handle();

        $this->assertSame('read', $chat->fresh()->status);
        Event::assertDispatched(NewChatEvent::class, fn (NewChatEvent $event) => (int) $event->organizationId === (int) $organization->id);
    }

    public function test_webhook_status_update_does_not_downgrade_read_messages(): void
    {
        Event::fake([NewChatEvent::class]);
        [, $organization] = $this->createOwnerContext($this->whatsappMetadata('own'), true);
        $contact = $this->createContact($organization->id);
        $chat = $this->createChat($organization->id, $contact->id, 'status-downgrade-wam');
        $chat->update(['status' => 'read']);
        ChatLog::create([
            'contact_id' => $contact->id,
            'entity_type' => 'chat',
            'entity_id' => $chat->id,
            'created_at' => now(),
        ]);

        (new ProcessWebhookJob($this->statusPayload('status-downgrade-wam', 'delivered'), $organization->id))->handle();

        $this->assertSame('read', $chat->fresh()->status);
        Event::assertNotDispatched(NewChatEvent::class);
    }

    public function test_media_webhook_defers_realtime_broadcast_until_media_job_completes(): void
    {
        Event::fake([NewChatEvent::class]);
        Queue::fake([ProcessWebhookMediaJob::class]);

        [, $organization] = $this->createOwnerContext($this->whatsappMetadata('own'), true);

        (new ProcessWebhookJob($this->imagePayload('media-deferral-wam'), $organization->id))->handle();

        $chat = Chat::where('organization_id', $organization->id)
            ->where('wam_id', 'media-deferral-wam')
            ->firstOrFail();

        $this->assertNull($chat->media_id);
        Queue::assertPushed(ProcessWebhookMediaJob::class);
        Event::assertNotDispatched(NewChatEvent::class);
    }

    public function test_media_job_broadcasts_realtime_update_after_media_is_available(): void
    {
        Event::fake([NewChatEvent::class]);

        [, $organization] = $this->createOwnerContext($this->whatsappMetadata('own'), true);
        $contact = $this->createContact($organization->id);
        $chat = $this->createChat($organization->id, $contact->id, 'media-ready-wam');
        $chat->update([
            'type' => 'inbound',
            'metadata' => json_encode([
                'type' => 'image',
                'image' => [
                    'id' => 'graph-media-ready',
                    'caption' => 'Ready media',
                ],
            ]),
            'status' => 'delivered',
        ]);

        $media = ChatMedia::create([
            'name' => 'N/A',
            'path' => 'https://example.test/media/ready.jpg',
            'type' => 'image/jpeg',
            'size' => 1024,
            'location' => 'local',
            'created_at' => now(),
        ]);

        $chat->update(['media_id' => $media->id]);

        ChatLog::create([
            'contact_id' => $contact->id,
            'entity_type' => 'chat',
            'entity_id' => $chat->id,
            'created_at' => now(),
        ]);

        $job = new ProcessWebhookMediaJob([
            'type' => 'image',
            'image' => ['id' => 'graph-media-ready'],
        ], $chat->id, $organization->id);

        $broadcast = new \ReflectionMethod($job, 'broadcastChatUpdate');
        $broadcast->setAccessible(true);
        $broadcast->invoke($job, $chat->fresh(['media']), (int) $organization->id);

        Event::assertDispatched(NewChatEvent::class, function (NewChatEvent $event) use ($organization, $chat, $media) {
            $value = $event->chat[0]['value'] ?? null;

            return (int) $event->organizationId === (int) $organization->id
                && $value instanceof Chat
                && (int) $value->id === (int) $chat->id
                && (int) $value->media?->id === (int) $media->id;
        });
    }

    private function whatsappMetadata(string $prefix): array
    {
        return [
            'whatsapp' => [
                'is_embedded_signup' => 0,
                'app_id' => $prefix.'-app',
                'phone_number_id' => $prefix.'-phone',
                'waba_id' => $prefix.'-waba',
                'app_secret_encrypted' => (new WhatsappTokenVault())->encryptAppSecret($prefix.'-secret'),
            ],
        ];
    }

    private function statusPayload(string $wamId, string $status): array
    {
        return [
            'entry' => [[
                'id' => 'own-waba',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => [
                            'phone_number_id' => 'own-phone',
                        ],
                        'statuses' => [[
                            'id' => $wamId,
                            'status' => $status,
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    private function imagePayload(string $wamId): array
    {
        return [
            'entry' => [[
                'id' => 'own-waba',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => [
                            'phone_number_id' => 'own-phone',
                        ],
                        'contacts' => [[
                            'wa_id' => '15551234567',
                            'profile' => [
                                'name' => 'Media Sender',
                            ],
                        ]],
                        'messages' => [[
                            'from' => '15551234567',
                            'id' => $wamId,
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'image',
                            'image' => [
                                'id' => 'graph-media-id',
                                'mime_type' => 'image/jpeg',
                                'sha256' => 'media-sha',
                                'caption' => 'Proof image',
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    private function createAgentWithPermissions(Organization $organization, User $owner, array $permissions): User
    {
        $agent = User::create([
            'first_name' => 'Assigned',
            'last_name' => 'Agent',
            'email' => 'assigned-agent+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $role = OrganizationRole::create([
            'organization_id' => $organization->id,
            'name' => 'Assigned Chat Role '.Str::random(5),
            'description' => 'Assigned-only chat test role',
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

    private function createContact(int $organizationId): Contact
    {
        return Contact::create([
            'organization_id' => $organizationId,
            'first_name' => 'Scoped',
            'last_name' => 'Contact',
            'phone' => '+1555'.random_int(1000000, 9999999),
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'latest_chat_created_at' => now(),
        ]);
    }

    private function createChat(int $organizationId, int $contactId, string $wamId): Chat
    {
        return Chat::create([
            'organization_id' => $organizationId,
            'wam_id' => $wamId,
            'contact_id' => $contactId,
            'type' => 'outbound',
            'metadata' => json_encode(['type' => 'text', 'text' => ['body' => 'Hello']]),
            'status' => 'sent',
            'created_at' => now(),
        ]);
    }
}
