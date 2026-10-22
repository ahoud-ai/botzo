<?php

namespace App\Services\WebWidget;

use App\Models\Chat;
use App\Models\Contact;
use App\Models\User;
use App\Models\WebWidget;
use App\Models\WebWidgetSession;
use App\Services\ChatService;
use App\Services\IntelliReply\AiDisclosureService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Visitor sessions and messages of the website chat widget. Every limit here protects the
 * organization's AI quota on a public page; the inbox sees widget chats like WhatsApp ones.
 */
class WebWidgetService
{
    public const LIMIT_SESSIONS = 'sessions';
    public const LIMIT_TOO_FAST = 'too_fast';
    public const LIMIT_HOURLY = 'hourly';

    /**
     * Resume the visitor's session from their token, or open a new one.
     *
     * @return array{session: WebWidgetSession, token: ?string}|array{limited: string}
     *               token is returned only for a new session (the visitor keeps it)
     */
    public function openSession(WebWidget $widget, ?string $token, ?string $ip, string $origin, ?string $pageUrl, ?string $locale): array
    {
        $session = $token ? $this->findSession($widget, $token) : null;
        if ($session) {
            $session->forceFill(['last_seen_at' => now(), 'page_url' => $this->clip($pageUrl, 500)])->save();

            return ['session' => $session, 'token' => null];
        }

        $ipHash = $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null;
        $limiterKey = 'web-widget:sessions:'.($ipHash ?? 'unknown');
        if (! RateLimiter::attempt($limiterKey, max(1, (int) config('web_widget.limits.sessions_per_ip_per_hour', 10)), fn () => true, 3600)) {
            return ['limited' => self::LIMIT_SESSIONS];
        }

        $token = Str::random(64);
        $session = WebWidgetSession::create([
            'web_widget_id' => $widget->id,
            'organization_id' => $widget->organization_id,
            'contact_id' => null,
            'token_hash' => WebWidgetSession::hashToken($token),
            'ip_hash' => $ipHash,
            'origin' => $this->clip($origin, 255),
            'page_url' => $this->clip($pageUrl, 500),
            'locale' => in_array($locale, ['ar', 'en'], true) ? $locale : null,
            'last_seen_at' => now(),
        ]);

        return ['session' => $session, 'token' => $token];
    }

    public function findSession(WebWidget $widget, string $token): ?WebWidgetSession
    {
        return WebWidgetSession::where('token_hash', WebWidgetSession::hashToken($token))
            ->where('web_widget_id', $widget->id)
            ->first();
    }

    /**
     * Store the visitor's message (creating their contact on the first one) and answer it.
     *
     * @return array{chat: Chat, replied: bool}|array{limited: string}
     */
    public function receive(WebWidgetSession $session, string $text): array
    {
        $limited = $this->messageLimit($session);
        if ($limited !== null) {
            return ['limited' => $limited];
        }

        $contact = $this->contactFor($session);
        // The widget itself tells visitors an AI assistant answers, so no disclosure message is sent.
        app(AiDisclosureService::class)->markDisclosed((int) $session->organization_id, (int) $contact->id);
        (new ChatService((int) $session->organization_id))->handleTicketAssignment($contact->id);
        $chat = app(WebWidgetMessageStore::class)->storeText($contact, $text, 'inbound');
        $session->forceFill(['last_seen_at' => now()])->save();

        $replyAllowed = ! SubscriptionService::isSubscriptionLimitReachedForInboundMessages($session->organization_id)
            && ! SubscriptionService::isSubscriptionFeatureLimitReached($session->organization_id, 'message_limit');

        $replied = $replyAllowed && app(WebWidgetReplyService::class)->reply($session->widget, $chat);

        return ['chat' => $chat, 'replied' => $replied];
    }

    /**
     * The visitor's conversation after message $afterId (all of it when null), oldest first.
     *
     * @return list<array{id:int, from:string, name:?string, text:string, at:string}>
     */
    public function messages(WebWidgetSession $session, ?int $afterId = null): array
    {
        if (! $session->contact_id) {
            return [];
        }

        $query = Chat::where('contact_id', $session->contact_id)
            ->where('organization_id', $session->organization_id)
            ->whereNull('deleted_at');

        if ($afterId !== null) {
            $query->where('id', '>', $afterId)->orderBy('id');
        } else {
            $query->orderByDesc('id')->take(max(1, (int) config('web_widget.history_limit', 50)));
        }

        $chats = $query->get(['id', 'type', 'user_id', 'metadata', 'created_at'])->sortBy('id');
        $agentNames = User::whereIn('id', $chats->pluck('user_id')->filter()->unique())->pluck('first_name', 'id');

        return $chats->map(function (Chat $chat) use ($agentNames) {
            $metadata = json_decode((string) $chat->metadata, true);
            $text = is_array($metadata) && ($metadata['type'] ?? null) === 'text' ? (string) data_get($metadata, 'text.body', '') : '';
            if ($text === '') {
                return null;
            }

            $from = $chat->type === 'inbound' ? 'visitor' : ($chat->user_id ? 'agent' : 'assistant');

            return [
                'id' => (int) $chat->id,
                'from' => $from,
                'name' => $from === 'agent' ? ($agentNames[$chat->user_id] ?? null) : null,
                'text' => $text,
                'at' => Carbon::parse($chat->getRawOriginal('created_at'), 'UTC')->toIso8601String(),
            ];
        })->filter()->values()->all();
    }

    private function messageLimit(WebWidgetSession $session): ?string
    {
        $key = 'web-widget:messages:'.$session->id;
        $interval = max(1, (int) config('web_widget.limits.message_min_interval_seconds', 3));

        if (! RateLimiter::attempt($key.':burst', 1, fn () => true, $interval)) {
            return self::LIMIT_TOO_FAST;
        }

        if (! RateLimiter::attempt($key.':hour', max(1, (int) config('web_widget.limits.messages_per_hour', 30)), fn () => true, 3600)) {
            return self::LIMIT_HOURLY;
        }

        return null;
    }

    private function contactFor(WebWidgetSession $session): Contact
    {
        $contact = $session->contact_id
            ? Contact::where('id', $session->contact_id)->where('organization_id', $session->organization_id)->first()
            : null;

        if ($contact) {
            return $contact;
        }

        $contact = Contact::create([
            'first_name' => __('Website visitor').' '.random_int(1000, 9999),
            'phone' => null,
            'organization_id' => $session->organization_id,
            'channel' => Contact::CHANNEL_WEB,
            'ai_assistance_enabled' => 1,
            'created_by' => 0,
            'created_at' => Carbon::now('UTC'),
            'updated_at' => Carbon::now('UTC'),
        ]);

        $session->forceFill(['contact_id' => $contact->id])->save();

        return $contact;
    }

    private function clip(?string $value, int $length): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $length);
    }
}
