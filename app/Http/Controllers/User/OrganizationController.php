<?php

namespace App\Http\Controllers\User;

use DB;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreUserOrganization;
use App\Models\Organization;
use App\Services\OrganizationHierarchyService;
use App\Services\OrganizationSessionService;
use App\Services\OrganizationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OrganizationController extends BaseController
{
    private $organizationService;
    private $organizationSessionService;

    /**
     * OrganizationController constructor.
     *
     * @param UserService $organizationService
     */
    public function __construct()
    {
        $this->organizationService = new OrganizationService();
        $this->organizationSessionService = app(OrganizationSessionService::class);
    }
    
    public function index(){
        $currentOrganizationId = session()->get('current_organization');
        $billingOwner = app(OrganizationHierarchyService::class)->billingOwner($currentOrganizationId ? (int) $currentOrganizationId : null);
        $branchCreationContext = $this->organizationService->branchCreationContextForParent($billingOwner);
        $accessibleOrganizationIds = $this->organizationSessionService->accessibleOrganizationIdsForUser(auth()->id());
        $directOrganizationIds = $this->organizationSessionService->organizationIdsForUser(auth()->id());

        $data['organizations'] = Organization::query()
            ->with('parentOrganization')
            ->whereIn('id', $accessibleOrganizationIds !== [] ? $accessibleOrganizationIds : [0])
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get()
            ->map(function (Organization $organization) use ($currentOrganizationId, $directOrganizationIds) {
                $accessSource = in_array((int) $organization->id, $directOrganizationIds, true)
                    ? 'direct'
                    : 'inherited_parent_owner';

                return [
                    'organization' => [
                        'uuid' => $organization->uuid,
                        'name' => $organization->name,
                        'organization_type' => $organization->organization_type,
                        'parent_organization' => $organization->parentOrganization ? [
                            'uuid' => $organization->parentOrganization->uuid,
                            'name' => $organization->parentOrganization->name,
                        ] : null,
                    ],
                    'access' => [
                        'source' => $accessSource,
                        'label' => $accessSource === 'inherited_parent_owner'
                            ? __('Managed from parent organization')
                            : __('Direct workspace member'),
                        'isCurrent' => $currentOrganizationId !== null
                            && (int) $organization->id === (int) $currentOrganizationId,
                    ],
                ];
            })
            ->values()
            ->all();
        $data['branchCreationContext'] = $billingOwner ? [
            'type' => 'branch',
            'parentOrganization' => [
                'uuid' => $billingOwner->uuid,
                'name' => $billingOwner->name,
            ],
            'canCreateBranch' => (bool) ($branchCreationContext['can_create_branch'] ?? true),
            'blockingCode' => $branchCreationContext['blocking_code'] ?? null,
            'blockingMessage' => $branchCreationContext['blocking_message'] ?? null,
            'limitSnapshot' => $branchCreationContext['limit_snapshot'] ?? null,
        ] : null;
        
        return Inertia::render('User/OrganizationSelect', $data);
    }

    public function selectOrganization(Request $request){
        $organizationId = $this->organizationSessionService->selectableOrganizationIdForUserByUuid(
            auth()->id(),
            $request->uuid
        );

        if ($organizationId) {
            session()->put('current_organization', $organizationId);

            return to_route('dashboard');
        }

        return to_route('user.organization.index');
    }

    public function store(StoreUserOrganization $request)
    {
        $user = auth()->user();
        $organizationIds = $this->organizationSessionService->organizationIdsForUser($user?->id);
        $currentOrganizationId = session()->get('current_organization');

        $request->merge([
            'create_user' => 0,
            'email' => $user?->email,
        ]);

        if ($organizationIds === []) {
            $organization = $this->organizationService->createOwnedMainOrganizationShell(
                $user,
                (string) $request->input('name'),
                $user?->id ?? auth()->id()
            );

            session()->put('current_organization', $organization->id);

            return to_route('dashboard');
        } else {
            $billingOwner = app(OrganizationHierarchyService::class)->billingOwner($currentOrganizationId ? (int) $currentOrganizationId : null);

            if (!$billingOwner) {
                throw ValidationException::withMessages([
                    'organization' => __('Select a workspace first, then add a branch from that workspace context.'),
                ]);
            }

            if (!app(\App\Services\PermissionService::class)->isOwner($billingOwner->id)) {
                throw ValidationException::withMessages([
                    'organization' => __('Only workspace owners can create new branch workspaces.'),
                ]);
            }

            $request->merge([
                'organization_type' => 'branch',
                'parent_organization_uuid' => $billingOwner->uuid,
            ]);
        }

        $organization = $this->organizationService->store($request);

        if($organization){
            session()->put('current_organization', $organization->id);

            return to_route('dashboard');
        }
    }
}
