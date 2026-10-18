<?php

namespace App\Services\Ai\Data;

final class AiSpeechResult
{
    public function __construct(
        public readonly string $audio,
        public readonly string $mimeType,
        public readonly string $provider,
        public readonly string $model,
    ) {
    }
}
