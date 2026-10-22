<?php

namespace App\Services\WebWidget;

use App\Models\Contact;
use App\Models\Organization;
use App\Models\OrganizationApiKey;
use App\Models\WebWidget;
use App\Models\WebWidgetSession;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Conversations with Mochi opened through the developer API. They reuse the website widget's
 * sessions, reply engine and inbox (source "api"), so the assistant behaves the same everywhere.
 */
class AssistantConversationService
{
    public const SOURCE_API = 'api';

    public function __construct(private WebWidgetService $widgets)
    {
    }

    public function assistantActive(int $organizationId): bool
    {
        $metadata = json_decode((string) Organization::whereKey($organizationId)->value('metadata'), true) ?: [];

        return (bool) data_get($metadata, 'ai.active', false);
    }

    /**
     * The organization's widget record carries the assistant's settings and daily reply cap. An
     * organization that never set up the website widget gets one that stays switched off.
     */
    public function widgetFor(int $organizationId): WebWidget
    {
        return WebWidget::firstOrCreate(
            ['organization_id' => $organizationId],
            ['public_key' => WebWidget::generatePublicKey(), 'is_enabled' => false, 'allowed_domains' => [], 'settings' => []]
        );
    }

    /**
     * Secret keys identify the user (one conversation per user id); public keys get a new
     * conversation per device, resumed with the conversation token they were given.
     *
     * @return array{session: WebWidgetSession, token: ?string, created: bool}|array{limited: string}
     */
    public function open(OrganizationApiKey $key, ?string $userId, ?string $userName, ?string $locale, ?string $token, ?string $ip): array
    {
        $widget = $this->widgetFor((int) $key->organization_id);

        if (! $key->isPublic()) {
            $session = WebWidgetSession::where('web_widget_id', $widget->id)->where('external_user_id', $userId)->first();
            $created = false;
            if (! $session) {
                $session = $this->create($widget, $key, $userId, $locale, Str::random(64), null);
                $created = true;
            }
            $this->nameContact($session, $key, $userName);

            return ['session' => $session, 'token' => null, 'created' => $created];
        }

        if ($token !== null && $token !== '') {
            $session = $this->find($key, null, $token);
            if ($session) {
                return ['session' => $session, 'token' => null, 'created' => false];
            }
        }

        $ipHash = $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null;
        $limit = max(1, (int) config('web_widget.limits.sessions_per_ip_per_hour', 10));
        if (! RateLimiter::attempt('assistant-api:sessions:'.$key->id.':'.($ipHash ?? 'unknown'), $limit, fn () => true, 3600)) {
            return ['limited' => WebWidgetService::LIMIT_SESSIONS];
        }

        $token = Str::random(64);
        $session = $this->create($widget, $key, null, $locale, $token, $ipHash);
        $this->nameContact($session, $key, $userName);

        return ['session' => $session, 'token' => $token, 'created' => true];
    }

    /** A conversation of this organization, by its id (secret keys) or by its token (public keys). */
    public function find(OrganizationApiKey $key, ?string $conversationId, ?string $token): ?WebWidgetSession
    {
        $query = WebWidgetSession::where('organization_id', $key->organization_id)->where('source', self::SOURCE_API);

        if ($key->isPublic()) {
            if ($token === null || $token === '' || strlen($token) > 128) {
                return null;
            }
            $query->where('token_hash', WebWidgetSession::hashToken($token));
            if ($conversationId !== null) {
                $query->where('uuid', $conversationId);
            }

            return $query->first();
        }

        return $conversationId === null ? null : $query->where('uuid', $conversationId)->first();
    }

    public function receive(WebWidgetSession $session, string $text, bool $wait): array
    {
        return $this->widgets->receive($session, $text, null, null, ! $wait);
    }

    /** The conversation as the app sees it: "user", "assistant" or "agent" messages after $afterId. */
    public function messages(WebWidgetSession $session, ?int $afterId = null): array
    {
        return array_map(fn (array $message) => [
            'id' => $message['id'],
            'from' => $message['from'] === 'visitor' ? 'user' : $message['from'],
            'name' => $message['name'] ?? null,
            'text' => $message['text'],
            'at' => $message['at'],
        ], $this->widgets->messages($session, $afterId));
    }

    private function create(WebWidget $widget, OrganizationApiKey $key, ?string $userId, ?string $locale, string $token, ?string $ipHash): WebWidgetSession
    {
        return WebWidgetSession::create([
            'web_widget_id' => $widget->id,
            'organization_id' => $widget->organization_id,
            'source' => self::SOURCE_API,
            'external_user_id' => $userId,
            'organization_api_key_id' => $key->id,
            'contact_id' => null,
            'token_hash' => WebWidgetSession::hashToken($token),
            'ip_hash' => $ipHash,
            'origin' => null,
            'locale' => in_array($locale, ['ar', 'en'], true) ? $locale : null,
            'last_seen_at' => now(),
        ]);
    }

    // The inbox shows which app the conversation came from, and the user's name when the app sent one.
    private function nameContact(WebWidgetSession $session, OrganizationApiKey $key, ?string $userName): void
    {
        $contact = $this->widgets->contactFor($session);
        $metadata = json_decode((string) $contact->metadata, true);
        $metadata = is_array($metadata) ? $metadata : [];
        $appName = trim((string) $key->name) ?: 'API';
        $name = trim((string) $userName);

        if (($metadata['source_app'] ?? null) === $appName && ($name === '' || $contact->first_name === $name)) {
            return;
        }

        // Without a name, the inbox says "App user 1234" rather than the widget's "Website visitor 1234".
        $fallback = str_starts_with((string) $contact->first_name, __('Website visitor'))
            ? __('App user').' '.random_int(1000, 9999)
            : $contact->first_name;

        $metadata['source_app'] = $appName;
        $contact->forceFill([
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'first_name' => $name !== '' ? mb_substr($name, 0, 100) : $fallback,
        ])->save();
    }
}
