<?php

namespace App\Jobs;

use App\Models\Chat;
use App\Services\AutoReplyDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Debounced automatic reply, used when webhooks are processed in queue mode.
 */
class RunAutoReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 1;
    public $timeout = 120;

    public function __construct(private readonly int $chatId)
    {
    }

    public function handle(AutoReplyDispatchService $dispatcher): void
    {
        $chat = Chat::with('contact', 'media')->find($this->chatId);

        if ($chat) {
            $dispatcher->run($chat);
        }
    }
}
