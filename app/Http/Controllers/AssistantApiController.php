<?php

namespace App\Http\Controllers;

use App\Models\OrganizationApiKey;
use App\Models\WebWidgetSession;
use App\Services\SubscriptionService;
use App\Services\WebWidget\AssistantConversationService;
use App\Support\DeveloperApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * The Mochi API: apps and websites talk to the organization's assistant and build their own chat
 * screen. Conversations show in the inbox, where the team can answer too.
 */
class AssistantApiController extends Controller
{
    public function __construct(private AssistantConversationService $conversations)
    {
    }

    public function open(Request $request): JsonResponse
    {
        $key = $this->key($request);
        $validator = Validator::make($request->all(), [
            'user' => [$key->isPublic() ? 'nullable' : 'required', 'array'],
            'user.id' => [$key->isPublic() ? 'nullable' : 'required', 'string', 'max:191'],
            'user.name' => ['nullable', 'string', 'max:100'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);
        if ($validator->fails()) {
            return DeveloperApiResponse::validation($validator->errors());
        }
        if ($blocked = $this->blocked((int) $key->organization_id)) {
            return $blocked;
        }

        $opened = $this->conversations->open(
            $key,
            $key->isPublic() ? null : (string) $request->input('user.id'),
            $request->input('user.name'),
            $request->input('locale'),
            (string) $request->header('X-Conversation-Token', ''),
            $request->ip()
        );
        if (isset($opened['limited'])) {
            return DeveloperApiResponse::error(429, __('Too many new conversations from this device. Please try again later.'), 'too_many_conversations');
        }

        return response()->json(array_filter([
            'statusCode' => $opened['created'] ? 201 : 200,
            'conversation_id' => $opened['session']->uuid,
            'conversation_token' => $opened['token'],
            'messages' => $this->conversations->messages($opened['session']),
        ], fn ($value) => $value !== null), $opened['created'] ? 201 : 200);
    }

    public function send(Request $request, string $conversation): JsonResponse
    {
        $key = $this->key($request);
        $session = $this->conversation($request, $key, $conversation);
        if (! $session) {
            return DeveloperApiResponse::notFound(__('Conversation not found.'), 'conversation_not_found');
        }

        $validator = Validator::make($request->all(), [
            'text' => ['required', 'string', 'max:'.(int) config('web_widget.limits.message_max_length', 500)],
            'wait' => ['nullable', 'boolean'],
        ]);
        if ($validator->fails()) {
            return DeveloperApiResponse::validation($validator->errors());
        }
        if ($blocked = $this->blocked((int) $key->organization_id)) {
            return $blocked;
        }

        // Control characters have no place in a chat message.
        $text = trim((string) preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}]/u', '', (string) $request->input('text')));
        if ($text === '') {
            return DeveloperApiResponse::validation(['text' => [__('The message is empty.')]]);
        }

        $wait = $request->boolean('wait', true);
        $received = $this->conversations->receive($session, $text, $wait);
        if (isset($received['limited'])) {
            return DeveloperApiResponse::error(429, __('Messages are being sent too fast. Please wait a moment.'), 'too_many_messages');
        }

        $messageId = (int) $received['chat']->id;

        return response()->json([
            'statusCode' => $wait ? 200 : 202,
            'message_id' => $messageId,
            'replies' => $wait ? $this->conversations->messages($session->refresh(), $messageId) : [],
            // No automatic answer: the team took over, someone was asked for, or a limit was reached.
            'awaiting_team' => $wait && ! $received['replied'],
        ], $wait ? 200 : 202);
    }

    public function messages(Request $request, string $conversation): JsonResponse
    {
        $key = $this->key($request);
        $session = $this->conversation($request, $key, $conversation);
        if (! $session) {
            return DeveloperApiResponse::notFound(__('Conversation not found.'), 'conversation_not_found');
        }

        $after = $request->query('after');

        return response()->json([
            'statusCode' => 200,
            'messages' => $this->conversations->messages($session, is_numeric($after) ? (int) $after : null),
        ]);
    }

    private function key(Request $request): OrganizationApiKey
    {
        return $request->attributes->get('developer_api_key');
    }

    private function conversation(Request $request, OrganizationApiKey $key, string $conversation): ?WebWidgetSession
    {
        return $this->conversations->find($key, $conversation, (string) $request->header('X-Conversation-Token', ''));
    }

    private function blocked(int $organizationId): ?JsonResponse
    {
        if (! SubscriptionService::isSubscriptionActive($organizationId)) {
            return DeveloperApiResponse::forbidden(__('Please renew or subscribe to a plan to continue!'), 'subscription_inactive');
        }
        if (! $this->conversations->assistantActive($organizationId)) {
            return DeveloperApiResponse::forbidden(__('The AI assistant is turned off for this account.'), 'assistant_not_active');
        }

        return null;
    }
}
