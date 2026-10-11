<?php

namespace App\Services\Ai\Data;

final class AiTranscriptionResult
{
    public function __construct(
        public readonly string $text,
        public readonly string $provider,
        public readonly string $model,
        public readonly ?float $seconds = null,
    ) {
    }
}
