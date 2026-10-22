<?php

namespace App\Services\IntelliReply\Channels;

use App\Models\Contact;

/**
 * Where an automatic AI reply is delivered (WhatsApp today). The reply pipeline
 * decides what to say; the channel only delivers it.
 */
interface AiReplyChannel
{
    /**
     * @return bool true when the message was accepted for delivery
     */
    public function sendText(Contact $contact, string $text): bool;

    /**
     * Deliver $text as a voice message.
     *
     * @return bool false when the voice message could not be sent (the caller then sends text)
     */
    public function sendVoice(Contact $contact, array $organizationMetadata, string $apiKey, ?string $keySource, string $text): bool;
}
