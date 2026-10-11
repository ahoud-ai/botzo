<?php

namespace App\Services\Ai\Data;

/**
 * Provider-neutral chat completion request. Messages use the common
 * [{role: system|user|assistant, content: string|array}] shape.
 */
final class AiChatRequest
{
    public function __construct(
        public readonly string $model,
        public readonly array $messages,
        public readonly int $maxTokens,
        public readonly float $temperature,
        public readonly int $timeoutSeconds,
        /** Voice name when the reply should also be spoken (providers that support audio output). */
        public readonly ?string $audioVoice = null,
    ) {
    }
}
