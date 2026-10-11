<?php

namespace App\Services\IntelliReply;

use App\Models\Contact;
use Illuminate\Support\Carbon;

/**
 * Human takeover: when a team member replies, automatic replies for that
 * contact pause for a while and then resume on their own.
 */
class AiConversationPauseService
{
    public function pauseForHumanReply(Contact $contact): void
    {
        $minutes = (int) config('intellireply.human_takeover_minutes', 60);

        if ($minutes > 0) {
            $this->pauseFor($contact, $minutes);
        }
    }

    public function pauseFor(Contact $contact, int $minutes): void
    {
        $contact->forceFill(['ai_paused_until' => now()->addMinutes(max(1, $minutes))])->save();
    }

    public function resume(Contact $contact): void
    {
        $contact->forceFill(['ai_paused_until' => null])->save();
    }

    public function isPaused(Contact $contact): bool
    {
        return $contact->ai_paused_until !== null && Carbon::parse($contact->ai_paused_until)->isFuture();
    }
}
