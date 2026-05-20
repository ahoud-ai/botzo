<?php

namespace Tests\Unit;

use App\Events\NewChatEvent;
use App\Models\Chat;
use App\Models\Contact;
use Tests\TestCase;

class NewChatEventTest extends TestCase
{
    public function test_broadcast_with_exposes_compact_chat_context(): void
    {
        $contact = new Contact([
            'uuid' => 'contact-uuid-1',
            'first_name' => 'Mona',
            'last_name' => 'Adel',
        ]);

        $chat = new Chat([
            'type' => 'inbound',
            'metadata' => json_encode([
                'text' => ['body' => 'Need a quick update on the order status.'],
            ]),
        ]);
        $chat->setRelation('contact', $contact);

        $event = new NewChatEvent([
            [
                'type' => 'chat',
                'value' => $chat,
            ],
        ], 12);

        $payload = $event->broadcastWith();

        $this->assertSame('contact-uuid-1', $payload['contact_uuid']);
        $this->assertSame('Mona Adel', $payload['contact_name']);
        $this->assertSame(
            'Need a quick update on the order status.',
            $payload['message_preview']
        );
        $this->assertSame('inbound', $payload['direction']);
        $this->assertArrayHasKey('chat', $payload);
    }

    public function test_broadcast_with_keeps_previous_payload_when_extra_context_is_missing(): void
    {
        $event = new NewChatEvent([
            [
                'type' => 'chat',
                'value' => ['id' => 9],
            ],
        ], 9);

        $payload = $event->broadcastWith();

        $this->assertSame([[
            'type' => 'chat',
            'value' => ['id' => 9],
        ]], $payload['chat']);
        $this->assertArrayNotHasKey('contact_uuid', $payload);
        $this->assertArrayNotHasKey('contact_name', $payload);
        $this->assertArrayNotHasKey('message_preview', $payload);
        $this->assertArrayNotHasKey('direction', $payload);
    }

    public function test_broadcast_on_includes_targeted_user_streams_when_recipients_are_provided(): void
    {
        $event = new NewChatEvent([], 15, [42, 42, null, 77]);

        $channels = array_map('strval', $event->broadcastOn());

        $this->assertSame([
            'private-chats.ch15',
            'private-chats.user.42',
            'private-chats.user.77',
        ], $channels);
    }
}
