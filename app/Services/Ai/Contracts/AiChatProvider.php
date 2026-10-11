<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\Data\AiChatRequest;
use App\Services\Ai\Data\AiChatResult;
use App\Services\Ai\Exceptions\AiProviderException;

interface AiChatProvider
{
    /**
     * @throws AiProviderException when the provider is unreachable, times out or rejects the request
     */
    public function chat(AiChatRequest $request): AiChatResult;
}
