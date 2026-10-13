<?php

namespace App\Services\IntelliReply;

use App\Models\Chat;
use App\Models\Contact;
use App\Models\Organization;
use App\Services\AddonStateService;
use App\Services\AutomationFlows\AutomationFlowConversationHandoffService;
use App\Services\Whatsapp\WhatsappAccessTokenRefreshService;
use App\Services\WhatsappService;
use Illuminate\Support\Facades\Log;

/**
 * A customer asking for a person ("موظف", "انسان", ...) stops automatic replies,
 * gets a short confirmation, and the chat is assigned to the team when ticketing is on.
 */
class AiHumanHandoffService
{
    public function handleIfRequested(Chat $chat): bool
    {
        $organization = Organization::find($chat->organization_id);
        $metadata = $organization?->metadata ? (json_decode($organization->metadata, true) ?: []) : [];

        if (! $organization || ! $this->assistantActive((int) $organization->id, $metadata)) {
            return false;
        }

        $chatMetadata = is_string($chat->metadata) ? json_decode($chat->metadata, true) : (array) $chat->metadata;
        $text = ($chatMetadata['type'] ?? null) === 'text' ? (string) data_get($chatMetadata, 'text.body', '') : '';

        if (! $this->asksForHuman($text, $this->keywords($metadata))) {
            return false;
        }

        $contact = Contact::find($chat->contact_id);
        if (! $contact) {
            return false;
        }

        app(AiConversationPauseService::class)->pauseFor($contact, (int) config('intellireply.handoff.pause_minutes', 720));

        try {
            app(AutomationFlowConversationHandoffService::class)->assignToAgent($contact, 'auto_assign', null, true, 'human_handoff');
        } catch (\Throwable $exception) {
            report($exception);
        }

        $confirmation = trim((string) config('intellireply.handoff.confirmation_text', ''));
        if ($confirmation !== '') {
            $this->whatsapp((int) $organization->id, $metadata)->sendMessage($contact->uuid, $confirmation);
        }

        Log::channel('intellireply')->info('intellireply.handoff.requested', [
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
        ]);

        return true;
    }

    /**
     * Whole-word match after normalizing Arabic spelling, so "عايز اكلم موظف" and "إنسان" match
     * while "الموظفين عندكم ممتازين" does not.
     */
    public function asksForHuman(string $text, array $keywords): bool
    {
        $haystack = ' '.$this->normalize($text).' ';

        foreach ($keywords as $keyword) {
            $needle = $this->normalize((string) $keyword);

            if ($needle !== '' && str_contains($haystack, ' '.$needle.' ')) {
                return true;
            }
        }

        return false;
    }

    protected function assistantActive(int $organizationId, array $metadata): bool
    {
        return (bool) data_get($metadata, 'ai.active', false)
            && app(AddonStateService::class)->isModuleEnabledForOrganization('AI Assistant', $organizationId);
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

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $text); // tashkeel and tatweel
        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي']);
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
