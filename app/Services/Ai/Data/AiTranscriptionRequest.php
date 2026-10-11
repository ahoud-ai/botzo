<?php

namespace App\Services\Ai\Data;

final class AiTranscriptionRequest
{
    /**
     * @param  string|null  $language  ISO-639-1 code; null lets the model detect the language
     * @param  string|null  $prompt  context that improves accuracy (business name, dialect hint)
     */
    public function __construct(
        public readonly string $audio,
        public readonly string $fileName,
        public readonly string $mimeType,
        public readonly string $model,
        public readonly ?string $language,
        public readonly ?string $prompt,
        public readonly int $timeoutSeconds,
    ) {
    }
}
