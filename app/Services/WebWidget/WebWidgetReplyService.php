<?php

namespace App\Services\WebWidget;

use App\Models\Chat;
use App\Models\Contact;
use App\Models\WebWidget;
use App\Services\IntelliReply\AiConversationPauseService;
use App\Services\IntelliReply\AiHumanHandoffService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\IntelliReply\Services\AIResponseService;

/**
 * Answers a website visitor right away, in the request. Same rules as WhatsApp (team takeover
 * pause, "talk to a person", AI limits), minus WhatsApp flows and keyword replies, plus a daily
 * cap per widget.
 */
class WebWidgetReplyService
{
    /**
     * @return bool true when an automatic message was sent to the visitor
     */
    public function reply(WebWidget $widget, Chat $chat): bool
    {
        $lock = Cache::lock(
            sprintf('auto-reply:%d:%d', $chat->organization_id, $chat->contact_id),
            max(10, (int) config('auto_replies.lock_seconds', 90))
        );

        try {
            return (bool) $lock->block(max(1, (int) config('auto_replies.lock_wait_seconds', 30)), fn () => $this->replyNow($widget, $chat));
        } catch (LockTimeoutException) {
            Log::channel('intellireply')->warning('intellireply.web_widget.reply_busy', [
                'organization_id' => $chat->organization_id,
                'contact_id' => $chat->contact_id,
            ]);

            return false;
        }
    }

    private function replyNow(WebWidget $widget, Chat $chat): bool
    {
        $contact = Contact::find($chat->contact_id);
        if (! $contact || app(AiConversationPauseService::class)->isPaused($contact)) {
            return false;
        }

        if (app(AiHumanHandoffService::class)->handleIfRequested($chat)) {
            return true;
        }

        $counterKey = sprintf('web-widget:replies:%d:%s', $widget->id, now()->toDateString());
        if ((int) Cache::get($counterKey, 0) >= max(0, (int) $widget->daily_reply_limit)) {
            Log::channel('intellireply')->warning('intellireply.limit.reached', [
                'organization_id' => $chat->organization_id,
                'contact_id' => $chat->contact_id,
                'error_code' => 'WEB_WIDGET_DAILY_LIMIT',
            ]);

            return false;
        }

        $replied = (bool) app(AIResponseService::class)->handleAIResponse($chat, '');

        if ($replied) {
            Cache::add($counterKey, 0, now()->endOfDay());
            Cache::increment($counterKey);
        }

        return $replied;
    }
}
