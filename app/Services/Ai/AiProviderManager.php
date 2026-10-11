<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiChatProvider;
use App\Services\Ai\Contracts\AiEmbeddingProvider;
use InvalidArgumentException;

/**
 * Resolves AI providers by name from config('intellireply.providers').
 * Adding Anthropic or Google later means one class plus one config entry.
 */
class AiProviderManager
{
    public function chat(?string $provider, string $apiKey): AiChatProvider
    {
        return $this->make($provider ?: $this->defaultChatProvider(), $apiKey, AiChatProvider::class);
    }

    public function embeddings(?string $provider, string $apiKey): AiEmbeddingProvider
    {
        return $this->make($provider ?: $this->defaultEmbeddingProvider(), $apiKey, AiEmbeddingProvider::class);
    }

    public function defaultChatProvider(): string
    {
        return (string) config('intellireply.default_chat_provider', 'openai');
    }

    public function defaultEmbeddingProvider(): string
    {
        return (string) config('intellireply.default_embedding_provider', 'openai');
    }

    /**
     * @template T
     *
     * @param  class-string<T>  $capability
     * @return T
     */
    private function make(string $provider, string $apiKey, string $capability): object
    {
        $class = config('intellireply.providers.'.strtolower($provider));

        if (! is_string($class) || ! class_exists($class)) {
            throw new InvalidArgumentException("Unknown AI provider [{$provider}].");
        }

        $instance = app()->makeWith($class, ['apiKey' => $apiKey]);

        if (! $instance instanceof $capability) {
            throw new InvalidArgumentException("AI provider [{$provider}] does not support ".class_basename($capability).'.');
        }

        return $instance;
    }
}
