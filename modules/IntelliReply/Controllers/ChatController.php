<?php

namespace Modules\IntelliReply\Controllers;

use App\Helpers\CustomHelper;
use App\Http\Controllers\Controller as BaseController;
use App\Models\ChatTicket;
use App\Models\Contact;
use App\Models\Organization;
use App\Services\IntelliReply\AiUsageLimiterService;
use App\Services\IntelliReply\AiKeyResolver;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\IntelliReply\Models\Document;
use Modules\IntelliReply\Services\AIResponseService;
use OpenAI;

class ChatController extends BaseController
{
    private const DEFAULT_EMBEDDING_MODEL = 'text-embedding-3-small';

    public function suggestion(Request $request, AIResponseService $aiResponseService): JsonResponse
    {
        $organizationId = (int) session()->get('current_organization');
        $this->checkPermission('chats.view_all', $organizationId);
        $this->ensureAiAssistantEnabled($organizationId);

        $payload = $request->validate([
            'contact' => ['required', 'string', 'max:120'],
        ]);

        $contact = Contact::query()
            ->where('uuid', (string) $payload['contact'])
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->first();

        if (!$contact || !$this->canViewContact($contact, $organizationId)) {
            return response()->json([
                'success' => false,
                'message' => __('Chat not found.'),
            ], 404);
        }

        $organization = Organization::findOrFail($organizationId);
        $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];
        $metadata = is_array($metadata) ? $metadata : [];

        if (!$this->isChatSuggestionEnabled($metadata)) {
            return response()->json([
                'success' => false,
                'message' => __('AI assistant suggestions are not active for this workspace.'),
            ], 403);
        }

        $keyResolver = app(AiKeyResolver::class);
        $keyBundle = $keyResolver->resolveForOrganization(
            $metadata,
            data_get($metadata, 'ai.key_source', 'auto'),
            $organizationId
        );

        if (empty($keyBundle['key'])) {
            return response()->json([
                'success' => false,
                'message' => __('AI assistant is not configured.'),
            ], 422);
        }

        $usageLimiter = app(AiUsageLimiterService::class);
        $keySource = $keyBundle['source'] ?? null;
        if (!$usageLimiter->canUseText($organizationId, $keySource)) {
            return response()->json([
                'success' => false,
                'message' => __('AI response limit reached for this subscription.'),
                'usage' => $usageLimiter->getSnapshot($organizationId),
            ], 429);
        }

        try {
            $suggestion = $aiResponseService->suggestReply($organizationId, $contact, $keyBundle);
        } catch (\Throwable $exception) {
            report($exception);

            Log::warning('intellireply.chat_suggestion.failed', [
                'organization_id' => $organizationId,
                'contact_id' => $contact->id,
                'error_code' => 'SUGGESTION_FAILED',
                'key_source' => $keySource,
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Unable to generate a suggestion right now. Please try again.'),
            ], 422);
        }

        if (($suggestion['success'] ?? false) !== true || trim((string) ($suggestion['text'] ?? '')) === '') {
            return response()->json([
                'success' => false,
                'message' => $suggestion['message'] ?? __('No useful suggestion was found for this conversation.'),
            ], 422);
        }

        if (!$usageLimiter->consumeText($organizationId, $keySource)) {
            return response()->json([
                'success' => false,
                'message' => __('AI response limit reached for this subscription.'),
                'usage' => $usageLimiter->getSnapshot($organizationId),
            ], 429);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'text' => trim((string) $suggestion['text']),
            ],
            'usage' => $usageLimiter->getSnapshot($organizationId),
        ]);
    }

    public function chat(Request $request): JsonResponse
    {
        $organizationId = (int) session()->get('current_organization');
        $this->checkPermission('automations.view_all', $organizationId);
        $this->ensureAiAssistantEnabled($organizationId);

        $payload = $request->validate([
            'query' => ['required', 'string', 'max:4000'],
        ]);

        $organization = Organization::findOrFail($organizationId);
        $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];
        $metadata = is_array($metadata) ? $metadata : [];

        $keyResolver = app(AiKeyResolver::class);
        $keyBundle = $keyResolver->resolveForOrganization(
            $metadata,
            data_get($metadata, 'ai.key_source', 'auto'),
            $organizationId
        );
        $apiKey = $keyBundle['key'] ?? null;
        if (!$apiKey) {
            return response()->json([
                'response' => __('AI assistant is not configured.'),
            ], 422);
        }

        $client = OpenAI::client($apiKey);
        $query = trim((string) $payload['query']);

        try {
            [$queryEmbedding] = $this->createEmbeddingWithFallback($client, $query, $metadata);
        } catch (\Throwable $e) {
            return response()->json([
                'response' => $this->isEmbeddingModelAccessError($e)
                    ? __('Selected embedding model is not available for this OpenAI key. Choose another embedding model or use a key with access.')
                    : __('Unable to process your request right now. Please try again.'),
            ], 422);
        }

        $documents = Document::where('organization_id', $organizationId)->get();
        $closestDocument = null;
        $closestDistance = PHP_FLOAT_MAX;

        foreach ($documents as $document) {
            $documentEmbeddings = json_decode($document->embeddings, true);
            if (!is_array($documentEmbeddings)) {
                continue;
            }

            foreach ($documentEmbeddings as $documentEmbedding) {
                if (!is_array($documentEmbedding)) {
                    continue;
                }

                $distance = $this->cosineDistance($queryEmbedding, $documentEmbedding);
                if ($distance < $closestDistance) {
                    $closestDistance = $distance;
                    $closestDocument = $document;
                }
            }
        }

        if (!$closestDocument) {
            return response()->json([
                'response' => __('Sorry but I don\'t have any information about this.'),
            ]);
        }

        $aiModel = data_get($metadata, 'ai.model', 'gpt-4o-mini');
        $maxTokens = (int) data_get($metadata, 'ai.max_tokens', 512);
        $temperature = (float) data_get($metadata, 'ai.temperature', 0.7);

        $chatResponse = $client->chat()->create([
            'model' => $aiModel,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "You are a customer support AI assistant. Only answer from the provided documentation. If answer is missing, respond: Sorry I don't have information about this.",
                ],
                [
                    'role' => 'user',
                    'content' => 'documentation: '.$closestDocument->content."\n\nquestion: ".$query,
                ],
            ],
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
        ]);

        $content = data_get($chatResponse->toArray(), 'choices.0.message.content');
        if (!is_string($content) || trim($content) === '') {
            $content = __('Sorry but I don\'t have any information about this.');
        }

        return response()->json([
            'response' => $content,
        ]);
    }

    private function ensureAiAssistantEnabled(int $organizationId): void
    {
        if (!CustomHelper::isModuleEnabled('AI Assistant', $organizationId)) {
            abort(404);
        }
    }

    private function isChatSuggestionEnabled(array $metadata): bool
    {
        $active = data_get($metadata, 'ai.active');
        if ($active !== null) {
            return (bool) $active;
        }

        return (bool) data_get($metadata, 'ai.ai_chat_form_active', false);
    }

    private function canViewContact(Contact $contact, int $organizationId): bool
    {
        $permissionService = app(PermissionService::class);
        if ($permissionService->isOwner($organizationId)) {
            return true;
        }

        if (!$permissionService->can('chats.view_assigned_only', $organizationId)) {
            return true;
        }

        return ChatTicket::query()
            ->where('contact_id', $contact->id)
            ->where('assigned_to', auth()->id())
            ->exists();
    }

    private function cosineDistance(array $vecA, array $vecB): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $length = min(count($vecA), count($vecB));

        for ($i = 0; $i < $length; $i++) {
            $a = (float) $vecA[$i];
            $b = (float) $vecB[$i];
            $dotProduct += $a * $b;
            $normA += $a ** 2;
            $normB += $b ** 2;
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 1.0;
        }

        return 1 - ($dotProduct / (sqrt($normA) * sqrt($normB)));
    }

    private function resolveEmbeddingModel(array $metadata): string
    {
        $model = data_get($metadata, 'ai.embedding_model');
        if (is_string($model) && trim($model) !== '') {
            return $model;
        }

        $default = config('intellireply.default_embedding_model', self::DEFAULT_EMBEDDING_MODEL);
        return is_string($default) && trim($default) !== ''
            ? $default
            : self::DEFAULT_EMBEDDING_MODEL;
    }

    private function embeddingModelCandidates(array $metadata): array
    {
        $preferred = $this->resolveEmbeddingModel($metadata);
        $allowlist = config('intellireply.embedding_models', [self::DEFAULT_EMBEDDING_MODEL]);
        if (!is_array($allowlist)) {
            $allowlist = [self::DEFAULT_EMBEDDING_MODEL];
        }

        $fallbackMap = config('intellireply.embedding_model_fallbacks', []);
        if (!is_array($fallbackMap)) {
            $fallbackMap = [];
        }

        $candidates = [$preferred];
        $preferredFallbacks = $fallbackMap[$preferred] ?? [];
        if (is_array($preferredFallbacks)) {
            foreach ($preferredFallbacks as $fallbackModel) {
                if (is_string($fallbackModel) && trim($fallbackModel) !== '') {
                    $candidates[] = trim($fallbackModel);
                }
            }
        }

        foreach ($allowlist as $allowedModel) {
            if (is_string($allowedModel) && trim($allowedModel) !== '') {
                $candidates[] = trim($allowedModel);
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @return array{0:array,1:string}
     */
    private function createEmbeddingWithFallback($client, string $input, array $metadata): array
    {
        $lastError = null;

        foreach ($this->embeddingModelCandidates($metadata) as $model) {
            try {
                $response = $client->embeddings()->create([
                    'input' => $input,
                    'model' => $model,
                ]);

                return [$response->embeddings[0]->embedding, $model];
            } catch (\Throwable $e) {
                $lastError = $e;
                continue;
            }
        }

        throw $lastError ?? new \RuntimeException('Unable to generate embeddings');
    }

    private function isEmbeddingModelAccessError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'does not have access to model')
            || str_contains($message, 'model_not_found')
            || str_contains($message, 'invalid model');
    }
}
