<?php

namespace App\Services;

use App\Helpers\Email;
use App\Helpers\DateTimeHelper;
use App\Http\Resources\OrganizationsResource;
use App\Http\Resources\BillingResource;
use App\Models\AutomationFlow;
use App\Models\AutomationFlowRun;
use App\Models\BillingCredit;
use App\Models\BillingDebit;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\BillingTransaction;
use App\Models\AutoReply;
use App\Models\Campaign;
use App\Models\Chat;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\OrganizationEmployeeAssignment;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\Template;
use App\Models\User;
use App\Services\IntelliReply\AiUsageLimiterService;
use App\Support\BillingPaymentMethodResolver;
use DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Str;
use Illuminate\Validation\ValidationException;

class OrganizationService
{
    /**
     * Get all organizations based on the provided request filters.
     *
     * @param Request $request
     * @return mixed
     */
    public function get(object $request, $userId = null)
    {
        $organizations = (new Organization)->listAll(
            $request->query('search'),
            $userId,
            $request->query('organization_type'),
            $request->query('subscription_status'),
            $request->query('plan_uuid'),
            $request->query('branches_scope')
        );

        return OrganizationsResource::collection($organizations);
    }

    public function getDirectorySummary(): array
    {
        $mainOrganizationIds = Organization::query()
            ->whereNull('deleted_at')
            ->where('organization_type', 'main')
            ->pluck('id');

        return [
            'main_organizations' => $mainOrganizationIds->count(),
            'branches' => Organization::query()
                ->whereNull('deleted_at')
                ->where('organization_type', 'branch')
                ->count(),
            'active_subscriptions' => Subscription::query()
                ->whereIn('organization_id', $mainOrganizationIds)
                ->where('status', 'active')
                ->where('valid_until', '>', now())
                ->count(),
            'trial_subscriptions' => Subscription::query()
                ->whereIn('organization_id', $mainOrganizationIds)
                ->where('status', 'trial')
                ->where('valid_until', '>', now())
                ->count(),
            'expiring_soon' => Subscription::query()
                ->whereIn('organization_id', $mainOrganizationIds)
                ->where('valid_until', '>=', now())
                ->where('valid_until', '<=', now()->copy()->addDays(7))
                ->count(),
        ];
    }

    /**
     * Retrieve an organization by its UUID.
     *
     * @param string $uuid
     * @return \App\Models\Organization
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getByUuid($request, $uuid = null)
    {
        $result['plans'] = SubscriptionPlan::query()
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->get();
        $result['parentOrganizations'] = Organization::whereNull('deleted_at')
            ->where('organization_type', 'main')
            ->when($uuid !== null, function ($query) use ($uuid) {
                $query->where('uuid', '!=', $uuid);
            })
            ->orderBy('name')
            ->get(['uuid', 'name']);

        if ($uuid === null) {
            $result['organization'] = null;
            $result['billing'] = null;
            $result['invoiceRows'] = null;
            $result['users'] = null;
            $result['teamSummary'] = null;
            $result['profileSummary'] = null;
            $result['usageSummary'] = null;
            $result['billingSummary'] = null;
            $result['branches'] = [];
    
            return $result;
        }

        $organization = Organization::with([
            'subscription.plan',
            'parentOrganization.subscription.plan',
            'owner.user',
        ])->where('uuid', $uuid)->first();
        $billingOwner = app(OrganizationHierarchyService::class)->billingOwner($organization->id);
        $billingOrganizationId = $billingOwner?->id ?? $organization->id;
        $billing = (new BillingTransaction)->listAll($request->query('search'), $billingOrganizationId);
        $invoiceRows = app(BillingInvoiceService::class)->list($request, $billingOwner?->uuid ?? $organization->uuid);
        $planLimitService = app(SubscriptionPlanLimitService::class);
        $effectiveSubscription = $planLimitService->subscriptionForOrganization($organization->id);
        $familyOrganizationIds = app(OrganizationHierarchyService::class)->familyOrganizationIds($organization->id);
        $profileOwner = $billingOwner ?? $organization;
        $teamDirectory = $this->buildTeamDirectory($organization, (string) $request->query('search', ''));

        $organization->setAttribute('effective_subscription', $effectiveSubscription);
        $organization->setAttribute('billing_owner', $billingOwner ? [
            'id' => $billingOwner->id,
            'uuid' => $billingOwner->uuid,
            'name' => $billingOwner->name,
        ] : null);
        $organization->setAttribute('subscription_managed_by_parent', (bool) (
            $organization->organization_type === 'branch'
            && $billingOwner
            && $billingOwner->id !== $organization->id
        ));
        
        $result['organization'] = $organization;
        $result['billing'] = BillingResource::collection($billing);
        $result['invoiceRows'] = $invoiceRows;
        $result['users'] = $teamDirectory['rows'];
        $result['teamSummary'] = $teamDirectory['summary'];
        $result['profileSummary'] = $this->buildProfileSummary(
            $organization,
            $profileOwner,
            $familyOrganizationIds,
            $effectiveSubscription,
            (int) ($teamDirectory['summary']['members_count'] ?? 0)
        );
        $result['usageSummary'] = $this->buildUsageSummary($organization->id, $familyOrganizationIds);
        $result['billingSummary'] = $this->buildBillingSummary($billingOrganizationId);
        $result['branches'] = $this->buildBranchRows($profileOwner->id, $organization->id);

        return $result;
    }

    /**
     * Store a new organization based on the provided request data.
     *
     * @param Request $request
     */
    public function store(Object $request)
    {
        return DB::transaction(function () use ($request) {
            $organizationType = $this->resolveOrganizationType($request->input('organization_type'));
            $actorId = auth()->id() ?: auth('admin')->id();
            $parentOrganizationId = $this->resolveParentOrganizationId(
                $organizationType,
                $request->input('parent_organization_uuid')
            );

            if($request->input('create_user') == 1){
                $user = User::create([
                    'first_name' => $request->input('first_name'),
                    'last_name' => $request->input('last_name'),
                    'email' => $request->input('email'),
                    'role' => 'user',
                    'phone' => $request->input('phone') ? phone($request->input('phone'))->formatE164() : null,
                    'address' => json_encode([
                        'street' => $request->input('street'),
                        'city' => $request->input('city'),
                        'state' => $request->input('state'),
                        'zip' => $request->input('zip'),
                        'country' => $request->input('country'),
                    ]),
                    'password' => $request->input('password'),
                    'language' => app()->getLocale(),
                ]);

                if ((bool) $request->boolean('send_registration_email', true)) {
                    Email::send('Registration', $user, [
                        'link' => Email::defaultAppLink(),
                    ]);
                }
            } else {
                $user = null;

                if ($request->filled('owner_user_id')) {
                    $user = User::query()
                        ->where('id', (int) $request->input('owner_user_id'))
                        ->where('role', 'user')
                        ->first();
                }

                if (!$user && $request->filled('email')) {
                    $user = User::where('email', $request->input('email'))->first();
                }
            }

            if (!$user) {
                throw ValidationException::withMessages([
                    'email' => __('The selected user could not be found.'),
                ]);
            }

            $organization = $this->createOrganizationRecord(
                $request,
                $organizationType,
                $parentOrganizationId,
                $actorId
            );
            $this->initializeWorkspaceDefaults($organization);
            $this->attachOwnerTeam($organization, $user, $actorId);

            if ($organizationType !== 'branch') {
                $plan = $this->resolveOptionalActivePlan($request->input('plan'));

                if ($plan) {
                    $this->createSubscriptionForMainOrganization($organization, $plan);
                }
            }

            return $organization;
        });
    }

    /**
     * Update organization.
     *
     * @param Request $request
     * @param string $uuid
     * @return \App\Models\Organization
     */
    public function update($request, $uuid)
    {
        $organization = Organization::where('uuid', $uuid)->firstOrFail();
        $organizationType = $this->resolveOrganizationType($request->input('organization_type'));
        $this->assertOrganizationTypeTransitionAllowed($organization, $organizationType);
        $parentOrganizationId = $this->resolveParentOrganizationId(
            $organizationType,
            $request->input('parent_organization_uuid'),
            $organization->id
        );

        $organization->update([
            'name' => $request->input('name'),
            'organization_type' => $organizationType,
            'parent_organization_id' => $parentOrganizationId,
            'address' => json_encode([
                'street' => $request->street,
                'city' => $request->city,
                'state' => $request->state,
                'zip' => $request->zip,
                'country' => $request->country,
            ]),
        ]);

        if ($organizationType !== 'branch' && $request->filled('plan')) {
            $plan = $this->resolveRequiredActivePlan($request->input('plan'));
            $subscription = app(SubscriptionPlanLimitService::class)->subscriptionForOrganization($organization->id);

            if($subscription){
                $subscription->update([
                    'plan_id' => $plan->id
                ]);
            } else {
                $this->createSubscriptionForMainOrganization($organization, $plan);
            }
        }

        return $organization;
    }

    public function storeTransaction($request, $uuid){
        return DB::transaction(function () use ($request, $uuid) {
            $organization = Organization::where('uuid', $uuid)->firstOrFail();
    
            $modelClass = match ($request->type) {
                'credit' => BillingCredit::class,
                'debit' => BillingDebit::class,
                'payment' => BillingPayment::class,
            };

            $transactionData = [
                'organization_id' => $organization->id,
                'amount' => $request->amount,
            ];
            
            if (in_array($request->type, ['credit', 'debit'], true)) {
                $transactionData['description'] = $request->description;
            }
            
            if ($request->type === 'payment') {
                $transactionData['processor'] = $request->method;
                $transactionData['payment_method'] = BillingPaymentMethodResolver::displayLabel($request->method, $request->method);
            }
    
            $entry = $modelClass::create($transactionData);
    
            $transaction = BillingTransaction::create([
                'organization_id' => $organization->id,
                'entity_type' => $request->type,
                'entity_id' => $entry->id,
                'description' => $request->type === 'payment'
                    ? __('Payment via :method', [
                        'method' => BillingPaymentMethodResolver::displayLabel($entry->payment_method ?? null, $entry->processor ?? $request->method),
                    ])
                    : $request->description,
                'amount' => $request->amount,
                'created_by' => auth()->user()->id
            ]);
    
            return $transaction;
        });
    }

    public function destroy($uuid){
        // Find the organization by its UUID
        $organization = Organization::where('uuid', $uuid)->first();

        if ($organization) {
            $this->assertOrganizationCanBeDeleted($organization);
            // Delete all teams associated with the organization
            Team::where('organization_id', $organization->id)->delete();
            
            // Delete the organization
            $organization->delete();

            // Return true to indicate successful deletion
            return true;
        }

        // Return false if the organization does not exist
        return false;
    }

    private function resolveOrganizationType(?string $organizationType): string
    {
        return in_array($organizationType, ['main', 'branch'], true) ? $organizationType : 'main';
    }

    private function resolveParentOrganizationId(string $organizationType, ?string $parentOrganizationUuid, ?int $currentOrganizationId = null): ?int
    {
        if ($organizationType !== 'branch') {
            return null;
        }

        if (!$parentOrganizationUuid) {
            throw ValidationException::withMessages([
                'parent_organization_uuid' => __('Parent organization is required for branch type.'),
            ]);
        }

        $parentOrganization = Organization::where('uuid', $parentOrganizationUuid)
            ->where('organization_type', 'main')
            ->whereNull('deleted_at')
            ->when($currentOrganizationId !== null, function ($query) use ($currentOrganizationId) {
                $query->where('id', '!=', $currentOrganizationId);
            })
            ->first();

        if (!$parentOrganization) {
            throw ValidationException::withMessages([
                'parent_organization_uuid' => __('Selected parent organization is invalid.'),
            ]);
        }

        $this->assertParentOrganizationCanCreateBranch($parentOrganization, $currentOrganizationId);

        return $parentOrganization->id;
    }

    public function branchCreationContextForParent(?Organization $parentOrganization): ?array
    {
        if (!$parentOrganization) {
            return null;
        }

        $guard = $this->evaluateBranchCreationGuard($parentOrganization);

        return [
            'can_create_branch' => $guard['blocking_code'] === null,
            'blocking_code' => $guard['blocking_code'],
            'blocking_message' => $guard['blocking_message'],
            'limit_snapshot' => $guard['limit_snapshot'],
        ];
    }

    private function assertParentOrganizationCanCreateBranch(Organization $parentOrganization, ?int $currentOrganizationId = null): void
    {
        $guard = $this->evaluateBranchCreationGuard($parentOrganization, $currentOrganizationId);

        if ($guard['blocking_code'] === null) {
            return;
        }

        throw ValidationException::withMessages([
            'parent_organization_uuid' => $guard['blocking_message'],
        ]);
    }

    private function evaluateBranchCreationGuard(Organization $parentOrganization, ?int $currentOrganizationId = null): array
    {
        $planLimitService = app(SubscriptionPlanLimitService::class);
        $hierarchyService = app(OrganizationHierarchyService::class);
        $activeSubscription = $planLimitService->activeSubscriptionForOrganization($parentOrganization->id);
        $latestSubscription = $planLimitService->subscriptionForOrganization($parentOrganization->id);
        $subscriptionActive = $planLimitService->isOperationallyActive($activeSubscription);
        $parentPlanMissing = $subscriptionActive && !$activeSubscription?->plan;
        $branchesLimit = $planLimitService->limitForOrganization($parentOrganization->id, 'branches_limit', -1);

        if ($branchesLimit === 0 && $latestSubscription?->plan) {
            $branchesLimit = $planLimitService->normalizeLimit(
                $latestSubscription->plan->metadataValue('branches_limit', -1),
                -1
            );
        }
        $branchesCount = Organization::where('parent_organization_id', $parentOrganization->id)
            ->where('organization_type', 'branch')
            ->whereNull('deleted_at')
            ->when($currentOrganizationId !== null, function ($query) use ($currentOrganizationId) {
                $query->where('id', '!=', $currentOrganizationId);
            })
            ->count();
        $workspaceCount = $hierarchyService->familyWorkspaceCount($parentOrganization->id, $currentOrganizationId);

        $limitReached = $branchesLimit >= 0 && $workspaceCount >= $branchesLimit;
        $blockingCode = null;
        $blockingMessage = null;

        if (!$subscriptionActive) {
            $blockingCode = 'subscription_inactive';
            $blockingMessage = __('The parent organization subscription is inactive. Please renew or upgrade your plan before creating a new branch.');
        } elseif ($parentPlanMissing) {
            $blockingCode = 'subscription_plan_missing';
            $blockingMessage = __('The parent organization must be linked to an active plan before creating a new branch.');
        } elseif ($limitReached) {
            $blockingCode = 'branch_limit_reached';
            $blockingMessage = __('Branch limit reached for this organization plan. Please upgrade your plan or contact support to add more branches.');
        }

        return [
            'blocking_code' => $blockingCode,
            'blocking_message' => $blockingMessage,
            'limit_snapshot' => [
                'used' => (int) $workspaceCount,
                'limit' => (int) $branchesLimit,
                'remaining' => $branchesLimit < 0
                    ? null
                    : max($branchesLimit - $workspaceCount, 0),
            ],
        ];
    }

    public function adminProvisioningPayload(): array
    {
        $plans = SubscriptionPlan::query()
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->get()
            ->sort(function (SubscriptionPlan $left, SubscriptionPlan $right) {
                $rankComparison = $left->tierRank() <=> $right->tierRank();

                if ($rankComparison !== 0) {
                    return $rankComparison;
                }

                return strcasecmp((string) $left->name, (string) $right->name);
            })
            ->values()
            ->map(fn (SubscriptionPlan $plan) => [
                'uuid' => $plan->uuid,
                'name' => $plan->name,
                'period' => $plan->period,
            ])
            ->all();

        $parentOrganizations = Organization::query()
            ->whereNull('deleted_at')
            ->where('organization_type', 'main')
            ->orderBy('name')
            ->get(['uuid', 'name'])
            ->map(fn (Organization $organization) => [
                'uuid' => $organization->uuid,
                'name' => $organization->name,
            ])
            ->all();

        $assignmentCatalog = app(CompanyWorkforceService::class)->adminProvisioningCatalog();

        return [
            'plans' => $plans,
            'parentOrganizations' => $parentOrganizations,
            'companies' => $assignmentCatalog['companies'],
            'companyCatalog' => $assignmentCatalog['companyCatalog'],
        ];
    }

    private function buildProfileSummary(
        Organization $organization,
        Organization $profileOwner,
        array $familyOrganizationIds,
        ?Subscription $effectiveSubscription,
        ?int $teamMembersCount = null
    ): array {
        $billingDisplayState = SubscriptionService::billingDisplayState($organization->id, $effectiveSubscription);
        $ownerUser = optional($organization->owner)->user;
        $branchesCount = Organization::query()
            ->where('parent_organization_id', $profileOwner->id)
            ->whereNull('deleted_at')
            ->count();

        return [
            'scope_name' => $profileOwner->name,
            'scope_uuid' => $profileOwner->uuid,
            'organization_type' => $organization->organization_type,
            'organization_type_label' => $organization->organization_type === 'branch'
                ? __('Branch')
                : __('Main organization'),
            'parent_organization_name' => $organization->parentOrganization?->name,
            'billing_owner_name' => $profileOwner->name,
            'subscription_managed_by_parent' => (bool) (
                $organization->organization_type === 'branch'
                && $profileOwner->id !== $organization->id
            ),
            'owner_name' => $ownerUser?->full_name,
            'owner_email' => $ownerUser?->email,
            'identifier' => $organization->identifier,
            'created_at' => $organization->getRawOriginal('created_at')
                ? DateTimeHelper::formatDate($organization->getRawOriginal('created_at'))
                : null,
            'updated_at' => $organization->getRawOriginal('updated_at')
                ? DateTimeHelper::formatDate($organization->getRawOriginal('updated_at'))
                : null,
            'family_workspaces_count' => count($familyOrganizationIds),
            'branches_count' => $branchesCount,
            'team_members_count' => $teamMembersCount ?? 0,
            'subscription' => [
                'plan_name' => $effectiveSubscription?->plan?->localizedName()
                    ?? $effectiveSubscription?->plan?->name
                    ?? ($billingDisplayState['variant'] === 'billing_pending' ? __('Not selected yet') : __('Not set')),
                'status' => $billingDisplayState['variant'],
                'status_label' => $billingDisplayState['label'],
                'valid_until' => $effectiveSubscription?->getRawOriginal('valid_until')
                    ? DateTimeHelper::formatDate($effectiveSubscription->getRawOriginal('valid_until'))
                    : null,
                'start_date' => $effectiveSubscription?->getRawOriginal('start_date')
                    ? DateTimeHelper::formatDate($effectiveSubscription->getRawOriginal('start_date'))
                    : null,
            ],
            'address' => $this->decodeAddress($organization->address),
        ];
    }

    private function buildTeamDirectory(Organization $organization, string $search = ''): array
    {
        $members = [];
        $search = trim($search);

        $activeAssignments = OrganizationEmployeeAssignment::query()
            ->with(['employee.user', 'organizationRole'])
            ->where('organization_id', $organization->id)
            ->whereNull('deleted_at')
            ->whereIn('status', ['active', 'pending'])
            ->get();

        foreach ($activeAssignments as $assignment) {
            $employee = $assignment->employee;
            $user = $employee?->user;

            if (! $user) {
                continue;
            }

            $memberKey = 'user:'.$user->id;

            $this->mergeTeamMember($members, $memberKey, [
                'id' => $user->id,
                'full_name' => trim((string) $user->full_name),
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => (int) ($user->status ?? 1),
                'updated_at' => $this->formatMembershipDate($assignment->updated_at ?? $employee?->updated_at ?? $user->updated_at),
                'sort_name' => strtolower(trim((string) $user->full_name)),
                'roles' => array_filter([(string) ($assignment->organizationRole?->name ?? '')]),
                'sources' => [__('Company-governed access')],
            ]);
        }

        $activeTeams = Team::query()
            ->with(['user', 'organizationRole'])
            ->where('organization_id', $organization->id)
            ->get();

        foreach ($activeTeams as $team) {
            $user = $team->user;

            if (! $user) {
                continue;
            }

            $memberKey = 'user:'.$user->id;

            $this->mergeTeamMember($members, $memberKey, [
                'id' => $user->id,
                'full_name' => trim((string) $user->full_name),
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => (int) ($user->status ?? 1),
                'updated_at' => $this->formatMembershipDate($team->updated_at ?? $user->updated_at),
                'sort_name' => strtolower(trim((string) $user->full_name)),
                'roles' => array_filter([(string) ($team->organizationRole?->name ?? '')]),
                'sources' => [__('Direct workspace membership')],
            ]);
        }

        $hiddenInactiveRowsCount = Team::withTrashed()
            ->where('organization_id', $organization->id)
            ->whereNotNull('deleted_at')
            ->count();

        $collapsedAssignmentDuplicates = $activeAssignments
            ->groupBy(function (OrganizationEmployeeAssignment $assignment) {
                $userId = (int) ($assignment->employee?->user_id ?? 0);
                $roleId = (int) ($assignment->organization_role_id ?? 0);

                return $userId.'|'.$roleId;
            })
            ->reduce(function (int $carry, $group) {
                return $carry + max(0, $group->count() - 1);
            }, 0);

        $rows = collect(array_values($members))
            ->map(function (array $member) {
                $roles = array_values(array_filter(array_unique($member['roles'] ?? [])));
                $sources = array_values(array_filter(array_unique($member['sources'] ?? [])));
                $primaryRole = $roles[0] ?? __('Not set');
                $roleHintParts = [];
                $primarySource = in_array(__('Company-governed access'), $sources, true)
                    ? __('Company-governed access')
                    : ($sources[0] ?? null);

                if (count($roles) > 1) {
                    $roleHintParts[] = __('Additional roles: :count', ['count' => count($roles) - 1]);
                }

                if ($primarySource) {
                    $roleHintParts[] = $primarySource;
                }

                return [
                    'id' => $member['id'],
                    'full_name' => $member['full_name'] !== '' ? $member['full_name'] : __('Not set'),
                    'email' => $member['email'] ?: __('Not set'),
                    'phone' => $member['phone'] ?: __('N/A'),
                    'role' => $primaryRole,
                    'role_hint' => implode(' • ', array_filter($roleHintParts)),
                    'role_names' => $roles,
                    'status' => $member['status'],
                    'updated_at' => $member['updated_at'] ?: __('Not set'),
                    '_sort_name' => $member['sort_name'] ?: strtolower((string) $member['email']),
                ];
            })
            ->filter(function (array $member) use ($search) {
                if ($search === '') {
                    return true;
                }

                $haystack = implode(' ', array_filter([
                    $member['full_name'] ?? null,
                    $member['email'] ?? null,
                    $member['phone'] ?? null,
                    $member['role'] ?? null,
                    implode(' ', $member['role_names'] ?? []),
                    $member['role_hint'] ?? null,
                ]));

                return str_contains(mb_strtolower($haystack), mb_strtolower($search));
            })
            ->sortBy('_sort_name')
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 10;
        $paginated = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)
                ->map(function (array $member) {
                    unset($member['_sort_name']);

                    return $member;
                })
                ->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );
        $paginated->appends(request()->query());

        return [
            'rows' => $paginated,
            'summary' => [
                'members_count' => $rows->count(),
                'hidden_inactive_rows_count' => $hiddenInactiveRowsCount,
                'collapsed_assignment_duplicates_count' => $collapsedAssignmentDuplicates,
                'notes' => array_values(array_filter([
                    [
                        'level' => 'info',
                        'message' => __('This table shows current workspace access only. Historical or removed memberships stay hidden here.'),
                    ],
                    $hiddenInactiveRowsCount > 0 ? [
                        'level' => 'warning',
                        'message' => __('Hidden inactive memberships: :count', ['count' => $hiddenInactiveRowsCount]),
                    ] : null,
                    $collapsedAssignmentDuplicates > 0 ? [
                        'level' => 'warning',
                        'message' => __('Collapsed duplicate company assignments: :count', ['count' => $collapsedAssignmentDuplicates]),
                    ] : null,
                ])),
            ],
        ];
    }

    private function mergeTeamMember(array &$members, string $memberKey, array $payload): void
    {
        if (! isset($members[$memberKey])) {
            $members[$memberKey] = [
                'id' => $payload['id'],
                'full_name' => $payload['full_name'] ?? '',
                'email' => $payload['email'] ?? '',
                'phone' => $payload['phone'] ?? null,
                'status' => $payload['status'] ?? 1,
                'updated_at' => $payload['updated_at'] ?? null,
                'sort_name' => $payload['sort_name'] ?? '',
                'roles' => [],
                'sources' => [],
            ];
        }

        $members[$memberKey]['full_name'] = $members[$memberKey]['full_name'] ?: ($payload['full_name'] ?? '');
        $members[$memberKey]['email'] = $members[$memberKey]['email'] ?: ($payload['email'] ?? '');
        $members[$memberKey]['phone'] = $members[$memberKey]['phone'] ?: ($payload['phone'] ?? null);
        $members[$memberKey]['status'] = (int) ($payload['status'] ?? $members[$memberKey]['status']);
        $members[$memberKey]['updated_at'] = $payload['updated_at'] ?? $members[$memberKey]['updated_at'];
        $members[$memberKey]['sort_name'] = $members[$memberKey]['sort_name'] ?: ($payload['sort_name'] ?? '');
        $members[$memberKey]['roles'] = array_values(array_unique(array_merge(
            $members[$memberKey]['roles'],
            $payload['roles'] ?? []
        )));
        $members[$memberKey]['sources'] = array_values(array_unique(array_merge(
            $members[$memberKey]['sources'],
            $payload['sources'] ?? []
        )));
    }

    private function formatMembershipDate($value): ?string
    {
        if (! $value) {
            return null;
        }

        if (is_object($value) && method_exists($value, 'toDateTimeString')) {
            return DateTimeHelper::formatDate($value->toDateTimeString());
        }

        return DateTimeHelper::formatDate((string) $value);
    }

    private function buildUsageSummary(int $organizationId, array $familyOrganizationIds): array
    {
        return app(OrganizationUsageSummaryService::class)->build($organizationId, $familyOrganizationIds);
    }

    private function buildBillingSummary(int $billingOrganizationId): array
    {
        $latestTransaction = BillingTransaction::query()
            ->where('organization_id', $billingOrganizationId)
            ->latest('id')
            ->first();
        $latestInvoice = BillingInvoice::query()
            ->with('plan')
            ->where('organization_id', $billingOrganizationId)
            ->orderByDesc('id')
            ->first();
        $latestPayment = BillingPayment::query()
            ->where('organization_id', $billingOrganizationId)
            ->orderByDesc('id')
            ->first();

        return [
            'account_balance' => $this->formatCurrency(
                BillingTransaction::query()
                    ->where('organization_id', $billingOrganizationId)
                    ->sum('amount')
            ),
            'transactions_count' => BillingTransaction::query()
                ->where('organization_id', $billingOrganizationId)
                ->count(),
            'invoices_count' => BillingInvoice::query()
                ->where('organization_id', $billingOrganizationId)
                ->count(),
            'payments_count' => BillingPayment::query()
                ->where('organization_id', $billingOrganizationId)
                ->count(),
            'total_invoiced' => $this->formatCurrency(
                BillingInvoice::query()
                    ->where('organization_id', $billingOrganizationId)
                    ->sum('total')
            ),
            'total_paid' => $this->formatCurrency(
                BillingPayment::query()
                    ->where('organization_id', $billingOrganizationId)
                    ->sum('amount')
            ),
            'latest_transaction' => $latestTransaction ? [
                'description' => $latestTransaction->description,
                'amount' => $this->formatCurrency($latestTransaction->amount),
                'created_at' => $latestTransaction->getRawOriginal('created_at')
                    ? DateTimeHelper::formatDate($latestTransaction->getRawOriginal('created_at'))
                    : null,
            ] : null,
            'latest_invoice' => $latestInvoice ? [
                'plan_name' => $latestInvoice->plan?->localizedName()
                    ?? $latestInvoice->plan?->name
                    ?? __('Not set'),
                'total' => $this->formatCurrency($latestInvoice->total),
                'uuid' => $latestInvoice->uuid,
                'created_at' => $latestInvoice->getRawOriginal('created_at')
                    ? DateTimeHelper::formatDate($latestInvoice->getRawOriginal('created_at'))
                    : null,
            ] : null,
            'latest_payment' => $latestPayment ? [
                'method_label' => BillingPaymentMethodResolver::displayLabel(
                    $latestPayment->payment_method,
                    $latestPayment->processor
                ),
                'amount' => $this->formatCurrency($latestPayment->amount),
                'created_at' => $latestPayment->getRawOriginal('created_at')
                    ? DateTimeHelper::formatDate($latestPayment->getRawOriginal('created_at'))
                    : null,
            ] : null,
        ];
    }

    private function buildBranchRows(int $profileOwnerId, ?int $currentOrganizationId = null): array
    {
        $branches = Organization::query()
            ->with(['owner.user', 'parentOrganization'])
            ->withCount('teams')
            ->where('parent_organization_id', $profileOwnerId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        return $branches->map(function (Organization $branch) use ($currentOrganizationId) {
            $planLimitService = app(SubscriptionPlanLimitService::class);
            $effectiveSubscription = $planLimitService->subscriptionForOrganization($branch->id);
            $operationalStatus = $planLimitService->operationalStatus($effectiveSubscription);

            return [
                'uuid' => $branch->uuid,
                'name' => $branch->name,
                'owner_name' => $branch->owner?->user?->full_name,
                'owner_email' => $branch->owner?->user?->email,
                'teams_count' => (int) $branch->teams_count,
                'updated_at' => $branch->getRawOriginal('updated_at')
                    ? DateTimeHelper::formatDate($branch->getRawOriginal('updated_at'))
                    : null,
                'is_current' => $currentOrganizationId !== null && $branch->id === $currentOrganizationId,
                'subscription_display' => [
                    'plan_name' => $effectiveSubscription?->plan?->localizedName()
                        ?? $effectiveSubscription?->plan?->name
                        ?? __('Not set'),
                    'status' => $operationalStatus,
                    'valid_until' => $effectiveSubscription?->getRawOriginal('valid_until')
                        ? DateTimeHelper::formatDate($effectiveSubscription->getRawOriginal('valid_until'))
                        : null,
                    'managed_by_parent' => true,
                    'managed_by' => $branch->parentOrganization?->name,
                ],
            ];
        })->values()->all();
    }

    private function decodeAddress(?string $address): array
    {
        if (!$address) {
            return [];
        }

        $decoded = json_decode($address, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function subscriptionStatusLabel(?string $status): string
    {
        return match ($status) {
            'active' => __('Active'),
            'trial' => __('Trial period'),
            'expired' => __('Expired'),
            'billing_pending' => __('Billing setup required'),
            default => __('Not set'),
        };
    }

    private function formatCurrency($amount): string
    {
        return number_format((float) $amount, 2);
    }

    public function createOwnedMainOrganizationShell(
        User $user,
        string $name,
        int $createdBy,
        array $address = []
    ): Organization
    {
        return $this->createOwnedMainOrganization($user, $name, $createdBy, null, $address);
    }

    public function createOwnedMainOrganization(
        User $user,
        string $name,
        int $createdBy,
        ?SubscriptionPlan $plan = null,
        array $address = []
    ): Organization
    {
        return DB::transaction(function () use ($user, $name, $createdBy, $address, $plan) {
            $organization = $this->createOwnedMainOrganizationRecord($user, $name, $createdBy, $address);

            $this->initializeWorkspaceDefaults($organization);
            $this->attachOwnerTeam($organization, $user, $createdBy);

            if ($plan) {
                $this->createSubscriptionForMainOrganization($organization, $plan);
            }

            return $organization;
        });
    }

    public function createOwnedBillableMainOrganization(
        User $user,
        string $name,
        int $createdBy,
        ?SubscriptionPlan $plan = null,
        array $address = []
    ): Organization
    {
        return $this->createOwnedMainOrganization(
            $user,
            $name,
            $createdBy,
            $plan ?: $this->resolveRequiredActivePlan(null),
            $address
        );
    }

    private function createOwnedMainOrganizationRecord(
        User $user,
        string $name,
        int $createdBy,
        array $address = []
    ): Organization
    {
        return Organization::create([
            'name' => trim($name),
            'organization_type' => 'main',
            'parent_organization_id' => null,
            'identifier' => now()->format('YmdHis') . $user->id . Str::random(4),
            'address' => $address !== [] ? json_encode($address) : null,
            'metadata' => json_encode([
                'addons' => [
                    'embedded_signup_enabled' => true,
                ],
            ]),
            'created_by' => $createdBy,
        ]);
    }

    private function createOrganizationRecord(object $request, string $organizationType, ?int $parentOrganizationId, ?int $createdBy): Organization
    {
        $timestamp = now()->format('YmdHis');
        $randomString = Str::random(4);
        $identifierSeed = $createdBy ?? auth()->id() ?? random_int(1000, 9999);

        return Organization::create([
            'name' => $request->input('name'),
            'organization_type' => $organizationType,
            'parent_organization_id' => $parentOrganizationId,
            'identifier' => $timestamp . $identifierSeed . $randomString,
            'address' => json_encode([
                'street' => $request->street,
                'city' => $request->city,
                'state' => $request->state,
                'zip' => $request->zip,
                'country' => $request->country,
            ]),
            'metadata' => json_encode([
                'addons' => [
                    'embedded_signup_enabled' => true,
                ],
            ]),
            'created_by' => $createdBy ?? auth()->id() ?? auth('admin')->id(),
        ]);
    }

    private function attachOwnerTeam(Organization $organization, User $user, ?int $createdBy): void
    {
        $ownerRole = \App\Models\OrganizationRole::where('name', 'Owner')
            ->whereNull('organization_id')
            ->first();

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $createdBy ?? auth()->id() ?? $user->id,
        ]);
    }

    private function initializeWorkspaceDefaults(Organization $organization): void
    {
        app(OrganizationDefaultRoleService::class)->ensureDefaultsForOrganization($organization);
    }

    private function createSubscriptionForMainOrganization(Organization $organization, SubscriptionPlan $plan): Subscription
    {
        $config = Setting::where('key', 'trial_period')->first();
        $trialDays = (int) ($config->value ?? 0);
        $hasTrial = $trialDays > 0;
        $validUntil = $hasTrial
            ? now()->copy()->addDays($trialDays)
            : ($plan->period === 'yearly' ? now()->copy()->addYear() : now()->copy()->addMonth());

        return Subscription::create([
            'organization_id' => $organization->id,
            'status' => $hasTrial ? 'trial' : 'active',
            'plan_id' => $plan->id,
            'start_date' => now(),
            'valid_until' => $validUntil,
        ]);
    }

    private function resolveRequiredActivePlan(?string $planUuid): SubscriptionPlan
    {
        if ($planUuid !== null && trim($planUuid) !== '') {
            $plan = SubscriptionPlan::query()
                ->where('uuid', $planUuid)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->first();

            if ($plan) {
                return $plan;
            }

            throw ValidationException::withMessages([
                'plan' => __('The selected subscription plan is invalid or inactive.'),
            ]);
        }

        $plan = SubscriptionPlan::query()
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->get()
            ->sort(function (SubscriptionPlan $left, SubscriptionPlan $right) {
                $rankComparison = $left->tierRank() <=> $right->tierRank();
                if ($rankComparison !== 0) {
                    return $rankComparison;
                }

                $priceComparison = ((float) $left->price) <=> ((float) $right->price);
                if ($priceComparison !== 0) {
                    return $priceComparison;
                }

                return $left->id <=> $right->id;
            })
            ->first();

        if ($plan) {
            return $plan;
        }

        throw ValidationException::withMessages([
            'plan' => __('No active subscription plan is available for new organizations right now.'),
        ]);
    }

    private function resolveOptionalActivePlan(?string $planUuid): ?SubscriptionPlan
    {
        $normalized = trim((string) ($planUuid ?? ''));

        if ($normalized === '') {
            return null;
        }

        return $this->resolveRequiredActivePlan($normalized);
    }

    private function assertOrganizationTypeTransitionAllowed(Organization $organization, string $requestedType): void
    {
        if ($organization->organization_type === $requestedType) {
            return;
        }

        throw ValidationException::withMessages([
            'organization_type' => __('Changing organization type directly is disabled to protect billing ownership and workspace hierarchy.'),
        ]);
    }

    private function assertOrganizationCanBeDeleted(Organization $organization): void
    {
        $hasBranches = Organization::query()
            ->where('parent_organization_id', $organization->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($organization->organization_type === 'main' && $hasBranches) {
            throw ValidationException::withMessages([
                'uuid' => __('This main organization cannot be deleted while it still has branch workspaces.'),
            ]);
        }

        $hasBillingArtifacts = Subscription::query()->where('organization_id', $organization->id)->exists()
            || BillingInvoice::query()->where('organization_id', $organization->id)->exists()
            || BillingPayment::query()->where('organization_id', $organization->id)->exists()
            || BillingTransaction::query()->where('organization_id', $organization->id)->exists();

        if ($hasBillingArtifacts) {
            throw ValidationException::withMessages([
                'uuid' => __('This organization cannot be deleted because it still has subscription or billing records.'),
            ]);
        }

        $hasOperationalArtifacts = Contact::query()
            ->where('organization_id', $organization->id)
            ->whereNull('deleted_at')
            ->exists()
            || Chat::query()
                ->where('organization_id', $organization->id)
                ->whereNull('deleted_at')
                ->exists()
            || Campaign::query()
                ->where('organization_id', $organization->id)
                ->exists()
            || AutoReply::query()
                ->where('organization_id', $organization->id)
                ->whereNull('deleted_at')
                ->exists()
            || Template::query()
                ->where('organization_id', $organization->id)
                ->whereNull('deleted_at')
                ->exists()
            || AutomationFlow::query()
                ->where('organization_id', $organization->id)
                ->whereNull('deleted_at')
                ->exists()
            || AutomationFlowRun::query()
                ->where('organization_id', $organization->id)
                ->exists();

        if ($hasOperationalArtifacts) {
            throw ValidationException::withMessages([
                'uuid' => __('This organization cannot be deleted because it still has operational data. Archive it or clean up its contacts, chats, campaigns, automations, and templates first.'),
            ]);
        }
    }
}
