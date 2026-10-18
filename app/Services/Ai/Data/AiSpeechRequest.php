<?php

namespace App\Services\Ai\Data;

final class AiSpeechRequest
{
    /**
     * @param  string  $format  "opus" returns Ogg/Opus, the format WhatsApp plays as a voice message
     * @param  string|null  $instructions  how to speak (accent, tone); ignored by models that do not support it
     */
    public function __construct(
        public readonly string $text,
        public readonly string $model,
        public readonly string $voice,
        public readonly string $format,
        public readonly ?string $instructions,
        public readonly int $timeoutSeconds,
    ) {
    }
}
