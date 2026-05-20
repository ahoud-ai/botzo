<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use Illuminate\Validation\ValidationException;
use DB;
use Validator;

class TicketService
{
    private function scopedTicket(string $ticketUuid): Ticket
    {
        $query = Ticket::where('uuid', $ticketUuid);

        if (auth()->user()?->role === 'user') {
            $query->where('user_id', auth()->id());
        }

        return $query->firstOrFail();
    }

    public function store(object $request){
        Ticket::create([
            'reference' => 'SUP-' . sprintf('%06d', Ticket::count() + 1) . '-' . now()->format('ymd'),
            'user_id' => auth()->user()->role === 'user' ? auth()->user()->id : $request->user,
            'category_id' => $request->category,
            'subject' => $request->subject,
            'message' => $request->message,
            'assigned_to' => auth()->user()->role === 'user' ? null : auth()->user()->id,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    public function assignTicket(object $request, $ticketUuid){
        $ticket = $this->scopedTicket($ticketUuid);
        $ticket->update([
            'assigned_to' => $request->user,
            'updated_at' => now()
        ]);
    }

    public function changeStatus(object $request, $ticketUuid){
        $ticket = $this->scopedTicket($ticketUuid);
        $ticket->update([
            'status' => $request->status,
            'updated_at' => now()
        ]);
    }

    public function changePriority(object $request, $ticketUuid){
        $ticket = $this->scopedTicket($ticketUuid);
        $ticket->update([
            'priority' => $request->priority,
            'updated_at' => now()
        ]);
    }

    public function comment(object $request, $ticketUuid){
        $ticket = $this->scopedTicket($ticketUuid);
        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->user()->id,
            'message' => $request->message,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    public function markAsRead($ticketUuid){
        $ticket = $this->scopedTicket($ticketUuid);
        TicketComment::where('ticket_id', $ticket->id)->update([
            'seen' => 1
        ]);
    }
}
