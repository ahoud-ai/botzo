<?php

namespace App\Services;

use App\Events\NewChatEvent;
use App\Http\Resources\ContactResource;
use App\Helpers\CustomHelper;
use App\Models\Addon;
use App\Models\Chat;
use App\Models\ChatLog;
use App\Models\ChatMedia;
use App\Models\ChatTicket;
use App\Models\Contact;
use App\Models\ContactField;
use App\Models\ContactGroup;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\Team;
use App\Models\Template;
use App\Services\IntelliReply\AiKeyResolver;
use App\Services\IntelliReply\AiUsageLimiterService;
use App\Services\Chat\ChatAccessService;
use App\Services\SubscriptionService;
use App\Services\Whatsapp\WhatsappAccessTokenRefreshService;
use App\Services\WhatsappService;
use App\Traits\TemplateTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Validator;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\IntelliReply\Models\Document;

class ChatService
{
    use TemplateTrait;

    private $whatsappService;
    private $organizationId;

    public function __construct($organizationId)
    {
        $this->organizationId = $organizationId;
        $this->initializeWhatsappService();
    }

    private function resolveContactByUuid(?string $uuid): ?Contact
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        return Contact::where('uuid', $uuid)
            ->where('organization_id', $this->organizationId)
            ->whereNull('deleted_at')
            ->first();
    }

    private function resolveContactById($contactId): ?Contact
    {
        return Contact::where('id', $contactId)
            ->where('organization_id', $this->organizationId)
            ->whereNull('deleted_at')
            ->first();
    }

    private function contactNotFoundResponse()
    {
        return response()->json([
            'success' => false,
            'message' => __('Chat not found.'),
        ], 404);
    }

    private function ticketingActive(): bool
    {
        $metadata = Organization::where('id', $this->organizationId)->value('metadata');
        $settings = $metadata ? json_decode($metadata, true) : [];

        return data_get($settings, 'tickets.active') === true;
    }

    private function canViewContact(Contact $contact): bool
    {
        return app(ChatAccessService::class)->canViewContact($contact, (int) $this->organizationId);
    }

    private function normalizeConversationChannel(mixed $channel): ?string
    {
        $channel = strtolower(trim((string) $channel));

        return $channel === 'whatsapp' ? 'whatsapp' : null;
    }

    private function initializeWhatsappService()
    {
        $config = Organization::where('id', $this->organizationId)->first()->metadata;
        $config = $config ? json_decode($config, true) : [];
        $accessToken = app(WhatsappAccessTokenRefreshService::class)
            ->resolveTokenForOrganization((int) $this->organizationId, true);
        $apiVersion = config('graph.api_version');
        $appId = $config['whatsapp']['app_id'] ?? null;
        $phoneNumberId = $config['whatsapp']['phone_number_id'] ?? null;
        $wabaId = $config['whatsapp']['waba_id'] ?? null;

        $this->whatsappService = new WhatsappService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $this->organizationId);
    }

    public function getChatList($request, $uuid = null, $searchTerm = null)
    {
        $permissionService = new \App\Services\PermissionService();
        $currentRole = $permissionService->getCurrentRole($this->organizationId);
        $roleName = $currentRole ? strtolower($currentRole->name) : 'owner'; // Fallback to owner for safety
        $contact = new Contact;
        $config = Organization::where('id', $this->organizationId)->first();
        $agents = Team::where('organization_id', $this->organizationId)->whereNull('deleted_at')->with('user')->get();
        $sortDirection = $request->session()->get('chat_sort_direction') ?? 'desc';
        $allowAgentsToViewAllChats = true;
        $ticketingActive = false;
        $aimodule = CustomHelper::isModuleEnabled('AI Assistant', $this->organizationId);

        //Check if tickets module has been enabled
        if($config->metadata != NULL){
            $settings = json_decode($config->metadata);

            if(isset($settings->tickets) && $settings->tickets->active === true){
                $ticketingActive = true;

                //Check if agents can view all chats
                $allowAgentsToViewAllChats = $settings->tickets->allow_agents_to_view_all_chats;
            }
        }

        // Store status in session if provided in request
        if ($request->status !== null) {
            $request->session()->put('chat_status', $request->status);
        }
        
        // Determine ticket state with priority:
        // 1. Explicit status in request
        // 2. Session stored preference (preserve user's filter choice)
        // 3. If viewing a specific chat (UUID) and no session preference, check its ticket status
        // 4. Default to 'all'
        $ticketState = null;
        
        if ($request->status !== null) {
            $ticketState = $request->status;
        } else {
            // Check session first to preserve user's filter choice (unassigned, all, open, closed)
            $sessionStatus = $request->session()->get('chat_status');
            
            if ($sessionStatus !== null) {
                // Use session status - don't override with ticket status
                $ticketState = $sessionStatus;
            } elseif ($uuid !== null && $ticketingActive) {
                // Only check ticket status if there's no session preference
                // This preserves the state when opening a chat without an explicit filter
                $contactForTicket = $this->resolveContactByUuid($uuid);
                if ($contactForTicket) {
                    $ticket = ChatTicket::where('contact_id', $contactForTicket->id)->first();
                    if ($ticket) {
                        $ticketState = $ticket->status;
                        // Store this status in session for consistency
                        $request->session()->put('chat_status', $ticketState);
                    }
                }
            }
        }
        
        // Final fallback to default
        if ($ticketState === null) {
            $ticketState = 'all';
        }

        // Get filter parameters
        $unreadOnly = $request->query('unread') === '1';
        $agentId = $request->query('agent') ? (int)$request->query('agent') : null;
        $channel = $this->normalizeConversationChannel($request->query('channel'));

        // Retrieve the list of contacts with chats (returns paginated result)
        $contacts = $contact->contactsWithChats($this->organizationId, $searchTerm, $ticketingActive, $ticketState, $sortDirection, $roleName, $allowAgentsToViewAllChats, $unreadOnly, $agentId, $channel);
        // Use the paginator's built-in total count (already accounts for GROUP BY correctly)
        $rowCount = $contacts->total();
        $ticketCounts = $this->getTicketCounts($ticketingActive);

        $pusherSettings = Setting::whereIn('key', [
            'pusher_app_id',
            'pusher_app_key',
            'pusher_app_secret',
            'pusher_app_cluster',
        ])->pluck('value', 'key')->toArray();

        $perPage = 10; // Number of items per page
        $totalContacts = count($contacts); // Total number of contacts
        $messageTemplates = Template::where('organization_id', $this->organizationId)
            ->where('deleted_at', null)
            ->where('status', 'APPROVED')
            ->get();
        
        $contactFields = ContactField::where('organization_id', $this->organizationId)
            ->whereNull('deleted_at')
            ->get(['id', 'uuid', 'name']);

        if ($uuid !== null) {
            $contact = Contact::with(['lastChat', 'lastInboundChat', 'notes', 'contactGroups'])
                ->where('uuid', $uuid)
                ->where('organization_id', $this->organizationId)
                ->whereNull('deleted_at')
                ->first();
            
            if (!$contact || !$this->canViewContact($contact)) {
                // Contact not found, return error or redirect
                if (request()->expectsJson()) {
                    return response()->json(['error' => __('Contact not found')], 404);
                } else {
                    $settings = json_decode($config->metadata);
                    return Inertia::render('User/Chat/Index', [
                        'title' => 'Chats',
                        'rows' => ContactResource::collection($contacts),
                        'showAiAssist' => $this->shouldShowAiAssist($settings),
                        'rowCount' => $rowCount,
                        'filters' => request()->all(),
                        'pusherSettings' => $pusherSettings,
                        'organizationId' => $this->organizationId,
                        'state' => app()->environment(),
                        'demoNumber' => config('platform.demo_number'),
                        'settings' => $config,
                        'templates' => $messageTemplates,
                        'contactFields' => $contactFields,
                        'status' => $ticketState,
                        'ticketCounts' => $ticketCounts,
                        'agents' => $agents,
                        'addon' => $aimodule,
                        'chat_sort_direction' => $sortDirection,
                        'isChatLimitReached' => SubscriptionService::isSubscriptionFeatureLimitReached($this->organizationId, 'message_limit')
                    ]);
                }
            }
            
            $ticket = ChatTicket::with('user')
                ->where('contact_id', $contact->id)
                ->first();

            $initialMessages = $this->getChatMessages($contact->id);

            // Mark messages as read
            Chat::where('contact_id', $contact->id)
                ->where('organization_id', $this->organizationId)
                ->where('type', 'inbound')
                ->whereNull('deleted_at')
                ->where('is_read', 0)
                ->update(['is_read' => 1]);

            if (request()->expectsJson()) {
                return response()->json([
                    'result' => ContactResource::collection($contacts)->response()->getData(),
                    'contact' => ContactResource::make($contact)->resolve(request()),
                ], 200);
            } else {
                $settings = json_decode($config->metadata);

                //To ensure the unread message counter is updated
                // Use the same logic as HandleInertiaRequests middleware
                $permissionService = new \App\Services\PermissionService();
                $isOwner = $permissionService->isOwner($this->organizationId);
                $ticketingActive = false;
                
                if ($config && $config->metadata) {
                    $settingsArray = json_decode($config->metadata, true);
                    if (isset($settingsArray['tickets']) && isset($settingsArray['tickets']['active']) && $settingsArray['tickets']['active'] === true) {
                        $ticketingActive = true;
                    }
                }
                
                if ($isOwner) {
                    // Owners see all unread messages
                    $unreadMessages = Chat::where('organization_id', $this->organizationId)
                        ->where('type', 'inbound')
                        ->where('deleted_at', NULL)
                        ->where('is_read', 0)
                        ->count();
                } else {
                    // Non-owners see only unread messages assigned to them
                    if ($ticketingActive) {
                        // When ticketing is active, filter by ticket assignment
                        // Get contact IDs assigned to this user
                        $user = auth()->user();
                        $assignedContactIds = ChatTicket::where('assigned_to', $user->id)
                            ->pluck('contact_id')
                            ->toArray();
                        
                        if (empty($assignedContactIds)) {
                            // No assigned contacts, so no unread messages
                            $unreadMessages = 0;
                        } else {
                            // Count unread chats for assigned contacts only
                            $unreadMessages = Chat::where('organization_id', $this->organizationId)
                                ->where('type', 'inbound')
                                ->whereNull('deleted_at')
                                ->where('is_read', 0)
                                ->whereIn('contact_id', $assignedContactIds)
                                ->count();
                        }
                    } else {
                        // If ticketing is not active, show all unread messages (fallback)
                        $unreadMessages = Chat::where('organization_id', $this->organizationId)
                            ->where('type', 'inbound')
                            ->where('deleted_at', NULL)
                            ->where('is_read', 0)
                            ->count();
                    }
                }

                return Inertia::render('User/Chat/Index', [
                    'title' => 'Chats',
                    'rows' => ContactResource::collection($contacts),
                    'showAiAssist' => $this->shouldShowAiAssist($settings),
                    'rowCount' => $rowCount,
                    'filters' => request()->all(),
                    'pusherSettings' => $pusherSettings,
                    'organizationId' => $this->organizationId,
                    'state' => app()->environment(),
                    'demoNumber' => config('platform.demo_number'),
                    'settings' => $config,
                    'templates' => $messageTemplates,
                    'contactFields' => $contactFields,
                    'status' => $ticketState,
                    'chatThread' => $initialMessages['messages'],
                    'hasMoreMessages' => $initialMessages['hasMoreMessages'],
                    'nextPage' => $initialMessages['nextPage'],
                    'contact' => ContactResource::make($contact),
                    'fields' => ContactField::where('organization_id', $this->organizationId)->where('deleted_at', null)->get(),
                    'locationSettings' => $this->getLocationSettings(),
                    'ticket' => $ticket,
                    'ticketCounts' => $ticketCounts,
                    'agents' => $agents,
                    'addon' => $aimodule,
                    'chat_sort_direction' => $sortDirection,
                    'unreadMessages' => $unreadMessages,
                    'isChatLimitReached' => SubscriptionService::isSubscriptionFeatureLimitReached($this->organizationId, 'message_limit')
                ]);
            }
        }

        if (request()->expectsJson()) {
            return response()->json([
                'result' => ContactResource::collection($contacts)->response()->getData(),
            ], 200);
        } else {
            $settings = json_decode($config->metadata);
            
            return Inertia::render('User/Chat/Index', [
                'title' => 'Chats',
                'rows' => ContactResource::collection($contacts),
                'showAiAssist' => $this->shouldShowAiAssist($settings),
                'rowCount' => $rowCount,
                'filters' => request()->all(),
                'pusherSettings' => $pusherSettings,
                'organizationId' => $this->organizationId,
                'state' => app()->environment(),
                'settings' => $config,
                'templates' => $messageTemplates,
                'contactFields' => $contactFields,
                'status' => $ticketState,
                'ticketCounts' => $ticketCounts,
                'agents' => $agents,
                'addon' => $aimodule,
                'ticket' => array(),
                'chat_sort_direction' => $sortDirection,
                'isChatLimitReached' => SubscriptionService::isSubscriptionFeatureLimitReached($this->organizationId, 'message_limit')
            ]);
        }
    }

    public function handleTicketAssignment($contactId){
        (new ChatTicketProvisioningService((int) $this->organizationId))->ensureForContact((int) $contactId);
    }

    public function sendMessage(object $request)
    {
        $contact = $this->resolveContactByUuid($request->uuid ?? null);

        if (!$contact || !$this->canViewContact($contact)) {
            return $this->contactNotFoundResponse();
        }

        if (SubscriptionService::isSubscriptionFeatureLimitReached($this->organizationId, 'message_limit')) return response()->json(['success' => false, 'message' => __('You have reached your message limit for the current subscription cycle. Please upgrade your plan to continue sending messages.')], 403);

        $settings = app(SettingValueService::class);
        if (!filled($contact->phone)) {
            return response()->json([
                'success' => false,
                'message' => __('This contact does not have a WhatsApp phone number.'),
            ], 422);
        }

        if($request->type === 'text'){
            return $this->whatsappService->sendMessage($contact->uuid, $request->message, auth()->user()->id);
        } else {
            if (! in_array($request->type, ['audio', 'document', 'image', 'sticker', 'video'], true)) {
                return response()->json(['success' => false, 'message' => __('Unsupported media type.')], 422);
            }

            if (! $request->hasFile('file')) {
                return response()->json(['success' => false, 'message' => __('Media file is required.')], 422);
            }

            $storage = $settings->getString('storage_system', 'local');
            $fileContent = $request->file('file');
            $fileName = $fileContent->getClientOriginalName();
            $mediaMetadata = [
                'content_type' => $fileContent->getMimeType() ?: $fileContent->getClientMimeType(),
                'size' => $fileContent->getSize(),
            ];

            if($storage === 'local'){
                $location = 'local';
                $file = Storage::disk('local')->put('public', $fileContent);
                $mediaFilePath = $file;
                $mediaUrl = rtrim(config('app.url'), '/') . '/media/' . ltrim($mediaFilePath, '/');
            } else if($storage === 'aws') {
                $location = 'amazon';
                $file = $request->file('file');
                $filePath = 'uploads/media/received/'  . $this->organizationId . '/' . $fileName;
                $uploadedFile = $file->store('uploads/media/sent/' . $this->organizationId, 's3');
                $mediaFilePath = Storage::disk('s3')->url($uploadedFile);
                $mediaUrl = $mediaFilePath;
            } else {
                $location = 'local';
                $file = Storage::disk('local')->put('public', $fileContent);
                $mediaFilePath = $file;
                $mediaUrl = rtrim(config('app.url'), '/') . '/media/' . ltrim($mediaFilePath, '/');
            }
    
            return $this->whatsappService->sendMedia($contact->uuid, $request->type, $fileName, $mediaFilePath, $mediaUrl, $location, $request->caption ?? null, null, $mediaMetadata, auth()->id());
        }
    }

    public function sendTemplateMessage(object $request, $uuid)
    {
        if (SubscriptionService::isSubscriptionFeatureLimitReached($this->organizationId, 'message_limit')) return (object) ['success' => false, 'message' => __('You have reached your message limit for the current subscription cycle. Please upgrade your plan to continue sending messages.')];

        $settings = app(SettingValueService::class);
        $template = Template::where('uuid', $request->template)
            ->where('organization_id', $this->organizationId)
            ->whereNull('deleted_at')
            ->first();
        $contact = $this->resolveContactByUuid($uuid);
        $mediaId = null;

        if (!$template || !$contact || !$this->canViewContact($contact)) {
            return (object) ['success' => false, 'message' => __('Template or contact not found.')];
        }

        if(in_array($request->header['format'], ['IMAGE', 'DOCUMENT', 'VIDEO'])){
            $header = $request->header;
            
            if ($request->header['parameters']) {
                $metadata['header']['format'] = $header['format'];
                $metadata['header']['parameters'] = [];
        
                foreach ($request->header['parameters'] as $key => $parameter) {
                    if ($parameter['selection'] === 'upload') {
                        $storage = $settings->getString('storage_system', 'local');
                        $fileName = $parameter['value']->getClientOriginalName();
                        $fileContent = $parameter['value'];

                        if($storage === 'local'){
                            $file = Storage::disk('local')->put('public', $fileContent);
                            $mediaFilePath = $file;
            
                            $mediaUrl = rtrim(config('app.url'), '/') . '/media/' . ltrim($mediaFilePath, '/');
                        } else if($storage === 'aws') {
                            $file = $parameter['value'];
                            $uploadedFile = $file->store('uploads/media/sent/' . $this->organizationId, 's3');
                            $mediaFilePath = Storage::disk('s3')->url($uploadedFile);
            
                            $mediaUrl = $mediaFilePath;
                        }

                        $contentType = $fileContent->getMimeType()
                            ?: $fileContent->getClientMimeType()
                            ?: $this->getContentTypeFromUrl($mediaUrl);
                        $mediaSize = $fileContent->getSize() ?? $this->getMediaSizeInBytesFromUrl($mediaUrl);

                        //save media
                        $chatMedia = new ChatMedia;
                        $chatMedia->name = $fileName;
                        $chatMedia->location = $storage == 'aws' ? 'amazon' : 'local';
                        $chatMedia->path = $mediaUrl;
                        $chatMedia->type = $contentType;
                        $chatMedia->size = $mediaSize;
                        $chatMedia->created_at = now();
                        $chatMedia->save();

                        $mediaId = $chatMedia->id;
                    } else {
                        $mediaUrl = $parameter['value'];
                    }
        
                    $metadata['header']['parameters'][] = [
                        'type' => $parameter['type'],
                        'selection' => $parameter['selection'],
                        'value' => $mediaUrl,
                    ];
                }
            }
        } else {
            $metadata['header'] = $request->header;
        }

        $metadata['body'] = $request->body;
        $metadata['footer'] = $request->footer;
        $metadata['buttons'] = $request->buttons;
        $metadata['media'] = $mediaId;

        //Build Template to send
        $template = $this->buildTemplate($template->name, $template->language, json_decode(json_encode($metadata)), $contact);
        
        return $this->whatsappService->sendTemplateMessage($contact->uuid, $template, auth()->user()->id, NULL, $mediaId);
    }

    public function clearMessage($uuid)
    {
        Chat::where('uuid', $uuid)
            ->update([
                'deleted_by' => auth()->user()->id,
                'deleted_at' => now()
            ]);
    }

    public function clearContactChat($uuid)
    {
        $contact = Contact::with('lastChat')
            ->where('uuid', $uuid)
            ->where('organization_id', $this->organizationId)
            ->firstOrFail();

        if (!$this->canViewContact($contact)) {
            abort(404, __('Chat not found.'));
        }

        Chat::where('contact_id', $contact->id)
            ->where('organization_id', $this->organizationId)
            ->update([
            'deleted_by' => auth()->user()->id,
            'deleted_at' => now()
        ]);

        ChatLog::where('contact_id', $contact->id)
            ->where('entity_type', 'chat')
            ->update([
            'deleted_by' => auth()->user()->id,
            'deleted_at' => now()
        ]);

        $chat = $contact->lastChat
            ? Chat::with('contact','media')
                ->where('id', $contact->lastChat->id)
                ->where('organization_id', $this->organizationId)
                ->first()
            : null;

        //event(new NewChatEvent($chat, $contact->organization_id));
    }

    private function getContentTypeFromUrl($url) {
        try {
            // Make a HEAD request to fetch headers only
            $response = Http::timeout(10)->connectTimeout(5)->head($url);
    
            // Check if the Content-Type header is present
            if ($response->hasHeader('Content-Type')) {
                return $response->header('Content-Type');
            }
    
            return 'application/octet-stream';
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Error fetching headers: ' . $e->getMessage());
            return 'application/octet-stream';
        }
    }

    private function getMediaSizeInBytesFromUrl($url) {
        try {
            $response = Http::timeout(10)->connectTimeout(5)->head($url);
            $contentLength = $response->header('Content-Length');

            if (is_array($contentLength)) {
                $contentLength = reset($contentLength);
            }

            $contentLength = trim((string) $contentLength);
            if ($contentLength !== '' && ctype_digit($contentLength)) {
                return (int) $contentLength;
            }
        } catch (\Exception $e) {
            Log::error('Error fetching media size: ' . $e->getMessage());
        }
    
        return 0;
    }

    private function shouldShowAiAssist($settings): bool
    {
        if (!CustomHelper::isModuleEnabled('AI Assistant', $this->organizationId)) {
            return false;
        }

        $active = data_get($settings, 'ai.active');
        $isActive = $active !== null
            ? (bool) $active
            : (bool) data_get($settings, 'ai.ai_chat_form_active', false);

        if (!$isActive) {
            return false;
        }

        $metadata = is_array($settings)
            ? $settings
            : json_decode(json_encode($settings), true);
        $metadata = is_array($metadata) ? $metadata : [];

        $keyBundle = app(AiKeyResolver::class)->resolveForOrganization(
            $metadata,
            data_get($metadata, 'ai.key_source', 'auto'),
            (int) $this->organizationId
        );

        if (empty($keyBundle['key'])) {
            return false;
        }

        if (!app(AiUsageLimiterService::class)->canUseText((int) $this->organizationId, $keyBundle['source'] ?? null)) {
            return false;
        }

        return Document::query()
            ->where('organization_id', $this->organizationId)
            ->where('status', 'Complete')
            ->whereNotNull('embeddings')
            ->exists();
    }

    private function getLocationSettings(){
        // Retrieve the settings for the current organization
        $settings = Organization::where('id', $this->organizationId)->first();

        if ($settings) {
            // Decode the JSON metadata column into an associative array
            $metadata = json_decode($settings->metadata, true);

            if (isset($metadata['contacts'])) {
                // If the 'contacts' key exists, retrieve the 'location' value
                $location = $metadata['contacts']['location'];

                // Now, you have the location value available
                return $location;
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    private function getTicketCounts(bool $ticketingActive): array
    {
        if (!$ticketingActive) {
            return [
                'all' => 0,
                'open' => 0,
                'closed' => 0,
                'unassigned' => 0,
            ];
        }

        $baseQuery = ChatTicket::query()
            ->join('contacts', 'contacts.id', '=', 'chat_tickets.contact_id')
            ->where('contacts.organization_id', $this->organizationId)
            ->whereNull('contacts.deleted_at');

        return [
            'all' => (clone $baseQuery)->count('chat_tickets.id'),
            'open' => (clone $baseQuery)->where('chat_tickets.status', 'open')->count('chat_tickets.id'),
            'closed' => (clone $baseQuery)->where('chat_tickets.status', 'closed')->count('chat_tickets.id'),
            'unassigned' => (clone $baseQuery)->whereNull('chat_tickets.assigned_to')->count('chat_tickets.id'),
        ];
    }

    public function getChatMessages($contactId, $page = 1, $perPage = 10)
    {
        $contact = $this->resolveContactById($contactId);

        if (!$contact || !$this->canViewContact($contact)) {
            abort(404, __('Chat not found.'));
        }

        $chatLogs = ChatLog::where('contact_id', $contact->id)
            ->where('deleted_at', null)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $chats = [];
        foreach ($chatLogs as $chatLog) {
            $chats[] = array([
                'type' => $chatLog->entity_type,
                'value' => $chatLog->relatedEntities
            ]);
        }

        return [
            'messages' => array_reverse($chats),
            'hasMoreMessages' => $chatLogs->hasMorePages(),
            'nextPage' => $chatLogs->currentPage() + 1
        ];
    }
}
