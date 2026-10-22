<?php

namespace App\Services\IntelliReply\Channels;

use App\Models\Contact;
use App\Services\WebWidget\WebWidgetMessageStore;

/**
 * Delivers AI replies to a website widget visitor: the reply is stored and the open widget
 * picks it up. Voice replies are not available on the website yet.
 */
class WebReplyChannel implements AiReplyChannel
{
    public function sendText(Contact $contact, string $text): bool
    {
        app(WebWidgetMessageStore::class)->storeText($contact, $text, 'outbound');

        return true;
    }

    public function sendVoice(Contact $contact, array $organizationMetadata, string $apiKey, ?string $keySource, string $text): bool
    {
        return false;
    }
}
