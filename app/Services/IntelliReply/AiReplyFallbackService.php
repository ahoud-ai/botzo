<?php

namespace App\Services\IntelliReply;

use App\Models\Contact;
use App\Models\Organization;
use App\Services\AutomationFlows\AutomationFlowConversationHandoffService;
use App\Services\IntelliReply\Channels\AiReplyChannel;
use App\Services\IntelliReply\Channels\WhatsappReplyChannel;
use App\Services\Whatsapp\WhatsappAccessTokenRefreshService;
use App\Services\WhatsappService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The AI model failed or timed out: tell the customer the team will answer,
 * hand the chat to the team, and pause automatic replies briefly so a failing
 * provider is not hit on every message. The apology is sent at most once per cooldown.
 */
class AiReplyFallbackService
{
    /**
     * @return bool true when the message was handled (apology sent now or recently)
     */
    public function handle(Contact $contact, string $reason): bool
    {
        $organizationId = (int) $contact->organization_id;
        $cooldownKey = sprintf('intellireply:fallback-sent:%d:%d', $organizationId, $contact->id);

        Log::channel('intellireply')->warning('intellireply.fallback.triggered', [
            'organization_id' => $organizationId,
            'contact_id' => $contact->id,
            'reason' => $reason,
        ]);

        // Already apologized recently: stay quiet instead of repeating ourselves.
        if (Cache::has($cooldownKey)) {
            return true;
        }

        $organization = Organization::find($organizationId);
        $metadata = $organization?->metadata ? (json_decode($organization->metadata, true) ?: []) : [];
        $text = trim((string) (data_get($metadata, 'ai.fallback_text') ?: config('intellireply.fallback.text', '')));

        if (! $this->handOff($contact, $text, (int) config('intellireply.fallback.pause_minutes', 15), 'ai_unavailable')) {
            return false;
        }

        Cache::put($cooldownKey, true, now()->addMinutes(max(1, (int) config('intellireply.fallback.cooldown_minutes', 30))));

        return true;
    }

    /**
     * A voice note could not be used: ask the customer to type instead of going silent.
     * The assistant stays on (the customer's next text is answered normally) and the
     * request is sent at most once per cooldown.
     *
     * @return bool true when the customer was asked now or recently
     */
    public function askToType(Contact $contact, string $text, string $reason): bool
    {
        $organizationId = (int) $contact->organization_id;
        $cooldownKey = sprintf('intellireply:ask-to-type:%d:%d', $organizationId, $contact->id);

        Log::channel('intellireply')->notice('intellireply.voice.ask_to_type', [
            'organization_id' => $organizationId,
            'contact_id' => $contact->id,
            'reason' => $reason,
        ]);

        if (Cache::has($cooldownKey)) {
            return true;
        }

        $text = trim($text);
        if ($text === '' || ! $this->send($contact, $text)) {
            return false;
        }

        Cache::put($cooldownKey, true, now()->addMinutes(max(1, (int) config('intellireply.voice.retry_cooldown_minutes', 10))));
        app(AiReplySafetyGuard::class)->recordReplyToContact($organizationId, (int) $contact->id);

        return true;
    }

    /**
     * Send $text (with the bot disclosure when still pending), pause automatic replies
     * and assign the chat to the team.
     */
    public function handOff(Contact $contact, string $text, int $pauseMinutes, string $assignReason): bool
    {
        $text = trim($text);
        if ($text === '' || ! $this->send($contact, $text)) {
            return false;
        }

        app(AiConversationPauseService::class)->pauseFor($contact, $pauseMinutes);

        try {
            app(AutomationFlowConversationHandoffService::class)->assignToAgent($contact, 'auto_assign', null, true, $assignReason);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return true;
    }

    /**
     * Send $text, prefixed with the bot disclosure when it is still pending for this conversation.
     */
    private function send(Contact $contact, string $text): bool
    {
        $organizationId = (int) $contact->organization_id;
        $organization = Organization::find($organizationId);
        $metadata = $organization?->metadata ? (json_decode($organization->metadata, true) ?: []) : [];

        $disclosureService = app(AiDisclosureService::class);
        $disclosure = $disclosureService->pendingFor($organizationId, (int) $contact->id, $metadata, $organization?->name);
        $message = $disclosure !== null ? $disclosure."\n\n".$text : $text;

        if (! $this->replyChannel($organizationId, $metadata)->sendText($contact, $message)) {
            return false;
        }

        if ($disclosure !== null) {
            $disclosureService->markDisclosed($organizationId, (int) $contact->id);
        }

        return true;
    }

    protected function replyChannel(int $organizationId, array $metadata): AiReplyChannel
    {
        return new WhatsappReplyChannel(fn () => $this->whatsapp($organizationId, $metadata));
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
}
