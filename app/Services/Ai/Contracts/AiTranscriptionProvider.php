<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\Data\AiTranscriptionRequest;
use App\Services\Ai\Data\AiTranscriptionResult;
use App\Services\Ai\Exceptions\AiProviderException;

interface AiTranscriptionProvider
{
    /**
     * @throws AiProviderException
     */
    public function transcribe(AiTranscriptionRequest $request): AiTranscriptionResult;
}
