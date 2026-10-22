<?php

namespace App\Services\IntelliReply\Channels;

use App\Models\Contact;
use App\Services\WebWidget\WebWidgetAssistantContext;
use App\Services\WebWidget\WebWidgetMessageStore;

/**
 * Delivers AI replies to a website widget visitor: the reply is stored and the open widget
 * picks it up. Voice replies are not available on the website yet.
 */
class WebReplyChannel implements AiReplyChannel
{
    public function sendText(Contact $contact, string $text): bool
    {
        $reply = WebWidgetAssistantContext::extractActions($text);
        app(WebWidgetMessageStore::class)->storeText($contact, $reply['text'], 'outbound', null, $reply['action'] ? ['widget_action' => $reply['action']] : []);

        return true;
    }

    public function sendVoice(Contact $contact, array $organizationMetadata, string $apiKey, ?string $keySource, string $text): bool
    {
        return false;
    }
}
