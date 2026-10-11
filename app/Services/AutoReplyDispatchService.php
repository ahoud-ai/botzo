<?php

namespace App\Services;

use App\Jobs\RunAutoReplyJob;
use App\Models\Chat;
use App\Models\Contact;
use App\Services\IntelliReply\AiConversationPauseService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Single entry point for automatic replies to an inbound message.
 *
 * - Debounce: a burst of customer messages gets one reply, from the latest message.
 * - One responder at a time: flows, basic replies and the AI run under one per-contact lock.
 */
class AutoReplyDispatchService
{
    private const STATE_TTL_MINUTES = 60;

    public function dispatch(Chat $chat, bool $isNewContact): void
    {
        $this->dispatchMany([['chat' => $chat, 'is_new_contact' => $isNewContact]]);
    }

    /**
     * @param  array<int, array{chat: Chat, is_new_contact: bool}>  $items  latest inbound message per contact
     */
    public function dispatchMany(array $items): void
    {
        $delay = 0;

        foreach ($items as $item) {
            $chat = $item['chat'];
            Cache::put($this->latestKey($chat), (int) $chat->id, now()->addMinutes(self::STATE_TTL_MINUTES));

            if ($item['is_new_contact']) {
                Cache::put($this->newContactKey($chat), true, now()->addMinutes(self::STATE_TTL_MINUTES));
            }

            $delay = max($delay, $this->debounceSeconds($chat));
        }

        if ($delay > 0 && config('graph.webhook_processing') === 'queue') {
            foreach ($items as $item) {
                RunAutoReplyJob::dispatch((int) $item['chat']->id)
                    ->onQueue((string) config('graph.webhook_queue', 'whatsapp-webhooks'))
                    ->delay(now()->addSeconds($delay));
            }

            return;
        }

        // Without a worker we wait in-process (after Meta already got its 200), once for the whole delivery.
        if ($delay > 0) {
            Sleep::for($delay)->seconds();
        }

        foreach ($items as $item) {
            $this->run($item['chat']);
        }
    }

    public function run(Chat $chat): void
    {
        if ($this->isSuperseded($chat)) {
            return;
        }

        $lock = Cache::lock(
            sprintf('auto-reply:%d:%d', $chat->organization_id, $chat->contact_id),
            max(10, (int) config('auto_replies.lock_seconds', 90))
        );

        try {
            $lock->block(max(1, (int) config('auto_replies.lock_wait_seconds', 30)), function () use ($chat): void {
                // A newer message may have arrived while we waited for the previous reply to finish.
                if ($this->isSuperseded($chat)) {
                    return;
                }

                // A team member is handling this customer; stay quiet until the takeover pause ends.
                $contact = Contact::find($chat->contact_id);
                if ($contact && app(AiConversationPauseService::class)->isPaused($contact)) {
                    return;
                }

                // The "latest" marker is kept on purpose: a late queued job for an older message must still see it.
                $isNewContact = (bool) Cache::pull($this->newContactKey($chat), false);

                app(AutoReplyService::class)->checkAutoReply($chat, $isNewContact);
            });
        } catch (LockTimeoutException) {
            Log::warning('Automatic reply skipped because another reply for this contact is still running.', [
                'organization_id' => $chat->organization_id,
                'contact_id' => $chat->contact_id,
                'chat_id' => $chat->id,
            ]);
        }
    }

    private function isSuperseded(Chat $chat): bool
    {
        $latestChatId = Cache::get($this->latestKey($chat));

        return $latestChatId !== null && (int) $latestChatId !== (int) $chat->id;
    }

    private function debounceSeconds(Chat $chat): int
    {
        $metadata = is_string($chat->metadata) ? json_decode($chat->metadata, true) : (array) $chat->metadata;

        // Button and list taps are deliberate single choices; answer them right away.
        if (in_array($metadata['type'] ?? null, ['button', 'interactive'], true)) {
            return 0;
        }

        return max(0, (int) config('auto_replies.debounce_seconds', 4));
    }

    private function latestKey(Chat $chat): string
    {
        return sprintf('auto-reply:latest:%d:%d', $chat->organization_id, $chat->contact_id);
    }

    private function newContactKey(Chat $chat): string
    {
        return sprintf('auto-reply:new-contact:%d:%d', $chat->organization_id, $chat->contact_id);
    }
}
