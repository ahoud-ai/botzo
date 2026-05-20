<?php

namespace App\Models;

use App\Helpers\DateTimeHelper;
use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Propaganistas\LaravelPhone\PhoneNumber;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Contact extends Model {
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $guarded = [];
    protected $appends = ['full_name', 'formatted_phone_number'];
    protected $dates = ['deleted_at'];
    public $timestamps = false;

    public function getCreatedAtAttribute($value)
    {
        return DateTimeHelper::convertToOrganizationTimezone($value)->toDateTimeString();
    }

    public function getUpdatedAtAttribute($value)
    {
        return DateTimeHelper::convertToOrganizationTimezone($value)->toDateTimeString();
    }

    public function getAllContacts($organizationId, $searchTerm)
    {
        $query = $this->with('contactGroups')
            ->where('organization_id', $organizationId)
            ->where('deleted_at', null);
        
        // Check if user can only view assigned contacts
        $permissionService = new \App\Services\PermissionService();
        $isOwner = $permissionService->isOwner($organizationId);
        $canViewAll = $permissionService->can('contacts.view_all', $organizationId);
        $canViewAssignedOnly = $permissionService->can('contacts.view_assigned_only', $organizationId);
        
        // Owners always see all contacts, bypass view_assigned_only restriction
        // If user has both view_all AND view_assigned_only (and is not owner), filter by assigned contacts only
        // If user has only view_all (without view_assigned_only), show all contacts
        if (!$isOwner && $canViewAll && $canViewAssignedOnly) {
            $columns = $this->getContactColumnsForGroupBy();
            $query->select($columns)
                  ->leftJoin('chat_tickets', 'contacts.id', '=', 'chat_tickets.contact_id')
                  ->where('chat_tickets.assigned_to', auth()->user()->id)
                  ->groupBy($columns);
        }
        
        // Apply search filter if search term is provided
        if ($searchTerm) {
            $query->where(function ($query) use ($searchTerm) {
                $query->where('contacts.first_name', 'like', '%' . $searchTerm . '%')
                ->orWhere('contacts.last_name', 'like', '%' . $searchTerm . '%')
                
                // Split the search term into parts and check for matches in both columns
                ->orWhere(function ($subQuery) use ($searchTerm) {
                    $searchParts = explode(' ', $searchTerm);
                    if (count($searchParts) > 1) {
                        $subQuery->where('contacts.first_name', 'like', '%' . $searchParts[0] . '%')
                                ->where('contacts.last_name', 'like', '%' . $searchParts[1] . '%');
                    }
                })
                
                // Match phone or email
                ->orWhere('contacts.phone', 'like', '%' . $searchTerm . '%')
                ->orWhere('contacts.email', 'like', '%' . $searchTerm . '%');
            });
        }
        
        return $query->orderByDesc('contacts.is_favorite')
            ->orderByDesc('contacts.created_at')
            ->orderBy('contacts.id')
            ->paginate(10);
    }

    public function getAllContactGroups($organizationId)
    {
        return ContactGroup::where('organization_id', $organizationId)->whereNull('deleted_at')->get();
    }

    public function countContacts($organizationId)
    {
        return $this->where('organization_id', $organizationId)->whereNull('deleted_at')->count();
    }

    public function contactGroups()
    {
        return $this->belongsToMany(ContactGroup::class, 'contact_contact_group', 'contact_id', 'contact_group_id')
            ->using(ContactContactGroup::class)
            ->withTimestamps();
    }

    public function notes()
    {
        return $this->hasMany(ChatNote::class, 'contact_id')->orderBy('created_at', 'desc');
    }

    public function chats()
    {
        return $this->hasMany(Chat::class, 'contact_id')->orderBy('created_at', 'asc');
    }

    public function lastChat()
    {
        return $this->hasOne(Chat::class, 'contact_id')->with('media')->orderBy('id', 'desc');
    }

    public function lastInboundChat()
    {
        return $this->hasOne(Chat::class, 'contact_id')
                    ->where('type', 'inbound')
                    ->with('media')
                    ->orderBy('id', 'desc');
    }

    public function ticket()
    {
        return $this->hasOne(ChatTicket::class, 'contact_id');
    }

    public function chatLogs()
    {
        return $this->hasMany(ChatLog::class);
    }

    public function contactsWithChats($organizationId, $searchTerm = null, $ticketingActive = false, $ticketState = null, $sortDirection = 'asc', $role = 'owner', $allowAgentsViewAllChats = true, $unreadOnly = false, $agentId = null, ?string $channel = null)
    {
        // Execute query directly - no caching needed since query is optimized with indexes
        // Caching paginator objects causes PDO serialization issues
        return $this->executeContactsWithChatsQuery($organizationId, $searchTerm, $ticketingActive, $ticketState, $sortDirection, $role, $allowAgentsViewAllChats, $unreadOnly, $agentId, $channel);
    }
    
    /**
     * Get all contact columns for GROUP BY clause
     * Required for MySQL ONLY_FULL_GROUP_BY mode compliance
     */
    private function getContactColumnsForGroupBy()
    {
        return [
            'contacts.id',
            'contacts.uuid',
            'contacts.organization_id',
            'contacts.first_name',
            'contacts.last_name',
            'contacts.phone',
            'contacts.email',
            'contacts.avatar',
            'contacts.address',
            'contacts.is_favorite',
            'contacts.created_by',
            'contacts.created_at',
            'contacts.updated_at',
            'contacts.deleted_at',
            'contacts.latest_chat_created_at'
        ];
    }

    private function applyChatTicketFilters($query, $organizationId, $ticketingActive, $ticketState, $role = 'owner', $allowAgentsViewAllChats = true, $agentId = null)
    {
        $permissionService = new \App\Services\PermissionService();
        $isOwner = $permissionService->isOwner($organizationId);
        $canViewAllChats = $permissionService->can('chats.view_all', $organizationId);
        $canViewAssignedOnlyChats = $permissionService->can('chats.view_assigned_only', $organizationId);
        $joinedTickets = false;

        $joinTickets = function () use ($query, &$joinedTickets) {
            if (! $joinedTickets) {
                $query->leftJoin('chat_tickets', 'contacts.id', '=', 'chat_tickets.contact_id');
                $joinedTickets = true;
            }
        };

        if (! $isOwner && ! $canViewAllChats && ! $canViewAssignedOnlyChats) {
            $query->whereRaw('1 = 0');

            return $joinedTickets;
        }

        if (! $ticketingActive) {
            if (! $isOwner && $canViewAssignedOnlyChats) {
                $query->whereRaw('1 = 0');
            }

            return $joinedTickets;
        }

        if ($ticketState === 'unassigned') {
            $joinTickets();
            $query->whereNull('chat_tickets.assigned_to');
        } elseif ($ticketState !== null && $ticketState !== 'all') {
            $joinTickets();
            $query->where('chat_tickets.status', $ticketState);
        }

        if ($agentId !== null) {
            $joinTickets();
            $query->where('chat_tickets.assigned_to', $agentId);
        }

        if (! $isOwner && $canViewAssignedOnlyChats) {
            $joinTickets();
            $query->where('chat_tickets.assigned_to', auth()->id());
        } elseif (! $isOwner && strtolower((string) $role) === 'agent' && $allowAgentsViewAllChats === false) {
            $joinTickets();
            $query->where('chat_tickets.assigned_to', auth()->id());
        }

        return $joinedTickets;
    }

    private function applyConversationChannelFilter($query, int $organizationId, ?string $channel): void
    {
        if ($channel === null || $channel === '' || $channel === 'all') {
            return;
        }

        if ($channel === 'whatsapp') {
            $chatChannelExpression = "CASE WHEN JSON_VALID(chats.metadata) THEN JSON_UNQUOTE(JSON_EXTRACT(chats.metadata, '$.channel')) ELSE NULL END";
            $query->whereHas('chats', function ($chatQuery) use ($organizationId, $chatChannelExpression) {
                $chatQuery->where('organization_id', $organizationId)
                    ->whereNull('deleted_at')
                    ->where(function ($scope) use ($chatChannelExpression) {
                        $scope->whereRaw($chatChannelExpression.' = ?', ['whatsapp'])
                            ->orWhereNull('chats.metadata');
                    });
            });

            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function executeContactsWithChatsQuery($organizationId, $searchTerm = null, $ticketingActive = false, $ticketState = null, $sortDirection = 'desc', $role = 'owner', $allowAgentsViewAllChats = true, $unreadOnly = false, $agentId = null, ?string $channel = null)
    {
        $contactColumns = $this->getContactColumnsForGroupBy();

        // Simplified query: Just list all contacts with latest_chat_created_at, ordered by it (respects sortDirection parameter)
        $query = $this->newQuery()
            ->where('contacts.organization_id', $organizationId)
            ->whereNotNull('contacts.latest_chat_created_at')
            ->whereNull('contacts.deleted_at')
            ->with(['lastChat', 'lastInboundChat', 'ticket.user'])
            ->select($contactColumns);

        if ($this->applyChatTicketFilters($query, $organizationId, $ticketingActive, $ticketState, $role, $allowAgentsViewAllChats, $agentId)) {
            $query->groupBy($contactColumns);
        }

        $this->applyConversationChannelFilter($query, (int) $organizationId, $channel);

        // Search filter
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('contacts.first_name', 'like', "%$searchTerm%")
                    ->orWhere('contacts.last_name', 'like', "%$searchTerm%")
                    ->orWhereRaw("CONCAT(contacts.first_name, ' ', contacts.last_name) LIKE ?", ["%$searchTerm%"])
                    ->orWhere('contacts.phone', 'like', "%$searchTerm%")
                    ->orWhere('contacts.email', 'like', "%$searchTerm%");
            });
        }

        // Apply unread messages filter
        if ($unreadOnly) {
            $query->whereHas('chats', function ($q) use ($organizationId) {
                $q->where('organization_id', $organizationId)
                  ->where('type', 'inbound')
                  ->whereNull('deleted_at')
                  ->where('is_read', 0);
            });
        }

        return $query->orderBy('contacts.latest_chat_created_at', $sortDirection)
            ->paginate(10);
    }

    /**
     * Sync latest_chat_created_at for all contacts that have chats but missing this value
     * Updates in UTC timezone
     */
    private function syncLatestChatCreatedAt($organizationId)
    {
        // Find contacts that have chats but don't have latest_chat_created_at set
        // or have chats newer than their latest_chat_created_at
        // Need to include latest_chat_created_at in SELECT to use it in HAVING clause
        $contactsToUpdate = DB::select("
            SELECT c.id, MAX(ch.created_at) as max_chat_date, c.latest_chat_created_at
            FROM contacts c
            INNER JOIN chats ch ON c.id = ch.contact_id
            WHERE c.organization_id = ?
            AND ch.organization_id = ?
            AND c.deleted_at IS NULL
            AND ch.deleted_at IS NULL
            GROUP BY c.id, c.latest_chat_created_at
            HAVING MAX(ch.created_at) > COALESCE(c.latest_chat_created_at, '1970-01-01 00:00:00')
            OR c.latest_chat_created_at IS NULL
        ", [$organizationId, $organizationId]);

        if (empty($contactsToUpdate)) {
            return;
        }

        // Update each contact's latest_chat_created_at with their actual latest chat (in UTC)
        foreach ($contactsToUpdate as $contactData) {
            if ($contactData->max_chat_date) {
                // Ensure the timestamp is in UTC format
                $utcTimestamp = \Carbon\Carbon::parse($contactData->max_chat_date)->utc()->format('Y-m-d H:i:s');
                
                DB::table('contacts')
                    ->where('id', $contactData->id)
                    ->update(['latest_chat_created_at' => $utcTimestamp]);
            }
        }
    }

        /*$query = $this->newQuery()
            ->where('contacts.organization_id', $organizationId)
            ->whereNotNull('contacts.latest_chat_created_at')
            ->whereNull('contacts.deleted_at')
            ->with(['lastChat', 'lastInboundChat'])
            ->select('contacts.*')
            ->orderBy('contacts.latest_chat_created_at', $sortDirection);

        if($ticketingActive){
            // Conditional join with chat_tickets table and comparison with ticketState
            if ($ticketState === 'unassigned') {
                $query->leftJoin('chat_tickets', 'contacts.id', '=', 'chat_tickets.contact_id')
                    ->whereNull('chat_tickets.assigned_to');
            } elseif ($ticketState !== null && $ticketState !== 'all') {
                $query->leftJoin('chat_tickets', 'contacts.id', '=', 'chat_tickets.contact_id')
                    ->where('chat_tickets.status', $ticketState);
            } else if($ticketState === 'all'){
                $query->leftJoin('chat_tickets', 'contacts.id', '=', 'chat_tickets.contact_id');
            }

            if($role == 'agent' && $allowAgentsViewAllChats == false){
                $query->where(function($q) {
                    $q->where('chat_tickets.assigned_to', auth()->user()->id);
                });
            }
        }

        // Include the search term in the query if provided
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('contacts.first_name', 'like', '%' . $searchTerm . '%')
                ->orWhere('contacts.last_name', 'like', '%' . $searchTerm . '%')
                
                // Split the search term into parts and check for matches in both columns
                ->orWhere(function ($subQuery) use ($searchTerm) {
                    $searchParts = explode(' ', $searchTerm);
                    if (count($searchParts) > 1) {
                        $subQuery->where('contacts.first_name', 'like', '%' . $searchParts[0] . '%')
                                ->where('contacts.last_name', 'like', '%' . $searchParts[1] . '%');
                    }
                })
                
                // Match phone or email
                ->orWhere('contacts.phone', 'like', '%' . $searchTerm . '%')
                ->orWhere('contacts.email', 'like', '%' . $searchTerm . '%');
            });
        }

        return $query->paginate(10);*/

    public function contactsWithChatsCount($organizationId, $searchTerm = null, $ticketingActive = false, $ticketState = null, $sortDirection = 'desc', $role = 'owner', $allowAgentsViewAllChats = true, $unreadOnly = false, $agentId = null, ?string $channel = null)
    {
        // Simplified count query matching the main query logic
        $query = $this->newQuery()
            ->where('contacts.organization_id', $organizationId)
            ->whereNotNull('contacts.latest_chat_created_at')
            ->whereNull('contacts.deleted_at');

        $joinedTickets = $this->applyChatTicketFilters($query, $organizationId, $ticketingActive, $ticketState, $role, $allowAgentsViewAllChats, $agentId);

        $this->applyConversationChannelFilter($query, (int) $organizationId, $channel);

        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('contacts.first_name', 'like', "%$searchTerm%")
                    ->orWhere('contacts.last_name', 'like', "%$searchTerm%")
                    ->orWhereRaw("CONCAT(contacts.first_name, ' ', contacts.last_name) LIKE ?", ["%$searchTerm%"])
                    ->orWhere('contacts.phone', 'like', "%$searchTerm%")
                    ->orWhere('contacts.email', 'like', "%$searchTerm%");
            });
        }

        if ($unreadOnly) {
            $query->whereHas('chats', function ($q) use ($organizationId) {
                $q->where('organization_id', $organizationId)
                  ->where('type', 'inbound')
                  ->whereNull('deleted_at')
                  ->where('is_read', 0);
            });
        }

        return $joinedTickets
            ? $query->distinct()->count('contacts.id')
            : $query->count();
    }

    public function getFirstNameAttribute()
    {
        $firstName = $this->attributes['first_name'] ?? '';
        $firstName = $this->decodeUnicodeBytes($firstName);

        return $firstName;
    }

    public function getLastNameAttribute()
    {
        $lastName = $this->attributes['last_name'] ?? '';
        $lastName = $this->decodeUnicodeBytes($lastName);

        return $lastName;
    }

    public function getFullNameAttribute()
    {
        $firstName = $this->attributes['first_name'] ?? '';
        $lastName = $this->attributes['last_name'] ?? '';

        // Convert byte sequences to Unicode characters
        $firstName = $this->decodeUnicodeBytes($firstName);
        $lastName = $this->decodeUnicodeBytes($lastName);

        // Return the full name combining first name and last name
        return trim($firstName . ' ' . $lastName);

        //return "{$this->first_name} {$this->last_name}";
    }

    public function getFormattedPhoneNumberAttribute($value)
    {
        $phone = $this->phone;

        if ($phone === null || $phone === '') {
            return '';
        }

        // Only format if the phone number starts with '+'
        if (strpos($phone, '+') === 0) {
            try {
                return phone($phone)->formatInternational();
            } catch (\Exception $e) {
                // Fallback: return the raw phone if formatting fails
                return $phone;
            }
        }

        // If not international, just return as-is
        return $phone;
    }

    protected function decodeUnicodeBytes($value)
    {
        if ($value === null || $value === '') {
            return '';
        }

        return preg_replace_callback('/\\\\x([0-9A-F]{2})/i', function ($matches) {
            return chr(hexdec($matches[1]));
        }, (string) $value);
    }
}
