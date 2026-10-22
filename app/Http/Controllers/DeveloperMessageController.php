<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\ChatStatusLog;
use App\Support\DeveloperApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeveloperMessageController extends Controller
{
    /**
     * The delivery status of a message sent through the API, by the message_id the send returned.
     */
    public function show(Request $request, string $messageId): JsonResponse
    {
        $chat = Chat::where('organization_id', $request->organization)
            ->where('wam_id', $messageId)
            ->where('type', 'outbound')
            ->whereNull('deleted_at')
            ->with('contact:id,phone')
            ->first();

        if (! $chat) {
            return DeveloperApiResponse::notFound(__('Message not found.'), 'message_not_found');
        }

        $lastLog = ChatStatusLog::where('chat_id', $chat->id)->latest('id')->first();

        return response()->json([
            'statusCode' => 200,
            'data' => [
                'message_id' => $chat->wam_id,
                'phone' => $chat->contact?->phone,
                'status' => $chat->status,
                'error' => $chat->status === 'failed' ? $this->failure($lastLog) : null,
                'sent_at' => $chat->getRawOriginal('created_at'),
                'status_updated_at' => $lastLog?->getRawOriginal('created_at') ?? $chat->getRawOriginal('created_at'),
            ],
        ]);
    }

    // Meta reports a failed delivery in the status webhook ("errors"); a refused send keeps our own fields.
    private function failure(?ChatStatusLog $log): ?array
    {
        $metadata = $log ? json_decode((string) $log->metadata, true) : null;
        if (! is_array($metadata)) {
            return null;
        }

        return [
            'code' => data_get($metadata, 'errors.0.code', data_get($metadata, 'error_code')),
            'message' => data_get($metadata, 'errors.0.message', data_get($metadata, 'errors.0.title', data_get($metadata, 'message'))),
        ];
    }
}
