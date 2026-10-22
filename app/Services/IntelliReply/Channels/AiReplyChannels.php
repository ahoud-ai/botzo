<?php

namespace App\Services\IntelliReply\Channels;

use App\Models\Contact;
use App\Services\WhatsappService;
use Closure;

class AiReplyChannels
{
    /**
     * The channel a contact's automatic messages go out on: the website widget for its
     * visitors, WhatsApp for everyone else.
     *
     * @param  Closure(): WhatsappService  $whatsappFactory
     */
    public static function for(Contact $contact, Closure $whatsappFactory): AiReplyChannel
    {
        return $contact->isWebVisitor() ? new WebReplyChannel() : new WhatsappReplyChannel($whatsappFactory);
    }
}
