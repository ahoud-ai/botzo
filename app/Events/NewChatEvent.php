<?php

namespace App\Events;

use App\Models\Chat;
use App\Models\ChatTicket;
use App\Models\Contact;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class NewChatEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $chat;
    public $organizationId;
    public array $recipientUserIds;

    /**
     * Create a new event instance.
     *
     * @param mixed $chat
     * @param int $organizationId
     */
    public function __construct($chat, $organizationId, array $recipientUserIds = [])
    {
        $this->chat = $chat;
        $this->organizationId = $organizationId;
        $this->recipientUserIds = $this->normalizeRecipientUserIds($recipientUserIds);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel
     */
    public function broadcastOn()
    {
        $channels = [
            new PrivateChannel('chats.ch' . $this->organizationId),
        ];

        foreach ($this->resolvedRecipientUserIds() as $userId) {
            $channels[] = new PrivateChannel('chats.user.' . $userId);
        }

        return $channels;
    }

    /**
     * Skip broadcasting cleanly when driver is null or pusher is misconfigured.
     */
    public function broadcastWhen(): bool
    {
        $driver = (string) config('broadcasting.default', 'null');

        if ($driver === 'null') {
            return false;
        }

        if ($driver !== 'pusher') {
            return true;
        }

        return filled(config('broadcasting.connections.pusher.key'))
            && filled(config('broadcasting.connections.pusher.secret'));
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        $chat = $this->resolvePrimaryChat();
        $contact = $this->resolvePrimaryContact($chat, $this->resolvePrimaryContactId());

        return array_filter([
            'event_type' => $this->resolveEventType($chat),
            'chat' => $this->chat,
            'contact_uuid' => $this->normalizeString($contact?->uuid),
            'contact_name' => $this->normalizeString($contact?->full_name),
            'message_preview' => $this->extractMessagePreview($chat),
            'direction' => $this->normalizeString($chat?->type),
        ], static fn ($value, $key) => $key === 'chat' || $value !== null, ARRAY_FILTER_USE_BOTH);
    }

    protected function resolvePrimaryChat(): ?Chat
    {
        if (! is_array($this->chat)) {
            return null;
        }

        foreach ($this->chat as $entry) {
            if (! is_array($entry) || ($entry['type'] ?? null) !== 'chat') {
                continue;
            }

            $value = $entry['value'] ?? null;
            if ($value instanceof Chat) {
                return $value;
            }
        }

        return null;
    }

    protected function resolvedRecipientUserIds(): array
    {
        if ($this->recipientUserIds !== []) {
            return $this->recipientUserIds;
        }

        $contactId = $this->resolvePrimaryContactId();
        if (! $contactId) {
            return [];
        }

        return ChatTicket::where('contact_id', $contactId)
            ->whereNotNull('assigned_to')
            ->pluck('assigned_to')
            ->map(fn ($userId) => (int) $userId)
            ->filter(fn (int $userId) => $userId > 0)
            ->unique()
            ->values()
            ->all();
    }

    protected function resolvePrimaryContactId(): ?int
    {
        $chat = $this->resolvePrimaryChat();
        if ($chat && $chat->contact_id) {
            return (int) $chat->contact_id;
        }

        if (! is_array($this->chat)) {
            return null;
        }

        foreach ($this->chat as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $value = $entry['value'] ?? null;
            $contactId = is_array($value)
                ? ($value['contact_id'] ?? null)
                : ($value?->contact_id ?? null);

            if ($contactId) {
                return (int) $contactId;
            }
        }

        return null;
    }

    protected function normalizeRecipientUserIds(array $recipientUserIds): array
    {
        return collect($recipientUserIds)
            ->map(fn ($userId) => (int) $userId)
            ->filter(fn (int $userId) => $userId > 0)
            ->unique()
            ->values()
            ->all();
    }

    protected function resolvePrimaryContact(?Chat $chat, ?int $contactId = null)
    {
        if (! $chat) {
            return $contactId ? Contact::find($contactId) : null;
        }

        $chat->loadMissing('contact');

        return $chat->contact;
    }

    protected function resolveEventType(?Chat $chat): string
    {
        if ($chat) {
            return 'message.updated';
        }

        if (is_array($this->chat)) {
            foreach ($this->chat as $entry) {
                if (is_array($entry) && ($entry['type'] ?? null) === 'ticket') {
                    return 'ticket.updated';
                }
            }
        }

        return 'conversation.updated';
    }

    protected function extractMessagePreview(?Chat $chat): ?string
    {
        if (! $chat) {
            return null;
        }

        $metadata = $this->decodeMetadata($chat->metadata ?? null);
        if (! $metadata) {
            return null;
        }

        $candidates = [
            $metadata['text'] ?? null,
            data_get($metadata, 'text.body'),
            $metadata['body'] ?? null,
            $metadata['caption'] ?? null,
            data_get($metadata, 'image.caption'),
            data_get($metadata, 'video.caption'),
            data_get($metadata, 'document.caption'),
            data_get($metadata, 'audio.caption'),
        ];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeString($candidate);
            if ($normalized !== null) {
                return Str::limit($normalized, 160);
            }
        }

        return null;
    }

    protected function decodeMetadata($metadata): ?array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (! is_string($metadata) || trim($metadata) === '') {
            return null;
        }

        $decoded = json_decode($metadata, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function normalizeString($value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
