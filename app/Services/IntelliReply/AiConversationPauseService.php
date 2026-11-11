<?php

namespace App\Services\IntelliReply;

use App\Models\Contact;
use App\Models\ConversationHumanRequest;
use Illuminate\Support\Carbon;

/**
 * Human takeover: when a team member replies, automatic replies for that
 * contact pause for a while and then resume on their own.
 */
class AiConversationPauseService
{
    public function pauseForHumanReply(Contact $contact, ?int $userId = null): void
    {
        $minutes = (int) config('intellireply.human_takeover_minutes', 60);

        if ($minutes > 0) {
            $this->pauseFor($contact, $minutes);
        }

        app(HumanRequestService::class)->close($contact, ConversationHumanRequest::RESOLUTION_TEAM_REPLY, $userId);
    }

    public function pauseFor(Contact $contact, int $minutes): void
    {
        $contact->forceFill(['ai_paused_until' => now()->addMinutes(max(1, $minutes))])->save();
    }

    public function resume(Contact $contact, ?int $userId = null): void
    {
        $contact->forceFill(['ai_paused_until' => null])->save();

        app(HumanRequestService::class)->close($contact, ConversationHumanRequest::RESOLUTION_ASSISTANT_RESUMED, $userId);
    }

    public function isPaused(Contact $contact): bool
    {
        return $contact->ai_paused_until !== null && Carbon::parse($contact->ai_paused_until)->isFuture();
    }
}
