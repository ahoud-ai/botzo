<?php

namespace App\Services\IntelliReply;

use App\Models\Contact;
use App\Models\Organization;
use App\Services\AutomationFlows\AutomationFlowConversationHandoffService;
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

        Log::warning('intellireply.fallback.triggered', [
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

        if ($text === '') {
            return false;
        }

        $disclosureService = app(AiDisclosureService::class);
        $disclosure = $disclosureService->pendingFor($organizationId, (int) $contact->id, $metadata, $organization?->name);
        $message = $disclosure !== null ? $disclosure."\n\n".$text : $text;

        $sent = $this->whatsapp($organizationId, $metadata)->sendMessage($contact->uuid, $message);

        if (($sent->success ?? false) !== true) {
            return false;
        }

        if ($disclosure !== null) {
            $disclosureService->markDisclosed($organizationId, (int) $contact->id);
        }

        Cache::put($cooldownKey, true, now()->addMinutes(max(1, (int) config('intellireply.fallback.cooldown_minutes', 30))));
        app(AiConversationPauseService::class)->pauseFor($contact, (int) config('intellireply.fallback.pause_minutes', 15));

        try {
            app(AutomationFlowConversationHandoffService::class)->assignToAgent($contact, 'auto_assign', null, true, 'ai_unavailable');
        } catch (\Throwable $exception) {
            report($exception);
        }

        return true;
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
