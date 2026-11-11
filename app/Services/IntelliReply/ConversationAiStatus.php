<?php

namespace App\Services\IntelliReply;

use App\Models\Contact;
use App\Models\ConversationHumanRequest;
use Illuminate\Support\Carbon;

/**
 * What the assistant will really do in one conversation, worked out by the same
 * checks the reply paths use, so the chat screen never shows "on" while it stays silent.
 */
class ConversationAiStatus
{
    public const ON = 'on';
    public const OFF_EVERYWHERE = 'off_everywhere';
    public const WAITING_TEAM = 'waiting_team';
    public const TEAM_REPLIED = 'team_replied';
    public const OFF_HERE = 'off_here';

    /**
     * @param  array<string, mixed>  $metadata  the organization's metadata
     * @return array{state: string, paused_until: ?string, request_reason: ?string}
     */
    public function for(Contact $contact, array $metadata): array
    {
        $state = self::ON;
        $pausedUntil = null;
        $requestReason = null;

        if (! data_get($metadata, 'ai.active', false)) {
            $state = self::OFF_EVERYWHERE;
        } elseif ($request = $this->blockingRequest($contact)) {
            $state = self::WAITING_TEAM;
            $requestReason = $request->reason;
        } elseif (app(AiConversationPauseService::class)->isPaused($contact)) {
            $state = self::TEAM_REPLIED;
            $pausedUntil = Carbon::parse($contact->ai_paused_until)->toIso8601String();
        } elseif (! $contact->ai_assistance_enabled) {
            $state = self::OFF_HERE;
        }

        return [
            'state' => $state,
            'paused_until' => $pausedUntil,
            'request_reason' => $requestReason,
        ];
    }

    private function blockingRequest(Contact $contact): ?ConversationHumanRequest
    {
        return ConversationHumanRequest::query()
            ->where('open_contact_id', $contact->id)
            ->where('blocks_assistant', true)
            ->first();
    }
}
