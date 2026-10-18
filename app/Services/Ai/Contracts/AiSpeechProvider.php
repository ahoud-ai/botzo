<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\Data\AiSpeechRequest;
use App\Services\Ai\Data\AiSpeechResult;
use App\Services\Ai\Exceptions\AiProviderException;

interface AiSpeechProvider
{
    /**
     * @throws AiProviderException
     */
    public function speak(AiSpeechRequest $request): AiSpeechResult;
}
