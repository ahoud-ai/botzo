<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tells these users to refresh their bell. It carries no customer data: the list itself is read
 * through the access-checked endpoint.
 */
class HumanRequestsChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /** @param list<int> $userIds */
    public function __construct(private readonly array $userIds, public readonly bool $opened)
    {
    }

    public function broadcastOn(): array
    {
        return array_map(fn (int $userId) => new PrivateChannel('chats.user.'.$userId), $this->userIds);
    }

    public function broadcastWhen(): bool
    {
        return $this->userIds !== [];
    }

    public function broadcastWith(): array
    {
        return ['opened' => $this->opened];
    }
}
