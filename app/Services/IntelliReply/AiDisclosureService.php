<?php

namespace App\Services\IntelliReply;

use Illuminate\Support\Facades\Cache;

/**
 * WhatsApp policy: customers must know they are talking to a bot. The disclosure
 * is sent once per conversation, before the first AI reply.
 */
class AiDisclosureService
{
    /**
     * The disclosure to send with this reply, or null if this conversation already got it.
     */
    public function pendingFor(int $organizationId, int $contactId, array $organizationMetadata, ?string $businessName): ?string
    {
        if (Cache::has($this->key($organizationId, $contactId))) {
            return null;
        }

        $text = $this->textFor($organizationMetadata, $businessName);

        return $text === '' ? null : $text;
    }

    public function markDisclosed(int $organizationId, int $contactId): void
    {
        $hours = max(1, (int) config('intellireply.disclosure.conversation_hours', 24));

        Cache::put($this->key($organizationId, $contactId), true, now()->addHours($hours));
    }

    public function textFor(array $organizationMetadata, ?string $businessName): string
    {
        $custom = trim((string) data_get($organizationMetadata, 'ai.disclosure_text', ''));
        $text = $custom !== '' ? $custom : (string) config('intellireply.disclosure.default_text', '');

        return trim(str_replace('{business}', trim((string) $businessName), $text));
    }

    private function key(int $organizationId, int $contactId): string
    {
        return sprintf('intellireply:disclosed:%d:%d', $organizationId, $contactId);
    }
}
