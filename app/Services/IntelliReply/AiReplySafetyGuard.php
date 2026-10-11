<?php

namespace App\Services\IntelliReply;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Always-on guard rails for automatic AI replies: a hard output-token ceiling
 * and a per-contact hourly reply cap that stops runaway bot-to-bot loops.
 */
class AiReplySafetyGuard
{
    private const HOUR_IN_SECONDS = 3600;

    public function maxOutputTokens(int $configured): int
    {
        $cap = max(64, (int) config('intellireply.safety.max_output_tokens', 1024));

        return $configured > 0 ? min($configured, $cap) : $cap;
    }

    public function allowsReplyToContact(int $organizationId, int $contactId): bool
    {
        $limit = $this->hourlyContactLimit();

        return $limit === 0 || ! RateLimiter::tooManyAttempts($this->contactKey($organizationId, $contactId), $limit);
    }

    public function recordReplyToContact(int $organizationId, int $contactId): void
    {
        if ($this->hourlyContactLimit() > 0) {
            RateLimiter::hit($this->contactKey($organizationId, $contactId), self::HOUR_IN_SECONDS);
        }
    }

    private function hourlyContactLimit(): int
    {
        return max(0, (int) config('intellireply.safety.max_replies_per_contact_per_hour', 15));
    }

    private function contactKey(int $organizationId, int $contactId): string
    {
        return sprintf('intellireply:contact-replies:%d:%d', $organizationId, $contactId);
    }
}
