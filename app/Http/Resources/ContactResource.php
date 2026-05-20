<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        $data['unread_messages'] = $this->chats()
            ->where('type', 'inbound')
            ->whereNull('deleted_at')
            ->where('is_read', 0)
            ->count();

        $data['reply_context'] = [
            'selected_channel' => 'whatsapp',
            'reply_window_open' => $this->whatsappReplyWindowOpen(),
            'can_reply' => $this->whatsappReplyWindowOpen(),
            'can_send_text' => $this->whatsappReplyWindowOpen(),
            'can_send_media' => $this->whatsappReplyWindowOpen(),
            'can_send_template' => filled($this->phone),
        ];

        return $data;
    }

    private function whatsappReplyWindowOpen(): bool
    {
        if (! $this->relationLoaded('lastInboundChat') || ! $this->lastInboundChat) {
            return false;
        }

        $createdAt = $this->lastInboundChat->created_at;
        if (! $createdAt) {
            return false;
        }

        $createdAt = $createdAt instanceof \Carbon\CarbonInterface
            ? $createdAt
            : \Illuminate\Support\Carbon::parse($createdAt);

        return $createdAt->greaterThan(now()->subHours(24));
    }
}
