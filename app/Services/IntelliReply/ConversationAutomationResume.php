<?php

namespace App\Services\IntelliReply;

use App\Models\AutomationFlowRun;
use App\Models\Contact;
use App\Services\AutomationFlows\AutomationFlowRuntimeSupportService;

/**
 * The team is done with a customer: everything automatic comes back for them, as before the
 * handoff. Runs when a conversation is closed and from the "resolved" button above the chat.
 *
 * - The assistant answers this customer again (no pause, enabled for the contact).
 * - Their open "wants a person" request is closed.
 * - A flow left waiting (for the team, a reply or a delay) is cancelled, so the customer's next
 *   message starts the flow from its beginning.
 */
class ConversationAutomationResume
{
    public const WAITING_STATUSES = ['active', 'waiting_input', 'waiting_handoff', 'waiting_delay'];

    public function __construct(
        private readonly AiConversationPauseService $pause,
        private readonly AutomationFlowRuntimeSupportService $runtimeSupport,
    ) {
    }

    public function resume(Contact $contact, ?int $userId = null): void
    {
        $this->pause->resume($contact, $userId);
        $contact->forceFill(['ai_assistance_enabled' => true])->save();

        AutomationFlowRun::with('version')
            ->where('organization_id', $contact->organization_id)
            ->where('contact_id', $contact->id)
            ->whereIn('status', self::WAITING_STATUSES)
            ->get()
            ->each(function (AutomationFlowRun $run) use ($userId): void {
                $this->runtimeSupport->recordStep($run, $run->waiting_node_id ?: $run->current_node_id, 'cancelled', null, null, [
                    'source' => 'team',
                    'reason' => 'released_by_team',
                    'user_id' => $userId,
                ]);
                $run->update([
                    'status' => 'cancelled',
                    'waiting_node_id' => null,
                    'waiting_for' => null,
                    'next_resume_at' => null,
                    'completed_at' => now(),
                    'last_activity_at' => now(),
                ]);
            });
    }

    /**
     * Whether anything automatic is held back for this customer right now.
     *
     * @param  bool  $assistant  false when the organization has no AI assistant: only flows count
     */
    public function isHeld(Contact $contact, bool $assistant = true): bool
    {
        if ($this->flowWaitsForTeam($contact)) {
            return true;
        }

        return $assistant && (
            $this->pause->isPaused($contact)
            || ! $contact->ai_assistance_enabled
            || app(HumanRequestService::class)->blocksAssistant($contact)
        );
    }

    /**
     * A flow handed this customer to the team and stays silent until the team is done.
     */
    public function flowWaitsForTeam(Contact $contact): bool
    {
        return AutomationFlowRun::where('organization_id', $contact->organization_id)
            ->where('contact_id', $contact->id)
            ->where('status', 'waiting_handoff')
            ->exists();
    }
}
