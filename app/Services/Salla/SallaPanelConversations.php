<?php

namespace App\Services\Salla;

use App\Models\Chat;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\SallaStore;
use App\Services\IntelliReply\AiConversationPauseService;
use App\Services\IntelliReply\HumanRequestService;
use App\Services\WebWidget\WebWidgetMessageStore;
use Illuminate\Support\Carbon;

/**
 * A store's chats with its website visitors. Every lookup starts from the store's organization, so
 * another store's conversation does not exist as far as a merchant can tell.
 */
class SallaPanelConversations
{
    private const LIST_LIMIT = 50;

    private const MESSAGE_LIMIT = 100;

    public function __construct(private readonly AiConversationPauseService $pause)
    {
    }

    /** The latest conversations, newest first. */
    public function list(SallaStore $store): array
    {
        $latest = Chat::query()
            ->where('organization_id', $store->organization_id)
            ->whereNull('deleted_at')
            ->whereIn('contact_id', Contact::query()->where('organization_id', $store->organization_id)->where('channel', Contact::CHANNEL_WEB)->select('id'))
            ->selectRaw('contact_id, MAX(id) as last_id')
            ->groupBy('contact_id')
            ->orderByDesc('last_id')
            ->limit(self::LIST_LIMIT)
            ->pluck('last_id', 'contact_id');

        $chats = Chat::query()->whereIn('id', $latest->values())->get(['id', 'type', 'user_id', 'metadata', 'created_at'])->keyBy('id');
        $contacts = Contact::query()->whereIn('id', $latest->keys())->get()->keyBy('id');

        return $latest->map(function ($lastId, $contactId) use ($chats, $contacts) {
            $chat = $chats->get($lastId);
            $contact = $contacts->get($contactId);

            return [
                'id' => (int) $contactId,
                'name' => trim((string) $contact?->first_name.' '.(string) $contact?->last_name),
                'last_message' => mb_substr($this->text($chat), 0, 120),
                'last_from' => $chat ? $this->from($chat) : null,
                'at' => $chat ? $this->time($chat) : null,
                'needs_team' => $chat?->type === 'inbound',
            ];
        })->values()->all();
    }

    public function show(SallaStore $store, int $contactId): ?array
    {
        $contact = $this->contact($store, $contactId);

        if (! $contact) {
            return null;
        }

        $chats = Chat::query()
            ->where('organization_id', $store->organization_id)
            ->where('contact_id', $contact->id)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->limit(self::MESSAGE_LIMIT)
            ->get(['id', 'type', 'user_id', 'metadata', 'created_at'])
            ->reverse();

        return [
            'id' => (int) $contact->id,
            'name' => trim((string) $contact->first_name.' '.(string) $contact->last_name),
            'assistant_paused' => $this->pause->isPaused($contact) || app(HumanRequestService::class)->blocksAssistant($contact),
            'messages' => $chats->map(fn (Chat $chat) => [
                'id' => (int) $chat->id,
                'from' => $this->from($chat),
                'text' => $this->text($chat),
                'products' => count((array) data_get(json_decode((string) $chat->metadata, true), 'widget_action.keys', [])),
                'at' => $this->time($chat),
            ])->filter(fn (array $message) => $message['text'] !== '')->values()->all(),
        ];
    }

    /** A team reply reaches the visitor's open chat, and Mochi steps aside like in the Botzo inbox. */
    public function reply(SallaStore $store, int $contactId, string $text): bool
    {
        $contact = $this->contact($store, $contactId);

        if (! $contact) {
            return false;
        }

        $this->pause->pauseForHumanReply($contact);
        app(WebWidgetMessageStore::class)->storeText($contact, $text, 'outbound', (int) Organization::query()->whereKey($store->organization_id)->value('created_by'));

        return true;
    }

    public function resumeAssistant(SallaStore $store, int $contactId): bool
    {
        $contact = $this->contact($store, $contactId);

        if (! $contact) {
            return false;
        }

        $this->pause->resume($contact);

        return true;
    }

    private function contact(SallaStore $store, int $contactId): ?Contact
    {
        return Contact::query()
            ->whereKey($contactId)
            ->where('organization_id', $store->organization_id)
            ->where('channel', Contact::CHANNEL_WEB)
            ->first();
    }

    private function from(Chat $chat): string
    {
        return $chat->type === 'inbound' ? 'visitor' : ($chat->user_id ? 'team' : 'assistant');
    }

    private function text(?Chat $chat): string
    {
        return $chat ? (string) data_get(json_decode((string) $chat->metadata, true), 'text.body', '') : '';
    }

    private function time(Chat $chat): string
    {
        return Carbon::parse($chat->getRawOriginal('created_at'), 'UTC')->toIso8601String();
    }
}
