<?php

namespace Tests\Unit;

use App\Events\NewChatEvent;
use App\Events\NewPaymentEvent;
use Tests\TestCase;

class BroadcastGuardTest extends TestCase
{
    public function test_new_chat_event_skips_when_broadcast_driver_is_null(): void
    {
        config(['broadcasting.default' => 'null']);

        $event = new NewChatEvent([], 1);

        $this->assertFalse($event->broadcastWhen());
    }

    public function test_new_chat_event_skips_when_pusher_is_not_configured(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => null,
            'broadcasting.connections.pusher.secret' => null,
        ]);

        $event = new NewChatEvent([], 1);

        $this->assertFalse($event->broadcastWhen());
    }

    public function test_new_payment_event_skips_when_pusher_is_not_configured(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => '',
            'broadcasting.connections.pusher.secret' => '',
        ]);

        $event = new NewPaymentEvent([], 1);

        $this->assertFalse($event->broadcastWhen());
    }
}

