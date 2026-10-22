<?php

namespace App\Services\Webhooks;

use App\Models\Chat;
use App\Models\Contact;

/**
 * The "data" part of each webhook event, kept small and stable: what an app needs to react,
 * without Botzo's internal ids or fields.
 */
class WebhookEvents
{
    public static function messageReceived(Contact $contact, Chat $chat, array $message): array
    {
        $type = (string) ($message['type'] ?? 'unknown');
        $data = [
            'message_id' => $chat->wam_id,
            'channel' => 'whatsapp',
            'from' => $contact->phone,
            'contact' => self::contact($contact),
            'type' => $type,
            'text' => null,
            'button' => null,
            'media' => null,
            'timestamp' => self::time($message['timestamp'] ?? null),
        ];

        if ($type === 'text') {
            $data['text'] = (string) data_get($message, 'text.body', '');
        } elseif ($type === 'button') {
            $data['button'] = ['id' => data_get($message, 'button.payload'), 'title' => data_get($message, 'button.text')];
        } elseif ($type === 'interactive') {
            $reply = data_get($message, 'interactive.button_reply') ?? data_get($message, 'interactive.list_reply') ?? [];
            $data['button'] = ['id' => $reply['id'] ?? null, 'title' => $reply['title'] ?? null];
        } elseif (in_array($type, ['image', 'video', 'audio', 'document', 'sticker'], true)) {
            $media = (array) ($message[$type] ?? []);
            $data['media'] = [
                'type' => $type,
                'mime_type' => $media['mime_type'] ?? null,
                'caption' => $media['caption'] ?? null,
                'filename' => $media['filename'] ?? null,
                'voice' => (bool) ($media['voice'] ?? false),
            ];
        }

        return $data;
    }

    public static function messageStatus(Chat $chat, ?Contact $contact, array $status): array
    {
        $error = data_get($status, 'errors.0');

        return [
            'message_id' => $chat->wam_id,
            'channel' => 'whatsapp',
            'to' => $contact?->phone,
            'status' => (string) ($status['status'] ?? ''),
            'error' => $error ? [
                'code' => $error['code'] ?? null,
                'title' => $error['title'] ?? null,
                'message' => $error['message'] ?? data_get($error, 'error_data.details'),
            ] : null,
            'timestamp' => self::time($status['timestamp'] ?? null),
        ];
    }

    public static function handoff(Contact $contact, string $reason): array
    {
        return [
            'channel' => $contact->isWebVisitor() ? 'web' : 'whatsapp',
            'contact' => self::contact($contact),
            'reason' => $reason,
            'timestamp' => now('UTC')->toIso8601String(),
        ];
    }

    private static function contact(Contact $contact): array
    {
        return [
            'name' => trim($contact->first_name.' '.$contact->last_name) ?: null,
            'phone' => $contact->isWebVisitor() ? null : $contact->phone,
        ];
    }

    private static function time($unix): string
    {
        return is_numeric($unix)
            ? \Carbon\Carbon::createFromTimestamp((int) $unix, 'UTC')->toIso8601String()
            : now('UTC')->toIso8601String();
    }
}
