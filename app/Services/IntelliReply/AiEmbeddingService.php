<?php

namespace App\Services\IntelliReply;

use App\Services\Ai\AiProviderManager;
use RuntimeException;
use Throwable;

/**
 * Embeddings for the knowledge base: the organization's embedding model with the
 * configured fallbacks, the document chunker, and cosine similarity.
 */
class AiEmbeddingService
{
    public const DEFAULT_MODEL = 'text-embedding-3-small';

    public const MAX_CHUNK_LENGTH = 1600;

    /**
     * @return array{0:array,1:string} the vector and the model that produced it
     */
    public function embed(array $organizationMetadata, string $apiKey, string $text): array
    {
        $client = app(AiProviderManager::class)->embeddings(data_get($organizationMetadata, 'ai.embedding_provider'), $apiKey);
        $lastError = null;

        foreach ($this->modelCandidates($organizationMetadata) as $model) {
            try {
                return [$client->embed($text, $model), $model];
            } catch (Throwable $exception) {
                $lastError = $exception;
            }
        }

        throw $lastError ?? new RuntimeException(__('Unable to generate embeddings'));
    }

    public function isModelAccessError(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'does not have access to model')
            || str_contains($message, 'model_not_found')
            || str_contains($message, 'invalid model');
    }

    /**
     * Split a document into chunks of at most MAX_CHUNK_LENGTH characters, keeping paragraphs together.
     * Stored document embeddings are indexed by these chunks, so the output must stay stable.
     *
     * @return list<string>
     */
    public function chunk(string $content): array
    {
        $paragraphs = preg_split("/(\r\n|\n|\r){2,}/", $content) ?: [];
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $segment = trim((string) $paragraph);
            if ($segment === '') {
                continue;
            }

            if (mb_strlen($current.' '.$segment) <= self::MAX_CHUNK_LENGTH) {
                $current = trim($current.' '.$segment);
                continue;
            }

            if ($current !== '') {
                $chunks[] = $current;
            }

            if (mb_strlen($segment) <= self::MAX_CHUNK_LENGTH) {
                $current = $segment;
                continue;
            }

            foreach (mb_str_split($segment, self::MAX_CHUNK_LENGTH) as $piece) {
                $piece = trim($piece);
                if ($piece !== '') {
                    $chunks[] = $piece;
                }
            }
            $current = '';
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        if (empty($chunks)) {
            $chunks[] = mb_substr($content, 0, self::MAX_CHUNK_LENGTH);
        }

        return $chunks;
    }

    /**
     * Cosine similarity in [-1, 1]; higher means closer.
     */
    public function similarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $length = min(count($a), count($b));

        for ($i = 0; $i < $length; $i++) {
            $x = (float) $a[$i];
            $y = (float) $b[$i];
            $dot += $x * $y;
            $normA += $x ** 2;
            $normB += $y ** 2;
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    /**
     * @return list<string>
     */
    public function modelCandidates(array $organizationMetadata): array
    {
        $preferred = data_get($organizationMetadata, 'ai.embedding_model');
        if (! is_string($preferred) || trim($preferred) === '') {
            $default = config('intellireply.default_embedding_model', self::DEFAULT_MODEL);
            $preferred = is_string($default) && trim($default) !== '' ? $default : self::DEFAULT_MODEL;
        }

        $candidates = [trim($preferred)];
        $fallbacks = ((array) config('intellireply.embedding_model_fallbacks', []))[$preferred] ?? [];
        $allowlist = config('intellireply.embedding_models', [self::DEFAULT_MODEL]);

        foreach (array_merge(is_array($fallbacks) ? $fallbacks : [], is_array($allowlist) ? $allowlist : []) as $model) {
            if (is_string($model) && trim($model) !== '') {
                $candidates[] = trim($model);
            }
        }

        return array_values(array_unique($candidates));
    }
}
