<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewPaymentEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $payment;
    public $organizationId;

    /**
     * Create a new event instance.
     *
     * @param mixed $payment
     * @param int $organizationId
     */
    public function __construct($payment, $organizationId)
    {
        $this->payment = $payment;
        $this->organizationId = $organizationId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel
     */
    public function broadcastOn()
    {
        return new Channel('payments.ch' . $this->organizationId);
    }

    /**
     * Skip broadcasting cleanly when driver is null or pusher is misconfigured.
     */
    public function broadcastWhen(): bool
    {
        $driver = (string) config('broadcasting.default', 'null');

        if ($driver === 'null') {
            return false;
        }

        if ($driver !== 'pusher') {
            return true;
        }

        return filled(config('broadcasting.connections.pusher.key'))
            && filled(config('broadcasting.connections.pusher.secret'));
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return ['payment' => $this->payment];
    }
}
