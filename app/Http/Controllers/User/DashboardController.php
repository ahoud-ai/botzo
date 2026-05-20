<?php

namespace App\Http\Controllers\User;

use Carbon\Carbon;
use DB;
use App\Http\Controllers\Controller as BaseController;
use App\Helpers\SubscriptionHelper;
use App\Models\Addon;
use App\Models\Chat;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\Template;
use App\Models\Team;
use App\Services\PermissionService;
use App\Services\EmbeddedSignup\EmbeddedSignupGate;
use App\Services\OrganizationHierarchyService;
use App\Services\SubscriptionPlanLimitService;
use App\Services\SubscriptionService;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends BaseController
{
    public function __construct()
    {
        $this->subscriptionService = new SubscriptionService();
        $this->organizationHierarchyService = new OrganizationHierarchyService();
        $this->subscriptionPlanLimitService = app(SubscriptionPlanLimitService::class);
    }

    public function index(Request $request){
        $organizationId = session()->get('current_organization');
        $data['subscription'] = $this->subscriptionPlanLimitService->subscriptionForOrganization((int) $organizationId);
        $data['subscriptionDetails'] = SubscriptionService::calculateSubscriptionBillingDetails($organizationId, $data['subscription']?->plan_id);
        $data['subscriptionIsActive'] = SubscriptionService::isSubscriptionActive($organizationId);
        $data['subscriptionDisplayState'] = SubscriptionService::billingDisplayState((int) $organizationId, $data['subscription']);
        $data['chatCount'] = Chat::where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->whereHas('contact', function ($query) {
                $query->whereNull('deleted_at');
            })
            ->count();
        $data['campaignCount'] = Campaign::where('organization_id', $organizationId)->whereNull('deleted_at')->count();
        $data['contactCount'] = Contact::where('organization_id', $organizationId)->whereNull('deleted_at')->count();
        $data['templateCount'] = Template::where('organization_id', $organizationId)->whereNull('deleted_at')->count();
        $data['teamMemberCount'] = Team::where('organization_id', $organizationId)->whereNull('deleted_at')->count();
        $data['graphAPIVersion'] = config('graph.api_version');

        $organization = Organization::where('id', $organizationId)->first();
        $config = $organization->metadata ? json_decode($organization->metadata, true) : [];
        $settings = Setting::whereIn('key', ['is_embedded_signup_active', 'whatsapp_client_id', 'whatsapp_config_id'])
            ->pluck('value', 'key');
        $ticketingActive = (bool) data_get($config, 'tickets.active', false);
        $allowAgentsToViewAllChats = (bool) data_get($config, 'tickets.allow_agents_to_view_all_chats', true);
        $permissionService = new PermissionService();
        $currentRole = $permissionService->getCurrentRole((int) $organizationId);
        $roleName = $currentRole ? strtolower($currentRole->name) : 'owner';
        $recentConversationRows = (new Contact())->contactsWithChats(
            (int) $organizationId,
            null,
            $ticketingActive,
            'all',
            'desc',
            $roleName,
            $allowAgentsToViewAllChats
        );

        $data['organization'] = $organization;
        $data['campaigns'] = Campaign::where('organization_id', $organizationId)
            ->whereIn('status', ['pending', 'scheduled'])
            ->limit(5)
            ->get();
        $data['campaignSummary'] = [
            'pending' => Campaign::where('organization_id', $organizationId)->whereNull('deleted_at')->where('status', 'pending')->count(),
            'scheduled' => Campaign::where('organization_id', $organizationId)->whereNull('deleted_at')->where('status', 'scheduled')->count(),
        ];
        $data['ticketSummary'] = $this->ticketSummary((int) $organizationId, $ticketingActive);
        $data['recentConversations'] = collect($recentConversationRows->items())
            ->take(5)
            ->map(function (Contact $contact) {
                $lastChat = $contact->lastChat;
                $ticket = $contact->ticket;
                $ticketUser = $ticket?->user;

                return [
                    'uuid' => $contact->uuid,
                    'full_name' => $contact->full_name,
                    'avatar' => $contact->avatar,
                    'unread_messages' => $contact->chats()
                        ->where('type', 'inbound')
                        ->whereNull('deleted_at')
                        ->where('is_read', 0)
                        ->count(),
                    'last_chat' => $lastChat ? [
                        'created_at' => $lastChat->created_at,
                        'deleted_at' => $lastChat->deleted_at,
                        'metadata' => $lastChat->metadata,
                    ] : null,
                    'ticket' => $ticket ? [
                        'status' => $ticket->status,
                        'agent_name' => $ticketUser ? trim(($ticketUser->first_name ?? '') . ' ' . ($ticketUser->last_name ?? '')) : null,
                        'agent_initials' => $ticketUser ? $this->initials($ticketUser->first_name, $ticketUser->last_name) : null,
                    ] : null,
                ];
            })
            ->values();
        $data['setupWhatsapp'] = isset($config['whatsapp']) ? false : true;;
        $data['period'] = $this->period();
        $data['inbound'] = $this->getChatCounts('inbound');
        $data['outbound'] = $this->getChatCounts('outbound');
        $embeddedSignupGate = new EmbeddedSignupGate();
        $data['embeddedSignupActive'] = $embeddedSignupGate->isEnabledForOrganization($organizationId);
        $data['appId'] = $settings->get('whatsapp_client_id', null);
        $data['configId'] = $settings->get('whatsapp_config_id', null);
        $data['title'] = __('Dashboard');

        return Inertia::render('User/Dashboard', $data);
    }

    private function ticketSummary(int $organizationId, bool $ticketingActive): array
    {
        if (! $ticketingActive) {
            return [
                'enabled' => false,
                'open' => 0,
                'unassigned' => 0,
            ];
        }

        $baseQuery = \App\Models\ChatTicket::query()
            ->join('contacts', 'contacts.id', '=', 'chat_tickets.contact_id')
            ->where('contacts.organization_id', $organizationId)
            ->whereNull('contacts.deleted_at');

        return [
            'enabled' => true,
            'open' => (clone $baseQuery)->where('chat_tickets.status', 'open')->count('chat_tickets.id'),
            'unassigned' => (clone $baseQuery)->whereNull('chat_tickets.assigned_to')->count('chat_tickets.id'),
        ];
    }

    private function initials(?string $firstName, ?string $lastName): string
    {
        $first = trim((string) $firstName);
        $last = trim((string) $lastName);

        if ($first === '' && $last === '') {
            return '';
        }

        return trim($first . ' ' . mb_substr($last, 0, 1));
    }

    public function dismissTeamPrompt(Request $request, $type){
        $currentOrganizationId = session()->get('current_organization');
        $organizationConfig = Organization::where('id', $currentOrganizationId)->first();

        $metadataArray = $organizationConfig->metadata ? json_decode($organizationConfig->metadata, true) : [];

        if($type === 'team'){
            $metadataArray['dashboard']['team_prompt'] = false;
        }

        $updatedMetadataJson = json_encode($metadataArray);

        $organizationConfig->metadata = $updatedMetadataJson;
        $organizationConfig->save();

        return redirect()->route('dashboard')->with(
                'status', [
                    'type' => 'success',
                    'message' => __('Prompt dismissed successfully!')
                ]
            );
    }

    private function period(){
        $currentDate = Carbon::now();
        $dateArray = [];

        for ($i = 0; $i < 7; $i++) {
            $currentDate->startOfDay();
            $dateArray[] = $currentDate->format('Y-m-d\TH:i:s.000\Z');
            $currentDate->subDay();
        }

        $dateArray = array_reverse($dateArray);

        return $dateArray;
    }

    private function getChatCounts($type){
        $organizationId = session()->get('current_organization');
        $chatCounts = [];

        foreach ($this->period() as $dateString) {
            $date = Carbon::parse($dateString);
            $chatCount = Chat::where('organization_id', $organizationId)
                ->where('type', $type)
                ->whereNull('deleted_at')
                ->whereDate('created_at', $date->toDateString())
                ->count();
            $chatCounts[] = $chatCount;
        }

        return $chatCounts;
    }
}
