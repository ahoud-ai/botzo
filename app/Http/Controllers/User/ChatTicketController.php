<?php

namespace App\Http\Controllers\User;

use App\Events\NewChatEvent;
use App\Http\Controllers\Controller as BaseController;
use App\Models\AutoReply;
use App\Models\Chat;
use App\Models\ChatLog;
use App\Models\ChatTicket;
use App\Models\ChatTicketLog;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use App\Services\Chat\ChatAccessService;
use App\Services\ChatService;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Redirect;

class ChatTicketController extends BaseController
{
    private function chatAccess(): ChatAccessService
    {
        return app(ChatAccessService::class);
    }

    public function index(Request $request, $uuid = null)
    {
        //
    }

    public function update(Request $request, $uuid)
    { 
        $organizationId = session()->get('current_organization');
        $this->checkPermission('chats.change_status', $organizationId);

        $request->validate([
            'status' => 'required|in:open,closed',
        ]);
        
        $contact = Contact::where('uuid', $uuid)
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        if (! $this->chatAccess()->canViewContact($contact, (int) $organizationId)) {
            abort(404, __('Chat not found.'));
        }

        $ticket = ChatTicket::firstOrCreate(
            ['contact_id' => $contact->id],
            [
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $previousAssignedTo = $ticket->assigned_to;

        $ticket->update([
            'status' => $request->status,
            'assigned_to' => auth()->user()->id,
            'updated_at' => now(),
        ]);

        $fromStatus = $request->status === 'closed' ? __('open') : __('closed');
        $toStatus = $request->status === 'closed' ? __('closed') : __('open');

        $ticketId = ChatTicketLog::insertGetId([
            'contact_id' => $contact->id,
            'description' => __('Conversation status changed from :from to :to', ['from' => $fromStatus, 'to' => $toStatus]),
            'created_at' => now()
        ]);

        $chatLogId = ChatLog::insertGetId([
            'contact_id' => $contact->id,
            'entity_type' => 'ticket',
            'entity_id' => $ticketId,
            'created_at' => now()
        ]);
        $this->broadcastTicketLog($chatLogId, (int) $organizationId, [auth()->id(), $previousAssignedTo]);

        return Redirect::back()->with(
            'status', [
                'type' => 'success', 
                'message' => __('Status updated successfully!')
            ]
        );
    }

    public function assign(Request $request, $uuid)
    { 
        $organizationId = session()->get('current_organization');
        $this->checkPermission('chats.assign', $organizationId);

        $request->validate([
            'id' => 'nullable|integer|exists:users,id',
        ]);
        
        $contact = Contact::where('uuid', $uuid)
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        if (! $this->chatAccess()->canViewContact($contact, (int) $organizationId)) {
            abort(404, __('Chat not found.'));
        }

        $team = $request->id
            ? Team::where('organization_id', $organizationId)
                ->where('user_id', $request->id)
                ->whereNull('deleted_at')
                ->first()
            : null;
        $user = $request->id ? User::where('id', $request->id)->first() : null;
        
        if ($request->id && (!$team || !$user)) {
            return Redirect::back()->with(
                'status', [
                    'type' => 'error',
                    'message' => __('User does not belong to this organization')
                ]
            );
        }

        $ticket = ChatTicket::firstOrCreate(
            ['contact_id' => $contact->id],
            [
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $previousAssignedTo = $ticket->assigned_to;

        $ticket->update([
            'assigned_to' => $request->id,
            'updated_at' => now(),
        ]);

        if ($request->id) {

            $assignedUserName = trim($user->first_name . ' ' . $user->last_name);

            $ticketId = ChatTicketLog::insertGetId([
                'contact_id' => $contact->id,
                'description' => __('Conversation was assigned to :name', ['name' => $assignedUserName]),
                'created_at' => now()
            ]);

            $chatLogId = ChatLog::insertGetId([
                'contact_id' => $contact->id,
                'entity_type' => 'ticket',
                'entity_id' => $ticketId,
                'created_at' => now()
            ]);
            $this->broadcastTicketLog($chatLogId, (int) $organizationId, [$request->id, $previousAssignedTo]);

        } else {
            $ticketId = ChatTicketLog::insertGetId([
                'contact_id' => $contact->id,
                'description' => __('Conversation was unassigned'),
                'created_at' => now()
            ]);

            $chatLogId = ChatLog::insertGetId([
                'contact_id' => $contact->id,
                'entity_type' => 'ticket',
                'entity_id' => $ticketId,
                'created_at' => now()
            ]);
            $this->broadcastTicketLog($chatLogId, (int) $organizationId, [$previousAssignedTo]);
        }

        return Redirect::back()->with(
            'status', [
                'type' => 'success',
                'message' => $request->id ? __('Ticket assigned successfully!') : __('Ticket unassigned successfully!')
            ]
        );
    }

    private function broadcastTicketLog(int $chatLogId, int $organizationId, array $recipientUserIds = []): void
    {
        $chatLog = ChatLog::where('id', $chatLogId)
            ->where('deleted_at', null)
            ->first();

        if (! $chatLog) {
            return;
        }

        event(new NewChatEvent([
            [
                'type' => 'ticket',
                'value' => $chatLog->relatedEntities,
            ],
        ], $organizationId, $recipientUserIds));
    }
}
