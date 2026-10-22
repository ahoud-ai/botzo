<?php

namespace App\Services\IntelliReply\Channels;

use App\Models\Contact;
use App\Services\IntelliReply\AiVoiceReplyService;
use App\Services\WebWidget\WebWidgetAssistantContext;
use App\Services\WhatsappService;
use Closure;

/**
 * Delivers AI replies through the organization's WhatsApp number. The WhatsApp client
 * is built only when something is actually sent.
 */
class WhatsappReplyChannel implements AiReplyChannel
{
    private ?WhatsappService $whatsapp = null;

    /**
     * @param  Closure(): WhatsappService  $whatsappFactory
     */
    public function __construct(private readonly Closure $whatsappFactory)
    {
    }

    public function sendText(Contact $contact, string $text): bool
    {
        // Widget action markers mean nothing on WhatsApp.
        $text = WebWidgetAssistantContext::extractActions($text)['text'];

        return ($this->whatsapp()->sendMessage($contact->uuid, $text)->success ?? false) === true;
    }

    public function sendVoice(Contact $contact, array $organizationMetadata, string $apiKey, ?string $keySource, string $text): bool
    {
        return app(AiVoiceReplyService::class)->send($contact, $this->whatsapp(), $organizationMetadata, $apiKey, $keySource, $text);
    }

    private function whatsapp(): WhatsappService
    {
        return $this->whatsapp ??= ($this->whatsappFactory)();
    }
}
