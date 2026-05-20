<?php

namespace App\Services;

use App\Helpers\DateTimeHelper;
use App\Models\Organization;
use App\Models\OrganizationEmployee;
use App\Models\OrganizationRole;
use App\Models\Subscription;
use App\Models\TeamInvite;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminUserDirectoryService
{
    public function indexPayload(Request $request): array
    {
        return [
            'title' => __('Users'),
            'allowCreate' => true,
            'rows' => $this->paginateUsers($request),
            'filters' => $this->normalizedFilters($request),
            'summary' => $this->summary($request),
            'filterOptions' => $this->filterOptions(),
        ];
    }

    public function detailPayload(int $userId): array
    {
        $user = $this->userQuery()
            ->where('id', $userId)
            ->firstOrFail();

        return [
            'title' => __('View user'),
            'user' => $this->mapUserDetail($user),
        ];
    }

    private function paginateUsers(Request $request): LengthAwarePaginator
    {
        $query = $this->userQuery();
        $this->applyFilters($query, $request);

        return $query
            ->latest('users.updated_at')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (User $user) => $this->mapUserRow($user));
    }

    private function userQuery(): Builder
    {
        return User::query()
            ->with([
                'teams.organization.subscription.plan',
                'teams.organization.parentOrganization.subscription.plan',
                'teams.organizationRole',
                'organizationEmployees.mainOrganization.subscription.plan',
                'organizationEmployees.mainOrganization.parentOrganization.subscription.plan',
                'organizationEmployees.assignments.organization.subscription.plan',
                'organizationEmployees.assignments.organization.parentOrganization.subscription.plan',
                'organizationEmployees.assignments.organizationRole',
            ])
            ->where('users.role', 'user');
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $search = trim((string) $request->query('search', ''));
        $accountStatus = strtolower(trim((string) $request->query('account_status', '')));
        $verification = strtolower(trim((string) $request->query('verification', '')));
        $accessScope = strtolower(trim((string) $request->query('access_scope', '')));

        if ($search !== '') {
            $query->where(function (Builder $userQuery) use ($search) {
                $like = '%'.$search.'%';

                $userQuery->where('users.first_name', 'like', $like)
                    ->orWhere('users.last_name', 'like', $like)
                    ->orWhere('users.email', 'like', $like)
                    ->orWhere('users.phone', 'like', $like)
                    ->orWhereHas('organizationEmployees.mainOrganization', function (Builder $organizationQuery) use ($like) {
                        $organizationQuery->where('name', 'like', $like);
                    })
                    ->orWhereHas('teams.organization', function (Builder $organizationQuery) use ($like) {
                        $organizationQuery->where('name', 'like', $like);
                    });
            });
        }

        if (in_array($accountStatus, ['active', 'inactive'], true)) {
            $query->where('users.status', $accountStatus === 'active' ? 1 : 0);
        }

        if (in_array($verification, ['verified', 'unverified'], true)) {
            if ($verification === 'verified') {
                $query->whereNotNull('users.email_verified_at');
            } else {
                $query->whereNull('users.email_verified_at');
            }
        }

        switch ($accessScope) {
            case 'main_owner':
                $this->applyMainOwnerScope($query);
                break;

            case 'company_employee':
                $this->applyCompanyEmployeeScope($query);
                break;

            case 'branch_member':
                $this->applyBranchMemberScope($query);
                break;

            case 'workspace_member':
                $this->applyWorkspaceMemberScope($query);
                break;

            case 'unassigned':
                $this->applyUnassignedScope($query);
                break;
        }
    }

    private function summary(Request $request): array
    {
        $baseQuery = $this->userQuery();
        $this->applyFilters($baseQuery, $request);

        $mainOwnersQuery = clone $baseQuery;
        $this->applyMainOwnerScope($mainOwnersQuery);

        $companyMembersQuery = clone $baseQuery;
        $this->applyCompanyEmployeeScope($companyMembersQuery);

        return [
            'users' => (clone $baseQuery)->count(),
            'verified' => (clone $baseQuery)->whereNotNull('users.email_verified_at')->count(),
            'inactive' => (clone $baseQuery)->where('users.status', '!=', 1)->count(),
            'main_owners' => $mainOwnersQuery->count(),
            'company_members' => $companyMembersQuery->count(),
        ];
    }

    private function filterOptions(): array
    {
        return [
            'account_status' => [
                ['value' => '', 'label' => __('All account statuses')],
                ['value' => 'active', 'label' => __('Active')],
                ['value' => 'inactive', 'label' => __('Inactive')],
            ],
            'verification' => [
                ['value' => '', 'label' => __('All verification states')],
                ['value' => 'verified', 'label' => __('Verified email')],
                ['value' => 'unverified', 'label' => __('Unverified email')],
            ],
            'access_scope' => [
                ['value' => '', 'label' => __('All user types')],
                ['value' => 'main_owner', 'label' => __('Main organization owner')],
                ['value' => 'company_employee', 'label' => __('Company employee')],
                ['value' => 'branch_member', 'label' => __('Branch workspace member')],
                ['value' => 'workspace_member', 'label' => __('Workspace member')],
                ['value' => 'unassigned', 'label' => __('No organization access')],
            ],
        ];
    }

    private function normalizedFilters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'account_status' => trim((string) $request->query('account_status', '')),
            'verification' => trim((string) $request->query('verification', '')),
            'access_scope' => trim((string) $request->query('access_scope', '')),
        ];
    }

    private function mapUserRow(User $user): array
    {
        $meta = $this->deriveUserMeta($user);

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => trim((string) $user->full_name),
            'email' => $user->email,
            'phone' => $user->phone,
            'account_status' => $this->accountStatusKey($user),
            'account_status_label' => $this->accountStatusLabel($user),
            'email_verified' => $user->email_verified_at !== null,
            'email_verified_label' => $user->email_verified_at ? __('Verified email') : __('Unverified email'),
            'organization_role' => $meta['organization_role'],
            'organization_role_label' => $meta['organization_role_label'],
            'user_type' => $meta['primary_type'],
            'user_type_label' => $meta['primary_type_label'],
            'primary_company_name' => $meta['primary_company_name'],
            'access_summary' => $meta['access_summary'],
            'subscription_issue_count' => $meta['subscription_issue_count'],
            'type_flags' => $meta['type_flags'],
            'companies_count' => $meta['companies_count'],
            'workspaces_count' => $meta['workspaces_count'],
            'main_owned_count' => $meta['main_owned_count'],
            'can_delete_account' => ($meta['main_owned_count'] ?? 0) === 0,
            'companies_preview' => $meta['companies_preview'],
            'updated_at' => $this->formatDate($user->updated_at),
        ];
    }

    private function mapUserDetail(User $user): array
    {
        $meta = $this->deriveUserMeta($user);
        $address = $this->decodeAddress($user->address);
        $companyAssignmentKeys = [];
        $organizationEmployees = $user->organizationEmployees
            ->sortBy(function (OrganizationEmployee $employee) {
                return strtolower((string) $employee->mainOrganization?->name);
            })
            ->values()
            ->map(function (OrganizationEmployee $employee) use (&$companyAssignmentKeys) {
                $assignments = $employee->assignments
                    ->sortBy(fn ($assignment) => [
                        $assignment->organization?->organization_type === 'main' ? 0 : 1,
                        strtolower((string) $assignment->organization?->name),
                    ])
                    ->values()
                    ->map(function ($assignment) use (&$companyAssignmentKeys) {
                        $organization = $assignment->organization;
                        $companyAssignmentKeys[$this->assignmentFingerprint(
                            $assignment->organization_id,
                            $assignment->organization_role_id,
                        )] = true;

                        return [
                            'uuid' => $assignment->uuid,
                            'workspace_name' => $organization?->name,
                            'workspace_uuid' => $organization?->uuid,
                            'workspace_type' => $organization?->organization_type,
                            'parent_workspace_name' => $organization?->parentOrganization?->name,
                            'role_name' => $assignment->organizationRole?->name,
                            'role_permissions' => $this->rolePermissionsSummary($assignment->organizationRole),
                            'subscription' => $this->organizationSubscriptionSnapshot($organization),
                            'status' => $assignment->status,
                            'status_label' => ucfirst((string) $assignment->status),
                            'is_owner_role' => $assignment->organizationRole?->isOwnerRole() ?? false,
                            'assigned_at' => $this->formatDate($assignment->assigned_at),
                            'activated_at' => $this->formatDate($assignment->activated_at),
                            'suspended_at' => $this->formatDate($assignment->suspended_at),
                        ];
                    })
                    ->all();

                return [
                    'uuid' => $employee->uuid,
                    'company_name' => $employee->mainOrganization?->name,
                    'company_uuid' => $employee->mainOrganization?->uuid,
                    'subscription' => $this->organizationSubscriptionSnapshot($employee->mainOrganization),
                    'status' => $employee->status,
                    'status_label' => ucfirst((string) $employee->status),
                    'invited_at' => $this->formatDate($employee->invited_at),
                    'invite_expires_at' => $this->formatDate($employee->invite_expires_at),
                    'accepted_at' => $this->formatDate($employee->accepted_at),
                    'suspended_at' => $this->formatDate($employee->suspended_at),
                    'assignments_count' => count($assignments),
                    'assignments' => $assignments,
                ];
            })
            ->all();

        $teamMemberships = $user->teams
            ->sortBy(fn ($team) => [
                $team->organization?->organization_type === 'main' ? 0 : 1,
                strtolower((string) $team->organization?->name),
            ])
            ->values()
            ->map(function ($team) use ($companyAssignmentKeys) {
                $organization = $team->organization;
                $isCompanyGovernedAccess = isset($companyAssignmentKeys[$this->assignmentFingerprint(
                    $team->organization_id,
                    $team->organization_role_id,
                )]);
                $company = $organization?->organization_type === 'branch'
                    ? $organization?->parentOrganization
                    : $organization;

                return [
                    'id' => $team->id,
                    'workspace_name' => $organization?->name,
                    'workspace_uuid' => $organization?->uuid,
                    'workspace_type' => $organization?->organization_type,
                    'company_name' => $company?->name,
                    'parent_workspace_name' => $organization?->parentOrganization?->name,
                    'role_name' => $team->organizationRole?->name,
                    'role_permissions' => $this->rolePermissionsSummary($team->organizationRole),
                    'subscription' => $this->organizationSubscriptionSnapshot($organization),
                    'access_source' => $isCompanyGovernedAccess ? 'company_assignment' : 'direct_workspace',
                    'access_source_label' => $isCompanyGovernedAccess ? __('Company-governed access') : __('Direct workspace membership'),
                    'is_owner_role' => $team->organizationRole?->isOwnerRole() ?? false,
                    'status' => $team->status,
                    'status_label' => ucfirst((string) $team->status),
                    'created_at' => $this->formatDate($team->created_at),
                ];
            })
            ->all();

        $workspaceInvites = TeamInvite::query()
            ->leftJoin('organizations', 'organizations.id', '=', 'team_invites.organization_id')
            ->leftJoin('organization_roles', 'organization_roles.id', '=', 'team_invites.organization_role_id')
            ->where('team_invites.email', strtolower((string) $user->email))
            ->orderByDesc('team_invites.expire_at')
            ->get([
                'team_invites.email',
                'team_invites.code',
                'team_invites.expire_at',
                'organizations.name as organization_name',
                'organization_roles.name as role_name',
            ])
            ->map(fn ($invite) => [
                'email' => $invite->email,
                'organization_name' => $invite->organization_name,
                'role_name' => $invite->role_name,
                'expire_at' => $this->formatDate($invite->expire_at),
            ])
            ->all();

        return [
            'id' => $user->id,
            'full_name' => trim((string) $user->full_name),
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'language' => $user->language,
            'role' => $user->role,
            'status' => $this->accountStatusKey($user),
            'status_label' => $this->accountStatusLabel($user),
            'email_verified' => $user->email_verified_at !== null,
            'email_verified_at' => $this->formatDate($user->email_verified_at),
            'created_at' => $this->formatDate($user->created_at),
            'updated_at' => $this->formatDate($user->updated_at),
            'type' => [
                'primary' => $meta['primary_type'],
                'primary_label' => $meta['primary_type_label'],
                'organization_role' => $meta['organization_role'],
                'organization_role_label' => $meta['organization_role_label'],
                'flags' => $meta['type_flags'],
            ],
            'counts' => [
                'companies' => $meta['companies_count'],
                'workspaces' => $meta['workspaces_count'],
                'main_owned' => $meta['main_owned_count'],
                'branch_owner_anomalies' => $meta['branch_owner_count'],
                'pending_company_invites' => $meta['pending_company_invites'],
                'subscription_issues' => $meta['subscription_issue_count'],
                'workspace_invites' => count($workspaceInvites),
            ],
            'owned_main_organizations' => $meta['owned_main_organizations'],
            'branch_owner_anomalies' => $meta['branch_owner_organizations'],
            'companies' => $organizationEmployees,
            'direct_workspaces' => $teamMemberships,
            'workspace_invites' => $workspaceInvites,
            'actions' => [
                'can_suspend_account' => (int) ($user->status ?? 1) === 1,
                'can_restore_account' => (int) ($user->status ?? 1) !== 1,
                'can_delete_account' => ($meta['main_owned_count'] ?? 0) === 0,
                'can_edit_account' => true,
            ],
            'business_rules' => $this->businessRules($user, $meta),
            'address' => [
                'street' => $address['street'] ?? null,
                'city' => $address['city'] ?? null,
                'state' => $address['state'] ?? null,
                'zip' => $address['zip'] ?? null,
                'country' => $address['country'] ?? null,
            ],
        ];
    }

    private function deriveUserMeta(User $user): array
    {
        $teams = $user->teams->filter(fn ($team) => $team->organization !== null)->values();
        $organizationEmployees = $user->organizationEmployees->filter(fn ($employee) => $employee->mainOrganization !== null)->values();

        $ownedMainOrganizations = $teams
            ->filter(fn ($team) => $team->organizationRole?->isOwnerRole() && $team->organization?->organization_type === 'main')
            ->map(fn ($team) => $team->organization)
            ->unique('id')
            ->values();

        $branchOwnerOrganizations = $teams
            ->filter(fn ($team) => $team->organizationRole?->isOwnerRole() && $team->organization?->organization_type === 'branch')
            ->map(fn ($team) => $team->organization)
            ->unique('id')
            ->values();

        $companyNameMap = [];
        $subscriptionIssueCompanies = [];

        foreach ($organizationEmployees as $employee) {
            if ($employee->mainOrganization) {
                $companyNameMap[(int) $employee->mainOrganization->id] = $employee->mainOrganization->name;
                $subscription = $this->organizationSubscriptionSnapshot($employee->mainOrganization);

                if (in_array($subscription['status_key'], ['inactive', 'expired', 'none'], true)) {
                    $subscriptionIssueCompanies[(int) $employee->mainOrganization->id] = $employee->mainOrganization->name;
                }
            }
        }

        foreach ($teams as $team) {
            $organization = $team->organization;
            if (! $organization) {
                continue;
            }

            $company = $organization->organization_type === 'branch'
                ? $organization->parentOrganization
                : $organization;

            if ($company) {
                $companyNameMap[(int) $company->id] = $company->name;
                $subscription = $this->organizationSubscriptionSnapshot($organization);

                if (in_array($subscription['status_key'], ['inactive', 'expired', 'none'], true)) {
                    $subscriptionIssueCompanies[(int) $company->id] = $company->name;
                }
            }
        }

        $workspaceIds = collect();

        foreach ($organizationEmployees as $employee) {
            foreach ($employee->assignments as $assignment) {
                if ($assignment->organization_id) {
                    $workspaceIds->push((int) $assignment->organization_id);
                }
            }
        }

        foreach ($teams as $team) {
            if ($team->organization_id) {
                $workspaceIds->push((int) $team->organization_id);
            }
        }

        $companiesPreview = collect($companyNameMap)
            ->filter()
            ->values()
            ->take(3)
            ->all();

        $typeFlags = [];

        if ($ownedMainOrganizations->isNotEmpty()) {
            $typeFlags[] = __('Main organization owner');
        }

        if (count($companyNameMap) > 1) {
            $typeFlags[] = __('Multi-company member');
        }

        if ($organizationEmployees->isNotEmpty()) {
            $typeFlags[] = __('Company employee');
        }

        if ($teams->contains(fn ($team) => $team->organization?->organization_type === 'branch')) {
            $typeFlags[] = __('Branch workspace member');
        }

        if ($branchOwnerOrganizations->isNotEmpty()) {
            $typeFlags[] = __('Branch owner anomaly');
        }

        if (empty($companyNameMap) && $workspaceIds->unique()->isEmpty()) {
            $typeFlags[] = __('No organization access');
        }

        $primaryType = 'workspace_member';
        $primaryTypeLabel = __('Workspace member');

        if ($ownedMainOrganizations->isNotEmpty()) {
            $primaryType = 'main_owner';
            $primaryTypeLabel = __('Main organization owner');
        } elseif ($organizationEmployees->isNotEmpty()) {
            $primaryType = 'company_employee';
            $primaryTypeLabel = __('Company employee');
        } elseif ($teams->contains(fn ($team) => $team->organization?->organization_type === 'branch')) {
            $primaryType = 'branch_member';
            $primaryTypeLabel = __('Branch workspace member');
        } elseif (empty($companyNameMap) && $workspaceIds->unique()->isEmpty()) {
            $primaryType = 'unassigned';
            $primaryTypeLabel = __('No organization access');
        }

        $pendingCompanyInvites = $organizationEmployees
            ->where('status', 'pending')
            ->count();
        $companiesCount = count($companyNameMap);
        $workspacesCount = $workspaceIds->unique()->count();
        $hasOwnerRole = $ownedMainOrganizations->isNotEmpty() || $branchOwnerOrganizations->isNotEmpty();
        $organizationRole = 'unassigned';
        $organizationRoleLabel = __('No organization access');

        if ($hasOwnerRole) {
            $organizationRole = 'owner';
            $organizationRoleLabel = __('Owner');
        } elseif ($companiesCount > 0 || $workspacesCount > 0) {
            $organizationRole = 'employee';
            $organizationRoleLabel = __('Employee');
        }

        $accessSummaryParts = [];

        if ($companiesCount > 0) {
            $accessSummaryParts[] = __('Companies').': '.$companiesCount;
        }

        if ($workspacesCount > 0) {
            $accessSummaryParts[] = __('Workspaces').': '.$workspacesCount;
        }

        if ($pendingCompanyInvites > 0) {
            $accessSummaryParts[] = __('Pending invites').': '.$pendingCompanyInvites;
        }

        $accessSummary = $accessSummaryParts !== []
            ? implode(' / ', $accessSummaryParts)
            : __('No organization access');

        return [
            'primary_type' => $primaryType,
            'primary_type_label' => $primaryTypeLabel,
            'organization_role' => $organizationRole,
            'organization_role_label' => $organizationRoleLabel,
            'type_flags' => array_values(array_unique($typeFlags)),
            'companies_count' => $companiesCount,
            'workspaces_count' => $workspacesCount,
            'main_owned_count' => $ownedMainOrganizations->count(),
            'branch_owner_count' => $branchOwnerOrganizations->count(),
            'pending_company_invites' => $pendingCompanyInvites,
            'primary_company_name' => $companiesPreview[0] ?? null,
            'access_summary' => $accessSummary,
            'subscription_issue_count' => count($subscriptionIssueCompanies),
            'companies_preview' => $companiesPreview,
            'owned_main_organizations' => $ownedMainOrganizations
                ->map(fn ($organization) => [
                    'uuid' => $organization->uuid,
                    'name' => $organization->name,
                ])
                ->all(),
            'branch_owner_organizations' => $branchOwnerOrganizations
                ->map(fn ($organization) => [
                    'uuid' => $organization->uuid,
                    'name' => $organization->name,
                ])
                ->all(),
        ];
    }

    private function applyMainOwnerScope(Builder $query): void
    {
        $query->whereHas('teams', function (Builder $teamQuery) {
            $this->scopeMainOwnerTeams($teamQuery);
        });
    }

    private function applyCompanyEmployeeScope(Builder $query): void
    {
        $query->whereHas('organizationEmployees')
            ->whereDoesntHave('teams', function (Builder $teamQuery) {
                $this->scopeMainOwnerTeams($teamQuery);
            });
    }

    private function applyBranchMemberScope(Builder $query): void
    {
        $query->whereHas('teams.organization', fn (Builder $organizationQuery) => $organizationQuery->where('organization_type', 'branch'))
            ->whereDoesntHave('organizationEmployees')
            ->whereDoesntHave('teams', function (Builder $teamQuery) {
                $this->scopeMainOwnerTeams($teamQuery);
            });
    }

    private function applyWorkspaceMemberScope(Builder $query): void
    {
        $query->whereHas('teams.organization', fn (Builder $organizationQuery) => $organizationQuery->where('organization_type', 'main'))
            ->whereDoesntHave('organizationEmployees')
            ->whereDoesntHave('teams.organization', fn (Builder $organizationQuery) => $organizationQuery->where('organization_type', 'branch'))
            ->whereDoesntHave('teams', function (Builder $teamQuery) {
                $this->scopeMainOwnerTeams($teamQuery);
            });
    }

    private function applyUnassignedScope(Builder $query): void
    {
        $query->whereDoesntHave('teams')
            ->whereDoesntHave('organizationEmployees');
    }

    private function scopeMainOwnerTeams(Builder $teamQuery): void
    {
        $teamQuery
            ->whereHas('organization', fn (Builder $organizationQuery) => $organizationQuery->where('organization_type', 'main'))
            ->whereHas('organizationRole', fn (Builder $roleQuery) => $roleQuery
                ->whereNull('organization_id')
                ->where('name', 'Owner'));
    }

    private function assignmentFingerprint(?int $organizationId, ?int $roleId): string
    {
        return (int) $organizationId.':'.(int) $roleId;
    }

    private function billingOrganizationFor(?Organization $organization): ?Organization
    {
        if (! $organization) {
            return null;
        }

        if ($organization->organization_type === 'branch' && $organization->parentOrganization) {
            return $organization->parentOrganization;
        }

        return $organization;
    }

    private function organizationSubscriptionSnapshot(?Organization $organization): array
    {
        $billingOrganization = $this->billingOrganizationFor($organization);
        /** @var Subscription|null $subscription */
        $subscription = $billingOrganization?->subscription;
        $statusKey = $subscription ? $this->subscriptionStatusKey($subscription) : 'none';

        return [
            'status_key' => $statusKey,
            'status_label' => $this->subscriptionStatusLabel($statusKey),
            'plan_name' => $subscription?->plan?->display_name ?? $subscription?->plan?->name ?? null,
            'valid_until' => $subscription
                ? $this->formatDate($subscription->getRawOriginal('valid_until') ?? $subscription->valid_until)
                : null,
            'managed_from_parent' => $organization?->organization_type === 'branch' && $billingOrganization?->id !== $organization?->id,
            'billing_organization_name' => $billingOrganization?->name,
        ];
    }

    private function subscriptionStatusKey(Subscription $subscription): string
    {
        $rawValidUntil = $subscription->getRawOriginal('valid_until') ?? $subscription->valid_until;
        $validUntil = $rawValidUntil ? Carbon::parse($rawValidUntil) : null;

        if ($validUntil && $validUntil->isPast()) {
            return 'expired';
        }

        return match (strtolower((string) $subscription->status)) {
            'active' => 'active',
            'trial' => 'trial',
            'expired' => 'expired',
            default => 'inactive',
        };
    }

    private function subscriptionStatusLabel(string $statusKey): string
    {
        return match ($statusKey) {
            'active' => __('Active'),
            'trial' => __('Trial'),
            'expired' => __('Expired'),
            'inactive' => __('Inactive'),
            default => __('No active subscription'),
        };
    }

    private function rolePermissionsSummary(?OrganizationRole $role): array
    {
        if (! $role) {
            return [
                'count' => 0,
                'is_full_access' => false,
                'label' => __('Not set'),
            ];
        }

        $permissions = $role->getPermissionsArray();
        $isFullAccess = $role->isOwnerRole() || in_array('*', $permissions, true);

        return [
            'count' => count($permissions),
            'is_full_access' => $isFullAccess,
            'label' => $isFullAccess
                ? __('All Permissions')
                : __('Permissions').': '.count($permissions),
        ];
    }

    private function accountStatusKey(User $user): string
    {
        return (int) $user->status === 1 ? 'active' : 'inactive';
    }

    private function accountStatusLabel(User $user): string
    {
        return (int) $user->status === 1 ? __('Active') : __('Inactive');
    }

    private function businessRules(User $user, array $meta): array
    {
        $rules = [];

        if (($meta['main_owned_count'] ?? 0) > 0) {
            $rules[] = [
                'level' => 'warning',
                'message' => __('This account owns one or more main organizations. Transfer ownership before deleting the platform account.'),
            ];
        }

        if (($meta['companies_count'] ?? 0) > 0) {
            $rules[] = [
                'level' => 'info',
                'message' => __('This account is linked to company employee records. Suspending the platform account keeps those records, but deleting the account removes the linked memberships.'),
            ];

            $rules[] = [
                'level' => 'info',
                'message' => __('Operational workspace access still depends on the company subscription and the assigned role permissions.'),
            ];
        }

        if (($meta['companies_count'] ?? 0) > 1) {
            $rules[] = [
                'level' => 'warning',
                'message' => __('This account belongs to multiple companies. Review cross-company access before changing its state or deleting it.'),
            ];
        }

        if (($meta['branch_owner_count'] ?? 0) > 0) {
            $rules[] = [
                'level' => 'warning',
                'message' => __('This account still has owner access on one or more branch workspaces. Review these assignments and replace them with company-governed access where possible.'),
            ];
        }

        if (($meta['pending_company_invites'] ?? 0) > 0) {
            $rules[] = [
                'level' => 'info',
                'message' => __('This account has pending company invitations or assignments that may create additional access after acceptance.'),
            ];
        }

        if (($meta['subscription_issue_count'] ?? 0) > 0) {
            $rules[] = [
                'level' => 'warning',
                'message' => __('This account is linked to one or more companies with inactive or missing subscriptions. Membership records may remain visible here while operational access stays limited until billing is restored.'),
            ];
        }

        if (($meta['companies_count'] ?? 0) === 0 && ($meta['workspaces_count'] ?? 0) === 0) {
            $rules[] = [
                'level' => 'info',
                'message' => __('This account currently has no organization access. Suspending it only blocks sign-in.'),
            ];
        }

        if ((int) ($user->status ?? 1) !== 1) {
            $rules[] = [
                'level' => 'info',
                'message' => __('This platform account is suspended. Restoring it re-enables sign-in without recreating company memberships.'),
            ];
        }

        if ((int) ($user->status ?? 1) !== 1 && ($meta['main_owned_count'] ?? 0) > 0) {
            $rules[] = [
                'level' => 'warning',
                'message' => __('Suspending the platform account does not transfer organization ownership. Any owned main organizations still require an explicit ownership transfer.'),
            ];
        }

        return $rules;
    }

    private function formatDate($value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return DateTimeHelper::formatDate((string) $value);
        } catch (\Throwable $exception) {
            return (string) $value;
        }
    }

    private function decodeAddress(?string $value): array
    {
        if (! $value) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
