<?php

namespace App\Services\WebWidget;

use App\Events\NewChatEvent;
use App\Models\Chat;
use App\Models\ChatLog;
use App\Models\Contact;
use Carbon\Carbon;

/**
 * Stores website widget messages exactly like WhatsApp ones (a text chat, its chat log and the
 * inbox broadcast), so the inbox, history and the AI context read them unchanged.
 */
class WebWidgetMessageStore
{
    public function storeText(Contact $contact, string $text, string $direction, ?int $userId = null): Chat
    {
        $chat = Chat::create([
            'organization_id' => $contact->organization_id,
            'wam_id' => null,
            'contact_id' => $contact->id,
            'type' => $direction === 'inbound' ? 'inbound' : 'outbound',
            'user_id' => $userId,
            'metadata' => json_encode([
                'type' => 'text',
                'text' => ['body' => $text],
                'channel' => Contact::CHANNEL_WEB,
                'provider' => 'web_widget',
            ], JSON_UNESCAPED_UNICODE),
            'status' => 'delivered',
            'created_at' => Carbon::now('UTC'),
        ]);

        $chatLogId = ChatLog::insertGetId([
            'contact_id' => $contact->id,
            'entity_type' => 'chat',
            'entity_id' => $chat->id,
            'created_at' => Carbon::now('UTC'),
        ]);

        $chatLog = ChatLog::where('id', $chatLogId)->whereNull('deleted_at')->first();
        if ($chatLog) {
            event(new NewChatEvent([['type' => 'chat', 'value' => $chatLog->relatedEntities]], $contact->organization_id));
        }

        return Chat::with('contact', 'media')->where('id', $chat->id)->first();
    }
}
