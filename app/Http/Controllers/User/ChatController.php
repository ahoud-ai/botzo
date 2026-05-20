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

class ChatController extends BaseController
{
    private function chatService()
    {
        return new ChatService(session()->get('current_organization'));
    }

    private function chatAccess(): ChatAccessService
    {
        return app(ChatAccessService::class);
    }

    private function ensureCanOpenChatArea(int $organizationId): void
    {
        if (! $this->chatAccess()->canOpenChatArea($organizationId)) {
            abort(403, __('You do not have permission to perform this action.'));
        }
    }

    public function index(Request $request, $uuid = null)
    {
        $organizationId = session()->get('current_organization');
        $this->ensureCanOpenChatArea((int) $organizationId);
        
        return $this->chatService()->getChatList($request, $uuid, $request->query('search'));
    }

    public function updateChatSortDirection(Request $request)
    {
        $request->session()->put('chat_sort_direction', $request->sort);

        return Redirect::back();
    }

    public function sendMessage(Request $request)
    {
        $this->checkPermission('chats.reply', session()->get('current_organization'));

        return $this->chatService()->sendMessage($request);
    }

    public function sendTemplateMessage(Request $request, $uuid)
    {
        $this->checkPermission('chats.reply', session()->get('current_organization'));

        $res = $this->chatService()->sendTemplateMessage($request, $uuid);

        return Redirect::back()->with(
            'status', [
                'type' => $res->success === true ? 'success' : 'error', 
                'message' => $res->success === true ? __('Message sent successfully!') : $res->message,
                'res' => $res
            ]
        );
    }

    public function deleteChats($uuid)
    {
        $organizationId = session()->get('current_organization');
        $this->checkPermission('chats.delete', $organizationId);
        
        $this->chatService()->clearContactChat($uuid);

        return Redirect::back()->with(
            'status', [
                'type' => 'success', 
                'message' => __('Chat cleared successfully!')
            ]
        );
    }

    public function loadMoreMessages(Request $request, $contactId)
    {
        $organizationId = session()->get('current_organization');
        $this->ensureCanOpenChatArea((int) $organizationId);
        
        $page = $request->query('page', 1);
        $messages = $this->chatService()->getChatMessages($contactId, $page);
        
        return response()->json($messages);
    }

    public function getMedia($id)
    {
        $organizationId = session()->get('current_organization');
        $this->ensureCanOpenChatArea((int) $organizationId);

        $chat = Chat::with(['media', 'contact'])
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($id) {
                $query->where('id', $id)
                    ->orWhere('uuid', $id);
            })
            ->firstOrFail();

        if (! $chat->contact || ! $this->chatAccess()->canViewContact($chat->contact, (int) $organizationId)) {
            abort(404, __('Chat not found.'));
        }

        if (!$chat->media) {
            abort(404, __('Media not found.'));
        }

        return response()->json([
            'success' => true,
            'media' => $chat->media,
        ]);
    }

    public function getAllUuids(Request $request)
    {
        try {
            $request->validate([
                'status' => 'nullable|string|in:all,open,closed,unassigned'
            ]);

            $status = $request->query('status', 'all');
            $organizationId = session()->get('current_organization');
            $this->ensureCanOpenChatArea((int) $organizationId);
            
            if (!$organizationId) {
                return response()->json([
                    'success' => false,
                    'message' => __('Organization not found'),
                    'uuids' => []
                ], 400);
            }
            
            $user = auth()->user();
            if (!$user || !$user->id) {
                return response()->json([
                    'success' => false,
                    'message' => __('User not authenticated'),
                    'uuids' => []
                ], 401);
            }
            
            $permissionService = new \App\Services\PermissionService();
            $currentRole = $permissionService->getCurrentRole($organizationId);
            $role = $currentRole ? strtolower($currentRole->name) : 'owner'; // Fallback to owner for safety
        
        // Get all contacts for this status without pagination
        $query = \App\Models\Contact::where('contacts.organization_id', $organizationId)
            ->whereNotNull('contacts.latest_chat_created_at')
            ->whereNull('contacts.deleted_at')
            ->select('contacts.*')
            ->selectSub(function ($subquery) use ($organizationId) {
                $subquery->from('chats')
                    ->selectRaw('MAX(created_at)')
                    ->whereColumn('chats.contact_id', 'contacts.id')
                    ->whereNull('chats.deleted_at')
                    ->where('chats.organization_id', $organizationId);
            }, 'last_chat_created_at');

        // Apply ticketing conditions if active
        $config = \App\Models\Organization::where('id', $organizationId)->first();
        $ticketingActive = false;
        $allowAgentsViewAllChats = true;
        
        if ($config && $config->metadata) {
            $settings = json_decode($config->metadata);
            if ($settings && isset($settings->tickets) && isset($settings->tickets->active) && $settings->tickets->active === true) {
                $ticketingActive = true;
                $allowAgentsViewAllChats = isset($settings->tickets->allow_agents_to_view_all_chats) ? $settings->tickets->allow_agents_to_view_all_chats : true;

                $query->leftJoin('chat_tickets', 'contacts.id', '=', 'chat_tickets.contact_id');

                if ($status === 'unassigned') {
                    $query->whereNull('chat_tickets.assigned_to');
                } elseif ($status !== null && $status !== 'all') {
                    $query->where('chat_tickets.status', $status);
                }

                $canViewAssignedOnly = $permissionService->can('chats.view_assigned_only', $organizationId);

                if (!$permissionService->isOwner($organizationId) && $canViewAssignedOnly && $user) {
                    $query->where('chat_tickets.assigned_to', $user->id);
                }

                $query->groupBy('contacts.id');
            }
        }

        if (!$ticketingActive && !$permissionService->isOwner($organizationId) && $permissionService->can('chats.view_assigned_only', $organizationId)) {
            return response()->json([
                'success' => true,
                'uuids' => [],
                'count' => 0
            ]);
        }

        $query->groupBy('contacts.id');
        $query->orderBy('last_chat_created_at', 'desc');
        
        try {
            $contacts = $query->get();
            
            // Filter out any contacts that might not have a uuid
            $uuids = $contacts->filter(function($contact) {
                return !is_null($contact->uuid);
            })->pluck('uuid')->toArray();
            
            return response()->json([
                'success' => true,
                'uuids' => $uuids,
                'count' => count($uuids)
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in getAllUuids: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'status' => $status,
                'organizationId' => $organizationId
            ]);
            
            return response()->json([
                'success' => false,
                'message' => __('An error occurred while fetching contacts: :error', ['error' => $e->getMessage()]),
                'uuids' => []
            ], 500);
        }
        } catch (\Exception $e) {
            \Log::error('Error in getAllUuids (outer): ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => __('An error occurred: :error', ['error' => $e->getMessage()]),
                'uuids' => []
            ], 500);
        }
    }

    public function bulkAction(Request $request)
    {
        $organizationId = session()->get('current_organization');
        
        $request->validate([
            'action' => 'required|in:assign,clear,mark_open,mark_closed',
            'contact_uuids' => 'required|array',
            'contact_uuids.*' => 'required|string',
            'user_id' => 'nullable|integer|exists:users,id'
        ]);

        $contactUuids = $request->contact_uuids;
        $action = $request->action;
        $requestedContactCount = count(array_unique($contactUuids));
        $contacts = Contact::whereIn('uuid', $contactUuids)
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->get();

        $contacts = $this->chatAccess()->visibleContacts($contacts, (int) $organizationId);

        if ($contacts->isEmpty() || $contacts->count() !== $requestedContactCount) {
            return response()->json([
                'success' => false,
                'message' => __('One or more contacts were not found')
            ], 404);
        }

        try {
            switch ($action) {
                case 'assign':
                    $this->checkPermission('chats.assign', $organizationId);
                    if (!$request->user_id) {
                        return response()->json([
                            'success' => false,
                            'message' => __('User ID is required for assignment')
                        ], 400);
                    }
                    $this->bulkAssign($contacts, $request->user_id, $organizationId);
                    break;

                case 'clear':
                    $this->checkPermission('chats.delete', $organizationId);
                    $this->bulkClear($contacts);
                    break;

                case 'mark_open':
                case 'mark_closed':
                    $this->checkPermission('chats.change_status', $organizationId);
                    $this->bulkMarkStatus($contacts, $action === 'mark_open' ? 'open' : 'closed');
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => __('Action completed successfully')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('An error occurred: :error', ['error' => $e->getMessage()])
            ], 500);
        }
    }

    private function bulkAssign($contacts, $userId, $organizationId)
    {
        $user = User::find($userId);
        if (!$user) {
            throw new \Exception(__('User not found'));
        }

        $team = Team::where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->first();

        if (!$team) {
            throw new \Exception(__('User does not belong to this organization'));
        }

        // Create or update tickets for all contacts
        foreach ($contacts as $contact) {
            // Ensure ticket exists, create if it doesn't
            $ticket = ChatTicket::firstOrCreate(
                ['contact_id' => $contact->id],
                [
                    'assigned_to' => $userId,
                    'status' => 'open',
                    'updated_at' => now(),
                ]
            );
            $previousAssignedTo = $ticket->wasRecentlyCreated ? null : $ticket->assigned_to;
            
            // Update assignment if ticket already existed
            if ($ticket->wasRecentlyCreated === false) {
                $ticket->update([
                    'assigned_to' => $userId,
                    'updated_at' => now()
                ]);
            }

            // Create ticket log
            $ticketId = ChatTicketLog::insertGetId([
                'contact_id' => $contact->id,
                'description' => __('Conversation was assigned to :name', ['name' => trim($user->first_name . ' ' . $user->last_name)]),
                'created_at' => now()
            ]);

            // Create chat log entry
            $chatLogId = ChatLog::insertGetId([
                'contact_id' => $contact->id,
                'entity_type' => 'ticket',
                'entity_id' => $ticketId,
                'created_at' => now()
            ]);
            $this->broadcastTicketLog($chatLogId, (int) $organizationId, [$userId, $previousAssignedTo]);
        }
    }

    private function bulkClear($contacts)
    {
        foreach ($contacts as $contact) {
            $this->chatService()->clearContactChat($contact->uuid);
        }
    }

    private function bulkMarkStatus($contacts, $status)
    {
        $user = auth()->user();
        if (!$user) {
            throw new \Exception(__('User not authenticated'));
        }
        
        $contactIds = $contacts->pluck('id');
        
        $previousAssignments = ChatTicket::whereIn('contact_id', $contactIds)
            ->pluck('assigned_to', 'contact_id');

        ChatTicket::whereIn('contact_id', $contactIds)->update([
            'status' => $status,
            'assigned_to' => $user->id
        ]);

        $fromStatus = $status === 'closed' ? __('open') : __('closed');
        $toStatus = $status === 'closed' ? __('closed') : __('open');

        // Create ticket logs
        foreach ($contacts as $contact) {
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
            $this->broadcastTicketLog($chatLogId, (int) $contact->organization_id, [
                $user->id,
                $previousAssignments[$contact->id] ?? null,
            ]);
        }
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
