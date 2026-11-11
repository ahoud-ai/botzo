<?php

namespace App\Services\IntelliReply;

use App\Models\Chat;
use App\Models\Contact;
use App\Models\ConversationHumanRequest;
use App\Models\Organization;
use App\Services\AddonStateService;
use App\Services\AutomationFlows\AutomationFlowConversationHandoffService;
use App\Services\IntelliReply\Channels\AiReplyChannel;
use App\Services\IntelliReply\Channels\AiReplyChannels;
use App\Services\Whatsapp\WhatsappAccessTokenRefreshService;
use App\Services\WhatsappService;
use Illuminate\Support\Facades\Log;

/**
 * A customer asking for a person ("موظف", "أبغى أكلم أحد", ...) reaches the team through the bell,
 * gets a short confirmation, and the chat is assigned to the team when ticketing is on. This works
 * with the assistant switched off too, because the customer still needs a person; it only leaves
 * automatic replies running then, since that switch stops the assistant and nothing else.
 */
class AiHumanHandoffService
{
    /** Messages a customer sends in one go are answered together, so a request in any of them counts. */
    private const BURST_MESSAGES = 5;

    public function handleIfRequested(Chat $chat): bool
    {
        $organization = Organization::find($chat->organization_id);
        $metadata = $organization?->metadata ? (json_decode($organization->metadata, true) ?: []) : [];

        if (! $organization || ! $this->assistantModuleEnabled((int) $organization->id)) {
            return false;
        }

        foreach ($this->unansweredTexts($chat) as $text) {
            if ($this->requestedIn($text, $metadata)) {
                return $this->handOver($chat, $organization, $metadata);
            }
        }

        return false;
    }

    /**
     * A voice note turned out to ask for a person.
     */
    public function handleTranscribedRequest(Chat $chat, string $text): bool
    {
        $organization = Organization::find($chat->organization_id);
        $metadata = $organization?->metadata ? (json_decode($organization->metadata, true) ?: []) : [];

        return $organization && $this->requestedIn($text, $metadata) && $this->handOver($chat, $organization, $metadata);
    }

    /**
     * The assistant is off and a website visitor wrote: tell them once that the team will answer,
     * and tell the team someone is waiting. Turning the assistant on again is not held back by this.
     */
    public function acknowledgeAssistantOff(Chat $chat): void
    {
        $organization = Organization::find($chat->organization_id);
        $metadata = $organization?->metadata ? (json_decode($organization->metadata, true) ?: []) : [];
        $contact = Contact::find($chat->contact_id);

        if (! $organization || ! $contact) {
            return;
        }

        $opened = app(HumanRequestService::class)->open($contact, ConversationHumanRequest::REASON_ASSISTANT_OFF);
        $text = trim((string) config('intellireply.assistant_off.text', ''));

        if ($opened['created'] && $text !== '') {
            $this->replyChannel($contact, $metadata)->sendText($contact, $text);
        }
    }

    public function asksForHuman(string $text, array $keywords, bool $looseKeywords = false): bool
    {
        return app(HumanRequestDetector::class)->asksForPerson($text, $keywords, $looseKeywords);
    }

    public function requestedIn(string $text, array $metadata): bool
    {
        return $this->asksForHuman($text, $this->keywords($metadata), $this->hasCustomKeywords($metadata));
    }

    protected function assistantActive(int $organizationId, array $metadata): bool
    {
        return (bool) data_get($metadata, 'ai.active', false)
            && $this->assistantModuleEnabled($organizationId);
    }

    protected function assistantModuleEnabled(int $organizationId): bool
    {
        return app(AddonStateService::class)->isModuleEnabledForOrganization('AI Assistant', $organizationId);
    }

    private function handOver(Chat $chat, Organization $organization, array $metadata): bool
    {
        $contact = Contact::find($chat->contact_id);
        if (! $contact) {
            return false;
        }

        $pause = app(AiConversationPauseService::class);
        $alreadyPaused = $pause->isPaused($contact);
        $opened = app(HumanRequestService::class)->open($contact, ConversationHumanRequest::REASON_CUSTOMER_REQUEST);

        if ($this->assistantActive((int) $organization->id, $metadata)) {
            $pause->pauseFor($contact, (int) config('intellireply.handoff.pause_minutes', 720));
        }

        try {
            app(AutomationFlowConversationHandoffService::class)->assignToAgent($contact, 'auto_assign', null, true, 'human_handoff');
        } catch (\Throwable $exception) {
            report($exception);
        }

        // Someone is already on it, or the customer already heard this: no need to repeat it.
        $confirmation = trim((string) config('intellireply.handoff.confirmation_text', ''));
        if ($opened['created'] && ! $alreadyPaused && $confirmation !== '') {
            $this->replyChannel($contact, $metadata)->sendText($contact, $confirmation);
        }

        Log::channel('intellireply')->info('intellireply.handoff.requested', [
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
        ]);

        return true;
    }

    /**
     * What the customer sent since the last reply, newest first.
     *
     * @return list<string>
     */
    private function unansweredTexts(Chat $chat): array
    {
        $lastReplyId = (int) Chat::query()
            ->where('contact_id', $chat->contact_id)
            ->where('type', 'outbound')
            ->max('id');

        $recent = Chat::query()
            ->where('contact_id', $chat->contact_id)
            ->where('type', 'inbound')
            ->whereNull('deleted_at')
            ->where('id', '>', $lastReplyId)
            ->where('id', '<', $chat->id)
            ->where('created_at', '>=', now()->subSeconds((int) config('auto_replies.burst_window_seconds', 120)))
            ->orderByDesc('id')
            ->limit(self::BURST_MESSAGES - 1)
            ->get(['id', 'metadata']);

        return collect([$chat])->concat($recent)
            ->map(fn (Chat $message) => $this->textOf($message))
            ->filter(fn (string $text) => $text !== '')
            ->values()
            ->all();
    }

    private function textOf(Chat $chat): string
    {
        $metadata = is_string($chat->metadata) ? json_decode($chat->metadata, true) : (array) $chat->metadata;

        return match ($metadata['type'] ?? null) {
            'text' => (string) data_get($metadata, 'text.body', ''),
            'button' => (string) (data_get($metadata, 'button.text') ?: data_get($metadata, 'button.payload', '')),
            'interactive' => (string) (data_get($metadata, 'interactive.button_reply.title')
                ?: data_get($metadata, 'interactive.list_reply.title', '')),
            default => '',
        };
    }

    protected function replyChannel(Contact $contact, array $metadata): AiReplyChannel
    {
        return AiReplyChannels::for($contact, fn () => $this->whatsapp((int) $contact->organization_id, $metadata));
    }

    protected function whatsapp(int $organizationId, array $metadata): WhatsappService
    {
        $accessToken = app(WhatsappAccessTokenRefreshService::class)->resolveTokenForOrganization($organizationId, true);

        return new WhatsappService(
            $accessToken,
            config('graph.api_version'),
            $metadata['whatsapp']['app_id'] ?? null,
            $metadata['whatsapp']['phone_number_id'] ?? null,
            $metadata['whatsapp']['waba_id'] ?? null,
            $organizationId
        );
    }

    private function keywords(array $metadata): array
    {
        $custom = trim((string) data_get($metadata, 'ai.handoff_keywords', ''));

        return $custom !== '' ? explode(',', $custom) : (array) config('intellireply.handoff.keywords', []);
    }

    private function hasCustomKeywords(array $metadata): bool
    {
        return trim((string) data_get($metadata, 'ai.handoff_keywords', '')) !== '';
    }
}
