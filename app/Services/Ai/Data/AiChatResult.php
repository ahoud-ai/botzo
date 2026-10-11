<?php

namespace App\Services\Ai\Data;

final class AiChatResult
{
    public function __construct(
        public readonly string $text,
        public readonly string $provider,
        public readonly string $model,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly ?string $audioId = null,
        /** Base64 audio, when the provider produced a spoken reply. */
        public readonly ?string $audioData = null,
    ) {
    }

    public function hasAudio(): bool
    {
        return $this->audioData !== null && $this->audioData !== '';
    }
}
