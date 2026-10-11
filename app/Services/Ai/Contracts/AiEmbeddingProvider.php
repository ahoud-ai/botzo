<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\Exceptions\AiProviderException;

/**
 * Separate from chat on purpose: not every chat provider offers embeddings,
 * so knowledge-base search can stay on one provider while chat uses another.
 */
interface AiEmbeddingProvider
{
    /**
     * @return array<int, float>
     *
     * @throws AiProviderException
     */
    public function embed(string $text, string $model): array;
}
