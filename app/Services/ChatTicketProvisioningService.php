<?php

namespace App\Services;

use App\Models\ChatLog;
use App\Models\ChatTicket;
use App\Models\ChatTicketLog;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class ChatTicketProvisioningService
{
    public function __construct(private readonly int $organizationId)
    {
    }

    public function ensureForContact(int $contactId, bool $reopenClosed = true): ?ChatTicket
    {
        $settings = $this->ticketSettings();

        if (!($settings['active'] ?? false)) {
            return null;
        }

        return DB::transaction(function () use ($contactId, $reopenClosed, $settings) {
            $ticket = ChatTicket::where('contact_id', $contactId)->first();

            if (!$ticket) {
                $ticket = new ChatTicket();
                $ticket->contact_id = $contactId;
                $ticket->assigned_to = ($settings['auto_assignment'] ?? false)
                    ? $this->resolveAssignee()
                    : null;
                $ticket->status = 'open';
                $ticket->created_at = now();
                $ticket->updated_at = now();
                $ticket->save();

                $ticketLogId = ChatTicketLog::insertGetId([
                    'contact_id' => $contactId,
                    'description' => __('Conversation was opened'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                ChatLog::insert([
                    'contact_id' => $contactId,
                    'entity_type' => 'ticket',
                    'entity_id' => $ticketLogId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $ticket;
            }

            if ($ticket->status === 'closed' && $reopenClosed) {
                if (($settings['reassign_reopened_chats'] ?? false) === true) {
                    $ticket->assigned_to = ($settings['auto_assignment'] ?? false)
                        ? $this->resolveAssignee()
                        : null;
                }

                $ticket->status = 'open';
                $ticket->updated_at = now();
                $ticket->save();

                $ticketLogId = ChatTicketLog::insertGetId([
                    'contact_id' => $contactId,
                    'description' => __('Conversation was moved from closed to open'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                ChatLog::insert([
                    'contact_id' => $contactId,
                    'entity_type' => 'ticket',
                    'entity_id' => $ticketLogId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $ticket;
        });
    }

    private function ticketSettings(): array
    {
        $metadata = Organization::where('id', $this->organizationId)->value('metadata');
        $settings = $metadata ? json_decode($metadata, true) : [];

        return is_array($settings['tickets'] ?? null) ? $settings['tickets'] : [];
    }

    private function resolveAssignee(): ?int
    {
        $teams = Team::query()
            ->where('organization_id', $this->organizationId)
            ->whereNull('deleted_at')
            ->get(['user_id']);

        if ($teams->isEmpty()) {
            return null;
        }

        $ticketCounts = ChatTicket::query()
            ->join('contacts', 'contacts.id', '=', 'chat_tickets.contact_id')
            ->where('contacts.organization_id', $this->organizationId)
            ->whereIn('chat_tickets.assigned_to', $teams->pluck('user_id'))
            ->selectRaw('chat_tickets.assigned_to, COUNT(chat_tickets.id) as aggregate')
            ->groupBy('chat_tickets.assigned_to')
            ->pluck('aggregate', 'chat_tickets.assigned_to');

        $team = $teams
            ->sortBy(fn (Team $team) => (int) ($ticketCounts[$team->user_id] ?? 0))
            ->first();

        return $team?->user_id;
    }
}
