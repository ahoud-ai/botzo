<?php

namespace App\Http\Controllers\User;

use App\Exports\CampaignDetailsExport;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreCampaign;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\CampaignLogResource;
use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\Contact;
use App\Models\ContactField;
use App\Models\ContactGroup;
use App\Models\Organization;
use App\Models\Template;
use App\Services\CampaignService;
use App\Services\SubscriptionFeatureUsageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class CampaignController extends BaseController
{
    private $campaignService;

    public function __construct(CampaignService $campaignService)
    {
        $this->campaignService = $campaignService;
    }

    public function index(Request $request, $uuid = null){
        $organizationId = session()->get('current_organization');
        $this->checkPermission('campaigns.view_all', $organizationId);
        
        if($uuid == null){
            $searchTerm = $request->query('search', '');
            $status = $request->query('status');

            // Get settings once
            $settings = Organization::where('id', $organizationId)->first();

            $baseQuery = Campaign::where('organization_id', $organizationId)
                ->where('deleted_at', null);

            // Build query with optimized eager loading and counts
            $query = (clone $baseQuery)->with(['template']);

            // Add search condition
            if ($searchTerm) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%' . $searchTerm . '%')
                      ->orWhereHas('template', function ($templateQuery) use ($searchTerm) {
                          $templateQuery->where('name', 'like', '%' . $searchTerm . '%');
                      });
                });
            }

            if ($status && $status !== 'all') {
                $query->where('status', $status);
            }

            // Paginate and get campaigns
            $campaigns = $query->latest()->paginate(10)->withQueryString();

            // Pre-calculate counts for all campaigns in a single query
            $campaignIds = $campaigns->pluck('id');
            $countsData = $this->getCampaignCounts($campaignIds, $organizationId);

            // Attach counts to each campaign
            foreach ($campaigns as $campaign) {
                $campaign->setAttribute('computed_counts', $countsData[$campaign->id] ?? [
                    'contacts_count' => 0,
                    'delivery_count' => 0,
                    'read_count' => 0,
                    'contact_group_count' => 0,
                ]);
            }

            $rows = CampaignResource::collection($campaigns);

            $stats = [
                'total' => (clone $baseQuery)->count(),
                'ongoing' => (clone $baseQuery)->where('status', 'ongoing')->count(),
                'scheduled' => (clone $baseQuery)->where('status', 'scheduled')->count(),
                'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
            ];

            return Inertia::render('User/Campaign/Index', [
                'title'=> __('Campaigns'),
                'allowCreate' => true,
                'rows' => $rows,
                'filters' => request()->all(['search', 'status']),
                'stats' => $stats,
                'settings' => $settings
            ]);
        } else if($uuid == 'create'){
            $this->checkPermission('campaigns.add', $organizationId);
            
            $data['settings'] = Organization::where('id', $organizationId)->first();
            $data['templates'] = Template::where('organization_id', $organizationId)
                ->where('deleted_at', null)
                ->where('status', 'APPROVED')
                ->get();

            $data['contactGroups'] = ContactGroup::where('organization_id', $organizationId)
                ->where('deleted_at', null)
                ->withCount(['contacts' => function ($query) use ($organizationId) {
                    $query->where('organization_id', $organizationId)->whereNull('deleted_at');
                }])
                ->get();

            $data['totalContacts'] = Contact::where('organization_id', $organizationId)
                ->whereNull('deleted_at')
                ->count();

            $data['contactFields'] = ContactField::where('organization_id', $organizationId)
                ->whereNull('deleted_at')
                ->get(['id', 'uuid', 'name']);

            $data['campaignLimit'] = app(SubscriptionFeatureUsageService::class)
                ->snapshot($organizationId, 'campaign_limit');

            $data['title'] = __('Create campaign');

            return Inertia::render('User/Campaign/Create', $data);
        } else {
            $this->checkPermission('campaigns.view', $organizationId);
            
            $data['campaign'] = Campaign::with('contactGroup', 'template')
                ->where('organization_id', $organizationId)
                ->where('uuid', $uuid)
                ->whereNull('deleted_at')
                ->firstOrFail();
            if ($data['campaign']) {
                $counts = $data['campaign']->getCounts();
                $data['campaign']['total_message_count'] = $counts->total_message_count ?? 0;
                $data['campaign']['total_sent_count'] = $counts->total_sent_count ?? 0;
                $data['campaign']['total_delivered_count'] = $counts->total_delivered_count ?? 0;
                $data['campaign']['total_failed_count'] = $counts->total_failed_count ?? 0;
                $data['campaign']['total_read_count'] = $counts->total_read_count ?? 0;
            }else{
                $data['campaign']['total_message_count'] = 0;
                $data['campaign']['total_sent_count'] = 0;
                $data['campaign']['total_delivered_count'] = 0;
                $data['campaign']['total_read_count'] = 0;
                $data['campaign']['total_failed_count'] = 0;
            }

            $data['filters'] = request()->all(['search', 'status']);

            $searchTerm = $request->query('search');
            $logStatus = $request->query('status');

            $logsQuery = CampaignLog::with('contact', 'chat.logs')
                ->where('campaign_id', $data['campaign']->id)
                ->where(function ($query) use ($searchTerm) {
                    $query->whereHas('contact', function ($contactQuery) use ($searchTerm) {
                        $contactQuery->where('first_name', 'like', '%' . $searchTerm . '%')
                                     ->orWhere('last_name', 'like', '%' . $searchTerm . '%')
                                     ->orWhere('phone', 'like', '%' . $searchTerm . '%');
                    });
                });

            // Buckets mirror Campaign::sentCount()/deliveryCount()/readCount()/failedCount()
            // exactly, so the filter matches the same real business rules used elsewhere.
            if ($logStatus && $logStatus !== 'all') {
                if ($logStatus === 'pending') {
                    $logsQuery->whereIn('status', ['pending', 'ongoing', 'retrying']);
                } elseif ($logStatus === 'failed') {
                    $logsQuery->where(function ($query) {
                        $query->where('status', 'failed')
                            ->orWhere(function ($query) {
                                $query->where('status', 'success')
                                    ->whereHas('chat', fn ($chatQuery) => $chatQuery->where('status', 'failed'));
                            });
                    });
                } elseif ($logStatus === 'sent') {
                    $logsQuery->where('status', 'success')
                        ->whereHas('chat', fn ($chatQuery) => $chatQuery->whereIn('status', ['accepted', 'sent']));
                } else {
                    // 'delivered' or 'read'
                    $logsQuery->where('status', 'success')
                        ->whereHas('chat', fn ($chatQuery) => $chatQuery->where('status', $logStatus));
                }
            }

            $data['rows'] = CampaignLogResource::collection(
                $logsQuery->orderBy('id')->paginate(10)->withQueryString()
            );
            $data['title'] = __('View campaign');

            return Inertia::render('User/Campaign/View', $data);
        }
    }

    public function store(StoreCampaign $request){
        $organizationId = session()->get('current_organization');
        $this->checkPermission('campaigns.add', $organizationId);
        
        $this->campaignService->store($request);

        return Redirect::route('campaigns')->with(
            'status', [
                'type' => 'success', 
                'message' => __('Campaign created successfully!')
            ]
        );
    }

    public function export($uuid = null){
        $organizationId = session()->get('current_organization');
        $this->checkPermission('campaigns.view_all', $organizationId);
        
            return Excel::download(new CampaignDetailsExport($uuid, $organizationId), 'campaign.csv');
    }

    public function delete($uuid){
        $organizationId = session()->get('current_organization');
        $this->checkPermission('campaigns.delete', $organizationId);

        $this->campaignService->destroy($uuid);

        // If the delete was triggered from the campaign's own detail page, that page
        // no longer exists after deletion, so go back there would 404. Send those
        // requests to the list instead; deletes from the list page keep going "back"
        // (preserving any active search/status filters) exactly as before.
        $referer = request()->headers->get('referer', '');
        $deletedFromDetailPage = str_contains($referer, '/campaigns/' . $uuid);

        $redirect = $deletedFromDetailPage ? Redirect::route('campaigns') : Redirect::back();

        return $redirect->with(
            'status', [
                'type' => 'success',
                'message' => __('Row deleted successfully!')
            ]
        );
    }

    /**
     * Get aggregated counts for multiple campaigns in a single query
     * Uses proper database indexes for optimal performance
     * 
     * @param \Illuminate\Support\Collection $campaignIds
     * @param int $organizationId
     * @return array
     */
    private function getCampaignCounts($campaignIds, $organizationId)
    {
        if ($campaignIds->isEmpty()) {
            return [];
        }

        // Get contact_group_id for each campaign first
        $campaignContactGroups = \DB::table('campaigns')
            ->whereIn('id', $campaignIds)
            ->pluck('contact_group_id', 'id')
            ->toArray();

        // Get unique contact group IDs that are not 0
        $contactGroupIds = array_filter(array_unique(array_values($campaignContactGroups)), function($id) {
            return $id != 0 && $id != '0' && $id !== null;
        });

        // Get contact group counts in one query if there are contact groups
        $contactGroupCounts = [];
        if (!empty($contactGroupIds)) {
            $contactGroupCounts = \DB::table('contact_contact_group')
                ->join('contacts', 'contacts.id', '=', 'contact_contact_group.contact_id')
                ->whereNull('contacts.deleted_at')
                ->whereIn('contact_contact_group.contact_group_id', $contactGroupIds)
                ->groupBy('contact_contact_group.contact_group_id')
                ->select(
                    'contact_contact_group.contact_group_id',
                    \DB::raw('COUNT(*) as count')
                )
                ->pluck('count', 'contact_group_id')
                ->toArray();
        }

        // Get total contacts count for organization (used when contact_group_id = 0)
        // With proper indexes on organization_id and deleted_at, this is fast
        $organizationTotalContacts = \DB::table('contacts')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->count();

        // Get campaign counts using a single optimized query
        // Indexes on campaign_id, status, and chat_id make this very fast
        $counts = \DB::table('campaign_logs')
            ->leftJoin('chats', 'chats.id', '=', 'campaign_logs.chat_id')
            ->whereIn('campaign_logs.campaign_id', $campaignIds)
            ->select(
                'campaign_logs.campaign_id',
                \DB::raw('COUNT(*) as contacts_count'),
                \DB::raw('SUM(CASE WHEN campaign_logs.status = "success" AND chats.status IN ("delivered", "read") THEN 1 ELSE 0 END) as delivery_count'),
                \DB::raw('SUM(CASE WHEN campaign_logs.status = "success" AND chats.status = "read" THEN 1 ELSE 0 END) as read_count')
            )
            ->groupBy('campaign_logs.campaign_id')
            ->get()
            ->keyBy('campaign_id')
            ->toArray();

        // Build result array with all counts
        $result = [];
        foreach ($campaignIds as $campaignId) {
            $campaignCounts = $counts[$campaignId] ?? (object)[
                'contacts_count' => 0,
                'delivery_count' => 0,
                'read_count' => 0,
            ];

            $contactGroupId = $campaignContactGroups[$campaignId] ?? null;
            $contactGroupCount = 0;
            
            if ($contactGroupId && $contactGroupId != 0 && $contactGroupId != '0') {
                $contactGroupCount = $contactGroupCounts[$contactGroupId] ?? 0;
            } else {
                // Use organization total when contact_group_id is 0
                $contactGroupCount = $organizationTotalContacts;
            }

            $result[$campaignId] = [
                'contacts_count' => (int)($campaignCounts->contacts_count ?? 0),
                'delivery_count' => (int)($campaignCounts->delivery_count ?? 0),
                'read_count' => (int)($campaignCounts->read_count ?? 0),
                'contact_group_count' => (int)$contactGroupCount,
            ];
        }

        return $result;
    }
}
