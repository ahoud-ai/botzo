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
use Modules\IntelliReply\Services\AIResponseService;

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

            Log::channel('intellireply')->warning('intellireply.chat_suggestion.failed', [
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






}
