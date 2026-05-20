<?php

namespace App\Services;

use App\Helpers\Email;
use App\Http\Requests\StoreCompanyEmployee;
use App\Http\Requests\UpdateCompanyEmployee;
use App\Http\Requests\StoreUserInvite;
use App\Models\Organization;
use App\Models\OrganizationEmployee;
use App\Models\OrganizationEmployeeAssignment;
use App\Models\OrganizationRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompanyWorkforceService
{
    public function __construct(
        private readonly OrganizationHierarchyService $organizationHierarchyService,
        private readonly OrganizationUserSeatUsageService $organizationUserSeatUsageService,
        private readonly SubscriptionFeatureUsageService $subscriptionFeatureUsageService,
    ) {
    }

    public function currentCompany(): Organization
    {
        $organizationId = (int) session()->get('current_organization');
        $organization = Organization::query()
            ->with('parentOrganization')
            ->where('id', $organizationId)
            ->whereNull('deleted_at')
            ->first();

        if (! $organization) {
            throw ValidationException::withMessages([
                'organization' => __('Select a workspace first.'),
            ]);
        }

        if ($organization->organization_type === 'branch' && $organization->parentOrganization) {
            $parentOrganization = $organization->parentOrganization;

            if (app(PermissionService::class)->isOwner($parentOrganization->id)) {
                return $parentOrganization;
            }
        }

        if ($organization->organization_type !== 'main') {
            throw ValidationException::withMessages([
                'organization' => __('Switch to the parent organization to manage company team assignments.'),
            ]);
        }

        if (! app(PermissionService::class)->isOwner($organization->id)) {
            abort(403, __('Only owners can manage company team members'));
        }

        return $organization;
    }

    public function indexPayload($request): array
    {
        $company = $this->currentCompany();
        $this->prepareCompanyContext($company);
        $search = trim((string) $request->query('search', ''));

        return [
            'title' => __('Company team'),
            'rows' => $this->paginateEmployees($company, $search),
            'filters' => $request->all(),
            'workspaces' => $this->workspaceOptions($company),
            'workspaceRoleCatalog' => $this->workspaceRoleCatalog($company),
            'summary' => $this->summary($company),
            'company' => [
                'uuid' => $company->uuid,
                'name' => $company->name,
            ],
        ];
    }

    public function adminProvisioningCatalog(): array
    {
        $companies = Organization::query()
            ->where('organization_type', 'main')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        $companyOptions = [];
        $companyCatalog = [];

        foreach ($companies as $company) {
            $subscriptionDisplayState = SubscriptionService::billingDisplayState($company->id);
            $canAssignEmployees = in_array($subscriptionDisplayState['operational_status'], ['active', 'trial'], true);

            $companyOptions[] = [
                'value' => (string) $company->uuid,
                'label' => $company->name,
                'disableTranslation' => true,
                'meta' => [
                    'subscription_status' => $subscriptionDisplayState['variant'],
                    'subscription_status_label' => $subscriptionDisplayState['label'],
                    'can_assign_employees' => $canAssignEmployees,
                ],
            ];

            $companyCatalog[(string) $company->uuid] = [
                'workspaces' => $this->workspaceOptions($company),
                'workspaceRoleCatalog' => $this->workspaceRoleCatalog($company),
                'subscriptionDisplayState' => $subscriptionDisplayState,
                'canAssignEmployees' => $canAssignEmployees,
                'blockingMessage' => $canAssignEmployees
                    ? null
                    : __('Activate billing for this company before assigning employees and workspace access.'),
            ];
        }

        return [
            'companies' => $companyOptions,
            'companyCatalog' => $companyCatalog,
        ];
    }

    public function provisionEmployeeAccessForCompany(
        Organization $company,
        User $user,
        array $assignments,
        ?int $actorId = null
    ): OrganizationEmployee {
        $this->prepareCompanyContext($company);
        $this->assertCompanyAllowsEmployeeProvisioning($company);

        $normalizedAssignments = $this->normalizeAssignments($company, $assignments);
        $email = strtolower(trim((string) $user->email));
        $actorId = $actorId ?: auth()->id() ?: Auth::guard('admin')->id() ?: $user->id;

        return DB::transaction(function () use ($company, $normalizedAssignments, $email, $user, $actorId) {
            $employee = OrganizationEmployee::query()
                ->withTrashed()
                ->where('main_organization_id', $company->id)
                ->where(function ($query) use ($user, $email) {
                    $query->where('user_id', $user->id)
                        ->orWhere('email', $email);
                })
                ->latest('id')
                ->first();

            $existingActiveEmployee = OrganizationEmployee::query()
                ->where('main_organization_id', $company->id)
                ->whereNull('deleted_at')
                ->where(function ($query) use ($user, $email, $employee) {
                    $query->where('user_id', $user->id)
                        ->orWhere('email', $email);

                    if ($employee?->id) {
                        $query->where('id', '!=', $employee->id);
                    }
                })
                ->first();

            if ($existingActiveEmployee) {
                throw ValidationException::withMessages([
                    'email' => __('This employee already exists in the selected company. Edit their assignments instead.'),
                ]);
            }

            if (! $employee) {
                $employee = new OrganizationEmployee([
                    'main_organization_id' => $company->id,
                    'email' => $email,
                ]);
            }

            $this->assertTeamCapacity($company, $employee->exists ? $employee : null, $user, $email);

            if ($employee->trashed()) {
                $employee->restore();
            }

            $employee->forceFill([
                'user_id' => $user->id,
                'email' => $email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'status' => 'active',
                'invited_by' => $actorId,
                'invited_at' => now(),
                'invite_code' => null,
                'invite_expires_at' => null,
                'accepted_at' => $employee->accepted_at ?? now(),
                'suspended_at' => null,
                'metadata' => array_merge($employee->metadata ?? [], [
                    'source' => 'admin_user_create_employee_assignment',
                ]),
            ])->save();

            $removedOrganizationIds = $this->syncEmployeeAssignments(
                $employee,
                $normalizedAssignments,
                'active',
                'admin_user_create_employee_assignment'
            );

            if ($removedOrganizationIds !== []) {
                $this->removeOperationalTeams($employee, $removedOrganizationIds);
            }

            $this->syncOperationalTeams($employee->fresh(['assignments.organizationRole']));

            return $employee->fresh(['user', 'assignments.organization', 'assignments.organizationRole']);
        });
    }

    public function invite(StoreCompanyEmployee $request): OrganizationEmployee
    {
        $company = $this->currentCompany();
        $this->prepareCompanyContext($company);
        $email = strtolower(trim((string) $request->string('email')));
        $user = $this->findUserByEmail($email);
        $firstName = trim((string) $request->input('first_name', ''));
        $lastName = trim((string) $request->input('last_name', ''));

        $normalizedAssignments = $this->normalizeAssignments(
            $company,
            (array) $request->input('assignments', [])
        );

        return DB::transaction(function () use ($company, $email, $user, $normalizedAssignments, $firstName, $lastName) {
            $employee = OrganizationEmployee::query()
                ->where('main_organization_id', $company->id)
                ->where('email', $email)
                ->whereNull('deleted_at')
                ->first();

            if ($employee) {
                if ($employee->status === 'active') {
                    throw ValidationException::withMessages([
                        'email' => __('This employee already exists in the company team. Edit their assignments instead.'),
                    ]);
                }

                if ($employee->status === 'suspended') {
                    throw ValidationException::withMessages([
                        'email' => __('This employee is suspended. Use Edit or Restore instead.'),
                    ]);
                }

                throw ValidationException::withMessages([
                    'email' => __('An invite is already pending for this employee. Use Edit or Resend invite instead.'),
                ]);
            }

            $deletedEmployee = OrganizationEmployee::query()
                ->withTrashed()
                ->where('main_organization_id', $company->id)
                ->where('email', $email)
                ->whereNotNull('deleted_at')
                ->latest('deleted_at')
                ->first();

            $employee = $deletedEmployee ?: new OrganizationEmployee([
                'main_organization_id' => $company->id,
                'email' => $email,
            ]);

            $targetUser = $employee->user ?? $user;
            $this->assertTeamCapacity($company, $employee->exists ? $employee : null, $targetUser, $email);

            if ($employee->trashed()) {
                $employee->restore();
            }

            $shouldActivateImmediately = $targetUser !== null && $employee->accepted_at !== null;
            $status = $shouldActivateImmediately ? 'active' : 'pending';

            $employee->forceFill([
                'user_id' => $targetUser?->id,
                'email' => $email,
                'first_name' => $targetUser?->first_name ?? ($firstName !== '' ? $firstName : $employee->first_name),
                'last_name' => $targetUser?->last_name ?? ($lastName !== '' ? $lastName : $employee->last_name),
                'status' => $status,
                'invited_by' => auth()->id(),
                'invited_at' => now(),
                'invite_code' => $status === 'pending' ? $this->generateInviteCode($company->id) : null,
                'invite_expires_at' => $status === 'pending' ? now()->addDay() : null,
                'accepted_at' => $status === 'active' ? ($employee->accepted_at ?? now()) : $employee->accepted_at,
                'suspended_at' => null,
                'metadata' => array_merge($employee->metadata ?? [], [
                    'source' => $deletedEmployee ? 'company_team_restore' : 'company_team_invite',
                ]),
            ])->save();

            $this->syncEmployeeAssignments(
                $employee,
                $normalizedAssignments,
                $status,
                $deletedEmployee ? 'company_team_restore' : 'company_team_invite'
            );

            if ($status === 'active') {
                $this->syncOperationalTeams($employee->fresh(['assignments.organizationRole']));
            } else {
                $this->removeOperationalTeams($employee);
                $this->sendCompanyInvite($employee);
            }

            return $employee->fresh(['user', 'assignments.organization', 'assignments.organizationRole']);
        });
    }

    public function resendInvite(string $employeeUuid): OrganizationEmployee
    {
        $company = $this->currentCompany();
        $this->prepareCompanyContext($company);

        $employee = $this->findCompanyEmployee($company, $employeeUuid, [
            'user',
            'assignments.organization',
            'assignments.organizationRole',
        ]);

        if (! $employee) {
            throw ValidationException::withMessages([
                'employee' => __('Employee not found!'),
            ]);
        }

        if ($employee->status !== 'pending') {
            throw ValidationException::withMessages([
                'employee' => __('Only pending invited employees can receive a new invite link.'),
            ]);
        }

        if ($employee->assignments->isEmpty()) {
            throw ValidationException::withMessages([
                'employee' => __('This employee has no active assignments to invite.'),
            ]);
        }

        return DB::transaction(function () use ($employee, $company) {
            $employee->forceFill([
                'status' => 'pending',
                'invited_by' => auth()->id(),
                'invited_at' => now(),
                'invite_code' => $this->generateInviteCode($company->id),
                'invite_expires_at' => now()->addDay(),
                'accepted_at' => null,
                'suspended_at' => null,
                'metadata' => array_merge($employee->metadata ?? [], [
                    'source' => 'company_team_resend_invite',
                ]),
            ])->save();

            OrganizationEmployeeAssignment::query()
                ->where('organization_employee_id', $employee->id)
                ->whereNull('deleted_at')
                ->update([
                    'status' => 'pending',
                    'assigned_by' => auth()->id(),
                    'assigned_at' => now(),
                    'activated_at' => null,
                    'suspended_at' => null,
                    'updated_at' => now(),
                ]);

            $this->removeOperationalTeams($employee);
            $this->sendCompanyInvite($employee);

            return $employee->fresh(['user', 'assignments.organization', 'assignments.organizationRole']);
        });
    }

    public function suspendEmployee(string $employeeUuid): OrganizationEmployee
    {
        $company = $this->currentCompany();
        $this->prepareCompanyContext($company);

        $employee = $this->findCompanyEmployee($company, $employeeUuid, [
            'assignments.organizationRole',
        ]);

        if (! $employee) {
            throw ValidationException::withMessages([
                'employee' => __('Employee not found!'),
            ]);
        }

        if ((int) $employee->user_id === (int) auth()->id()) {
            throw ValidationException::withMessages([
                'employee' => __('You cannot suspend your own company access from here.'),
            ]);
        }

        if ($employee->assignments->contains(fn (OrganizationEmployeeAssignment $assignment) => $assignment->organizationRole?->isOwnerRole())) {
            throw ValidationException::withMessages([
                'employee' => __('You can\'t suspend the main admin account!'),
            ]);
        }

        if ($employee->status !== 'active') {
            throw ValidationException::withMessages([
                'employee' => __('Only active employees can be suspended.'),
            ]);
        }

        return DB::transaction(function () use ($employee) {
            $employee->forceFill([
                'status' => 'suspended',
                'invite_code' => null,
                'invite_expires_at' => null,
                'suspended_at' => now(),
                'metadata' => array_merge($employee->metadata ?? [], [
                    'source' => 'company_team_suspend',
                    'suspended_by' => auth()->id(),
                ]),
            ])->save();

            OrganizationEmployeeAssignment::query()
                ->where('organization_employee_id', $employee->id)
                ->whereNull('deleted_at')
                ->update([
                    'status' => 'suspended',
                    'suspended_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->removeOperationalTeams($employee);

            return $employee->fresh(['user', 'assignments.organization', 'assignments.organizationRole']);
        });
    }

    public function deleteEmployee(string $employeeUuid): void
    {
        $company = $this->currentCompany();
        $this->prepareCompanyContext($company);

        $employee = $this->findCompanyEmployee($company, $employeeUuid, [
            'assignments.organizationRole',
        ]);

        if (! $employee) {
            throw ValidationException::withMessages([
                'employee' => __('Employee not found!'),
            ]);
        }

        if ((int) $employee->user_id === (int) auth()->id()) {
            throw ValidationException::withMessages([
                'employee' => __('You cannot delete your own company access from here.'),
            ]);
        }

        if ($employee->assignments->contains(fn (OrganizationEmployeeAssignment $assignment) => $assignment->organizationRole?->isOwnerRole())) {
            throw ValidationException::withMessages([
                'employee' => __('You can\'t delete the main admin account!'),
            ]);
        }

        DB::transaction(function () use ($employee) {
            $assignmentOrganizationIds = $employee->assignments
                ->pluck('organization_id')
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values()
                ->all();

            if ($employee->user_id && $assignmentOrganizationIds !== []) {
                Team::query()
                    ->where('user_id', $employee->user_id)
                    ->whereIn('organization_id', $assignmentOrganizationIds)
                    ->whereNull('deleted_at')
                    ->delete();
            }

            OrganizationEmployeeAssignment::query()
                ->where('organization_employee_id', $employee->id)
                ->whereNull('deleted_at')
                ->delete();

            $employee->forceFill([
                'invite_code' => null,
                'invite_expires_at' => null,
                'suspended_at' => now(),
                'metadata' => array_merge($employee->metadata ?? [], [
                    'source' => 'company_team_delete',
                    'deleted_by' => auth()->id(),
                ]),
            ])->save();

            $employee->delete();
        });
    }

    public function restoreEmployee(string $employeeUuid): OrganizationEmployee
    {
        $company = $this->currentCompany();
        $this->prepareCompanyContext($company);

        $employee = $this->findCompanyEmployee($company, $employeeUuid, [
            'user',
            'assignments.organization',
            'assignments.organizationRole',
        ]);

        if (! $employee) {
            throw ValidationException::withMessages([
                'employee' => __('Employee not found!'),
            ]);
        }

        if ($employee->status !== 'suspended') {
            throw ValidationException::withMessages([
                'employee' => __('Only suspended employees can be restored.'),
            ]);
        }

        $targetUser = $employee->user ?? $this->findUserByEmail($employee->email, $employee->user_id);
        $shouldActivateImmediately = $targetUser !== null && $employee->accepted_at !== null;
        $status = $shouldActivateImmediately ? 'active' : 'pending';

        $this->assertTeamCapacity($company, $employee, $targetUser, $employee->email);

        return DB::transaction(function () use ($employee, $targetUser, $status, $company) {
            $employee->forceFill([
                'user_id' => $targetUser?->id,
                'first_name' => $targetUser?->first_name ?? $employee->first_name,
                'last_name' => $targetUser?->last_name ?? $employee->last_name,
                'status' => $status,
                'invited_by' => $status === 'pending' ? auth()->id() : $employee->invited_by,
                'invited_at' => $status === 'pending' ? now() : $employee->invited_at,
                'invite_code' => $status === 'pending' ? $this->generateInviteCode($company->id) : null,
                'invite_expires_at' => $status === 'pending' ? now()->addDay() : null,
                'accepted_at' => $status === 'active' ? ($employee->accepted_at ?? now()) : $employee->accepted_at,
                'suspended_at' => null,
                'metadata' => array_merge($employee->metadata ?? [], [
                    'source' => 'company_team_restore_access',
                    'restored_by' => auth()->id(),
                ]),
            ])->save();

            OrganizationEmployeeAssignment::query()
                ->where('organization_employee_id', $employee->id)
                ->whereNull('deleted_at')
                ->update([
                    'status' => $status,
                    'activated_at' => $status === 'active' ? now() : null,
                    'suspended_at' => null,
                    'updated_at' => now(),
                ]);

            if ($status === 'active') {
                $this->syncOperationalTeams($employee->fresh(['assignments.organizationRole']));
            } else {
                $this->removeOperationalTeams($employee);
                $this->sendCompanyInvite($employee);
            }

            return $employee->fresh(['user', 'assignments.organization', 'assignments.organizationRole']);
        });
    }

    public function updateEmployee(UpdateCompanyEmployee $request, string $employeeUuid): OrganizationEmployee
    {
        $company = $this->currentCompany();
        $this->prepareCompanyContext($company);

        $employee = $this->findCompanyEmployee($company, $employeeUuid, [
            'user',
            'assignments.organizationRole',
            'assignments.organization',
        ]);

        if (! $employee) {
            throw ValidationException::withMessages([
                'employee' => __('Employee not found!'),
            ]);
        }

        $email = strtolower(trim((string) $request->string('email')));
        $currentEmployeeEmail = strtolower(trim((string) $employee->email));
        $isUnchangedEmail = $email === $currentEmployeeEmail;
        $matchingUser = $this->findUserByEmail($email, $employee->user_id);
        $normalizedAssignments = $this->normalizeAssignments(
            $company,
            (array) $request->input('assignments', [])
        );

        $existingEmployee = null;
        if (! $isUnchangedEmail) {
            $existingEmployee = OrganizationEmployee::query()
                ->where('main_organization_id', $company->id)
                ->where('email', $email)
                ->whereNull('deleted_at')
                ->where('id', '!=', $employee->id)
                ->first();
        }

        if ($existingEmployee) {
            throw ValidationException::withMessages([
                'email' => __('Another employee with this email already exists in the company team.'),
            ]);
        }

        if ($employee->user_id && $matchingUser && (int) $matchingUser->id !== (int) $employee->user_id) {
            throw ValidationException::withMessages([
                'email' => __('A different user account already uses this email address.'),
            ]);
        }

        $this->assertTeamCapacity($company, $employee, $employee->user ?? $matchingUser, $email);

        return DB::transaction(function () use ($request, $employee, $email, $normalizedAssignments, $matchingUser, $company) {
            $hasProtectedOwnerAssignments = $employee->assignments->contains(
                fn (OrganizationEmployeeAssignment $assignment) => $assignment->organizationRole?->isOwnerRole()
            );
            $targetUser = $employee->user ?? $matchingUser;
            $canManageLinkedUser = $targetUser && in_array($employee->status, ['active', 'suspended'], true);

            if ($canManageLinkedUser) {
                $targetUser->forceFill([
                    'first_name' => $request->input('first_name') ?: $targetUser->first_name,
                    'last_name' => $request->input('last_name') ?: $targetUser->last_name,
                    'email' => $email,
                ]);

                if ($request->filled('password')) {
                    $targetUser->password = Hash::make((string) $request->input('password'));
                }

                $targetUser->save();
            }

            $resolvedFirstName = trim((string) ($request->input('first_name') ?: ($canManageLinkedUser ? $targetUser?->first_name : null) ?: $employee->first_name));
            $resolvedLastName = trim((string) ($request->input('last_name') ?: ($canManageLinkedUser ? $targetUser?->last_name : null) ?: $employee->last_name));
            $status = $employee->status === 'suspended'
                ? 'suspended'
                : (($targetUser && $employee->accepted_at !== null) ? 'active' : 'pending');

            $employee->forceFill([
                'user_id' => $targetUser?->id,
                'email' => $email,
                'first_name' => $resolvedFirstName !== '' ? $resolvedFirstName : null,
                'last_name' => $resolvedLastName !== '' ? $resolvedLastName : null,
                'status' => $status,
                'accepted_at' => $status === 'active' ? ($employee->accepted_at ?? now()) : $employee->accepted_at,
                'invite_code' => $status === 'pending'
                    ? ($employee->invite_code ?: $this->generateInviteCode($company->id))
                    : null,
                'invite_expires_at' => $status === 'pending'
                    ? ($employee->invite_expires_at ?: now()->addDay())
                    : null,
                'suspended_at' => $status === 'suspended' ? ($employee->suspended_at ?: now()) : null,
                'metadata' => array_merge($employee->metadata ?? [], [
                    'source' => 'company_team_update',
                ]),
            ])->save();

            if ($hasProtectedOwnerAssignments) {
                if ($status === 'active' && $employee->user_id) {
                    OrganizationEmployeeAssignment::query()
                        ->where('organization_employee_id', $employee->id)
                        ->whereNull('deleted_at')
                        ->update([
                            'status' => 'active',
                            'activated_at' => now(),
                            'suspended_at' => null,
                            'updated_at' => now(),
                        ]);

                    $this->syncOperationalTeams($employee->fresh(['assignments.organizationRole']));
                } else {
                    $this->removeOperationalTeams($employee);
                }

                return $employee->fresh(['user', 'assignments.organization', 'assignments.organizationRole']);
            }

            $removedOrganizationIds = $this->syncEmployeeAssignments(
                $employee,
                $normalizedAssignments,
                $status,
                'company_team_update'
            );

            if ($employee->user_id && $removedOrganizationIds !== []) {
                $this->removeOperationalTeams($employee, $removedOrganizationIds);
            }

            if ($status === 'active' && $employee->user_id) {
                $this->syncOperationalTeams($employee->fresh(['assignments.organizationRole']));
            } elseif ($employee->user_id) {
                $this->removeOperationalTeams($employee, $removedOrganizationIds);
            }

            return $employee->fresh(['user', 'assignments.organization', 'assignments.organizationRole']);
        });
    }

    public function findInviteByCode(string $code): ?OrganizationEmployee
    {
        return OrganizationEmployee::query()
            ->with(['mainOrganization', 'user'])
            ->where('invite_code', $code)
            ->whereNull('deleted_at')
            ->first();
    }

    public function findActiveInviteByCode(string $code): ?OrganizationEmployee
    {
        $invite = $this->findInviteByCode($code);

        if (! $invite || ! $invite->invite_expires_at) {
            return null;
        }

        return $invite->invite_expires_at >= now() ? $invite : null;
    }

    public function acceptInvite(StoreUserInvite $request, string $code): void
    {
        $employee = $this->findActiveInviteByCode($code);

        if (! $employee) {
            throw ValidationException::withMessages([
                'email' => __('This invite code has expired!'),
            ]);
        }

        $user = User::query()
            ->where('email', $employee->email)
            ->where('role', 'user')
            ->first();

        $this->assertTeamCapacityForCompanyId(
            (int) $employee->main_organization_id,
            (int) $employee->id,
            $user?->id,
            $employee->email
        );

        DB::transaction(function () use ($request, $employee) {
            $user = User::query()
                ->where('email', $employee->email)
                ->where('role', 'user')
                ->first();

            if (! $user) {
                $user = User::create([
                    'first_name' => $request->input('first_name'),
                    'last_name' => $request->input('last_name'),
                    'email' => $employee->email,
                    'password' => Hash::make((string) $request->input('password')),
                    'language' => app()->getLocale(),
                    'role' => 'user',
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Email::send('Registration', $user, [
                    'link' => Email::defaultAppLink(),
                ]);
            }

            $employee->forceFill([
                'user_id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'status' => 'active',
                'accepted_at' => now(),
                'invite_code' => null,
                'invite_expires_at' => null,
                'suspended_at' => null,
            ])->save();

            OrganizationEmployeeAssignment::query()
                ->where('organization_employee_id', $employee->id)
                ->whereNull('deleted_at')
                ->whereIn('status', ['pending', 'active'])
                ->update([
                    'status' => 'active',
                    'activated_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->syncOperationalTeams($employee->fresh(['assignments.organizationRole']));

            Auth::guard('user')->loginUsingId($user->id);

            $firstAssignmentOrganizationId = OrganizationEmployeeAssignment::query()
                ->where('organization_employee_id', $employee->id)
                ->whereNull('deleted_at')
                ->orderBy('organization_id')
                ->value('organization_id');

            session()->put('current_organization', $firstAssignmentOrganizationId ?: $employee->main_organization_id);
        });
    }

    private function paginateEmployees(Organization $company, string $search = ''): LengthAwarePaginator
    {
        $query = OrganizationEmployee::query()
            ->with([
                'user',
                'assignments.organization',
                'assignments.organizationRole',
            ])
            ->where('main_organization_id', $company->id)
            ->whereNull('deleted_at');

        if ($search !== '') {
            $query->where(function ($employeeQuery) use ($search) {
                $like = '%'.$search.'%';

                $employeeQuery->where('email', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhereHas('user', function ($userQuery) use ($like) {
                        $userQuery->where('email', 'like', $like)
                            ->orWhere('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like);
                    })
                    ->orWhereHas('assignments.organization', function ($organizationQuery) use ($like) {
                        $organizationQuery->where('name', 'like', $like);
                    });
            });
        }

        return $query
            ->latest('updated_at')
            ->paginate(10)
            ->through(function (OrganizationEmployee $employee) {
                $assignments = $employee->assignments
                    ->sortBy(fn (OrganizationEmployeeAssignment $assignment) => [
                        $assignment->organization?->organization_type === 'main' ? 0 : 1,
                        strtolower((string) $assignment->organization?->name),
                    ])
                    ->values()
                    ->map(function (OrganizationEmployeeAssignment $assignment) {
                        return [
                            'organization_uuid' => $assignment->organization?->uuid,
                            'organization_name' => $assignment->organization?->name,
                            'organization_type' => $assignment->organization?->organization_type,
                            'organization_role_id' => $assignment->organization_role_id,
                            'role_name' => $assignment->organizationRole?->name,
                            'status' => $assignment->status,
                        ];
                    })
                    ->all();

                return [
                    'uuid' => $employee->uuid,
                    'email' => $employee->email,
                    'first_name' => $employee->user?->first_name ?? $employee->first_name,
                    'last_name' => $employee->user?->last_name ?? $employee->last_name,
                    'full_name' => $employee->fullName(),
                    'status' => $employee->status,
                    'updated_at' => optional($employee->updated_at)?->format('Y-m-d H:i'),
                    'assignments_count' => count($assignments),
                    'assignments' => $assignments,
                    'has_user_account' => (bool) $employee->user_id,
                    'can_delete' => ! $employee->assignments->contains(
                        fn (OrganizationEmployeeAssignment $assignment) => $assignment->organizationRole?->isOwnerRole()
                    ) && (int) $employee->user_id !== (int) auth()->id(),
                    'can_resend_invite' => $employee->status === 'pending' && ! empty($employee->invite_code),
                    'can_suspend' => $employee->status === 'active'
                        && ! $employee->assignments->contains(
                            fn (OrganizationEmployeeAssignment $assignment) => $assignment->organizationRole?->isOwnerRole()
                        )
                        && (int) $employee->user_id !== (int) auth()->id(),
                    'can_restore' => $employee->status === 'suspended'
                        && ! $employee->assignments->contains(
                            fn (OrganizationEmployeeAssignment $assignment) => $assignment->organizationRole?->isOwnerRole()
                        )
                        && (int) $employee->user_id !== (int) auth()->id(),
                    'can_edit' => true,
                    'can_manage_account' => (bool) $employee->user_id && in_array($employee->status, ['active', 'suspended'], true),
                    'can_manage_assignments' => ! $employee->assignments->contains(
                        fn (OrganizationEmployeeAssignment $assignment) => $assignment->organizationRole?->isOwnerRole()
                    ),
                ];
            });
    }

    private function workspaceOptions(Organization $company): array
    {
        return $this->familyOrganizations($company)
            ->map(function (Organization $organization) {
                return [
                    'value' => $organization->uuid,
                    'label' => $organization->name,
                    'disableTranslation' => true,
                    'meta' => [
                        'type' => $organization->organization_type,
                    ],
                ];
            })
            ->values()
            ->all();
    }

    private function workspaceRoleCatalog(Organization $company): array
    {
        $familyOrganizations = $this->familyOrganizations($company)->keyBy('id');
        $roles = OrganizationRole::query()
            ->whereIn('organization_id', $familyOrganizations->keys()->all())
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'organization_id', 'name']);

        $catalog = [];

        foreach ($familyOrganizations as $organizationId => $organization) {
            $catalog[(string) $organization->uuid] = $roles
                ->where('organization_id', $organizationId)
                ->values()
                ->map(fn (OrganizationRole $role) => [
                    'value' => $role->id,
                    'label' => $role->name,
                ])
                ->all();
        }

        return $catalog;
    }

    private function summary(Organization $company): array
    {
        $employees = OrganizationEmployee::query()
            ->where('main_organization_id', $company->id)
            ->whereNull('deleted_at')
            ->get(['status']);

        return [
            'employees' => $employees->count(),
            'active' => $employees->where('status', 'active')->count(),
            'pending' => $employees->where('status', 'pending')->count(),
            'suspended' => $employees->where('status', 'suspended')->count(),
            'workspaces' => $this->familyOrganizations($company)->count(),
        ];
    }

    private function normalizeAssignments(Organization $company, array $assignments): array
    {
        $familyOrganizations = $this->familyOrganizations($company)->keyBy(
            fn (Organization $organization) => (string) $organization->uuid
        );

        $normalized = [];

        foreach ($assignments as $assignment) {
            $workspaceUuid = trim((string) ($assignment['organization_uuid'] ?? ''));
            $roleId = (int) ($assignment['organization_role_id'] ?? 0);

            if ($workspaceUuid === '' || $roleId <= 0) {
                continue;
            }

            $organization = $familyOrganizations->get($workspaceUuid);

            if (! $organization) {
                throw ValidationException::withMessages([
                    'assignments' => __('The selected workspace is not available for this company.'),
                ]);
            }

            $role = OrganizationRole::query()
                ->where('id', $roleId)
                ->where('organization_id', $organization->id)
                ->whereNull('deleted_at')
                ->first();

            if (! $role) {
                throw ValidationException::withMessages([
                    'assignments' => __('The selected role is not available for this workspace.'),
                ]);
            }

            if (array_key_exists((string) $organization->uuid, $normalized)) {
                throw ValidationException::withMessages([
                    'assignments' => __('Each workspace can only be assigned once.'),
                ]);
            }

            $normalized[(string) $organization->uuid] = [
                'organization' => $organization,
                'role' => $role,
            ];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'assignments' => __('At least one assignment is required.'),
            ]);
        }

        return array_values($normalized);
    }

    private function familyOrganizations(Organization $company)
    {
        $familyIds = $this->organizationHierarchyService->familyOrganizationIds($company->id);

        return Organization::query()
            ->whereIn('id', $familyIds !== [] ? $familyIds : [$company->id])
            ->whereNull('deleted_at')
            ->get()
            ->sortBy(fn (Organization $organization) => [
                $organization->organization_type === 'main' ? 0 : 1,
                strtolower($organization->name),
            ])
            ->values();
    }

    private function assertTeamCapacity(
        Organization $company,
        ?OrganizationEmployee $employee = null,
        ?User $user = null,
        ?string $email = null
    ): void {
        $this->assertTeamCapacityForCompanyId(
            (int) $company->id,
            $employee?->id,
            $user?->id,
            $email ?? $employee?->email
        );
    }

    private function assertTeamCapacityForCompanyId(
        int $companyId,
        ?int $excludeEmployeeId,
        ?int $userId,
        ?string $email
    ): void {
        $snapshot = $this->subscriptionFeatureUsageService->snapshot($companyId, 'team_limit');
        $limit = (int) ($snapshot['limit'] ?? 0);

        if ($limit < 0) {
            return;
        }

        $usageSnapshot = $this->organizationUserSeatUsageService->snapshot($companyId, [
            'employee_ids' => $excludeEmployeeId ? [$excludeEmployeeId] : [],
        ]);
        $targetIdentityKey = $this->organizationUserSeatUsageService->identityKey($userId, $email);

        if ($targetIdentityKey !== null && in_array($targetIdentityKey, $usageSnapshot['identity_keys'] ?? [], true)) {
            return;
        }

        if ((int) ($usageSnapshot['used'] ?? 0) >= $limit) {
            throw ValidationException::withMessages([
                'email' => __('You have reached your limit of team members. Please upgrade your account to add more!'),
            ]);
        }
    }

    private function syncOperationalTeams(OrganizationEmployee $employee): void
    {
        if (! $employee->user_id) {
            return;
        }

        $activeAssignments = OrganizationEmployeeAssignment::query()
            ->where('organization_employee_id', $employee->id)
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->get();

        foreach ($activeAssignments as $assignment) {
            Team::query()->updateOrCreate(
                [
                    'organization_id' => $assignment->organization_id,
                    'user_id' => $employee->user_id,
                ],
                [
                    'organization_role_id' => $assignment->organization_role_id,
                    'status' => 'active',
                    'created_by' => $assignment->assigned_by ?? $employee->invited_by ?? $employee->user_id,
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function removeOperationalTeams(OrganizationEmployee $employee, array $organizationIds = []): void
    {
        if (! $employee->user_id) {
            return;
        }

        $scopedOrganizationIds = collect($organizationIds)
            ->merge(
                OrganizationEmployeeAssignment::query()
                    ->withTrashed()
                    ->where('organization_employee_id', $employee->id)
                    ->pluck('organization_id')
            )
            ->map(fn ($organizationId) => (int) $organizationId)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($scopedOrganizationIds === []) {
            return;
        }

        Team::query()
            ->where('user_id', $employee->user_id)
            ->whereIn('organization_id', $scopedOrganizationIds)
            ->whereNull('deleted_at')
            ->delete();
    }

    private function syncEmployeeAssignments(
        OrganizationEmployee $employee,
        array $normalizedAssignments,
        string $status,
        string $source
    ): array {
        $existingAssignments = OrganizationEmployeeAssignment::query()
            ->withTrashed()
            ->where('organization_employee_id', $employee->id)
            ->get()
            ->keyBy(fn (OrganizationEmployeeAssignment $assignment) => (int) $assignment->organization_id);

        $keptOrganizationIds = [];

        foreach ($normalizedAssignments as $assignmentData) {
            $organizationId = (int) $assignmentData['organization']->id;
            $keptOrganizationIds[] = $organizationId;

            $assignment = $existingAssignments->get($organizationId)
                ?? new OrganizationEmployeeAssignment([
                    'organization_employee_id' => $employee->id,
                    'organization_id' => $organizationId,
                ]);

            $assignment->forceFill([
                'organization_role_id' => $assignmentData['role']->id,
                'status' => $status,
                'assigned_by' => auth()->id(),
                'assigned_at' => now(),
                'activated_at' => $status === 'active'
                    ? ($assignment->activated_at ?? now())
                    : ($status === 'suspended' ? $assignment->activated_at : null),
                'suspended_at' => $status === 'suspended' ? now() : null,
                'deleted_at' => null,
                'metadata' => array_merge($assignment->metadata ?? [], [
                    'source' => $source,
                ]),
            ])->save();
        }

        $assignmentsToDelete = $existingAssignments
            ->filter(function (OrganizationEmployeeAssignment $assignment) use ($keptOrganizationIds) {
                return ! in_array((int) $assignment->organization_id, $keptOrganizationIds, true)
                    && $assignment->deleted_at === null;
            });

        if ($assignmentsToDelete->isNotEmpty()) {
            OrganizationEmployeeAssignment::query()
                ->whereIn('id', $assignmentsToDelete->pluck('id')->all())
                ->whereNull('deleted_at')
                ->delete();
        }

        return $assignmentsToDelete->pluck('organization_id')
            ->map(fn ($organizationId) => (int) $organizationId)
            ->values()
            ->all();
    }

    private function sendCompanyInvite(OrganizationEmployee $employee): void
    {
        $inviter = Auth::user();

        if (! $inviter || ! $employee->invite_code) {
            return;
        }

        Email::sendInvite('Invite', $employee->email, $inviter, url('/invite/' . $employee->invite_code), [
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'email' => $employee->email,
            'full_name' => $employee->fullName(),
        ]);
    }

    private function generateInviteCode(int $companyId): string
    {
        return md5(now()->timestamp.$companyId.Str::random(32));
    }

    private function prepareCompanyContext(Organization $company): void
    {
        $this->syncPreviousFamilyMemberships($company);
        $this->reconcileDuplicateEmployees($company);
    }

    private function syncPreviousFamilyMemberships(Organization $company): void
    {
        $familyOrganizations = $this->familyOrganizations($company)->keyBy('id');

        if ($familyOrganizations->isEmpty()) {
            return;
        }

        $teams = Team::query()
            ->with(['user', 'organizationRole'])
            ->whereIn('organization_id', $familyOrganizations->keys()->all())
            ->whereNull('deleted_at')
            ->get();

        foreach ($teams as $team) {
            $user = $team->user;
            if (! $user) {
                continue;
            }

            $employee = OrganizationEmployee::query()
                ->withTrashed()
                ->where('main_organization_id', $company->id)
                ->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->orWhere('email', strtolower((string) $user->email));
                })
                ->first();

            if (! $employee) {
                $employee = new OrganizationEmployee([
                    'main_organization_id' => $company->id,
                    'email' => strtolower((string) $user->email),
                ]);
            }

            $employee->fill([
                'user_id' => $user->id,
                'email' => strtolower((string) $user->email),
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'status' => $team->status === 'active' ? 'active' : 'pending',
                'accepted_at' => $employee->accepted_at ?? $team->created_at ?? now(),
                'deleted_at' => null,
                'metadata' => array_merge($employee->metadata ?? [], [
                    'source' => 'previous_team_sync',
                ]),
            ]);

            $employee->save();

            OrganizationEmployeeAssignment::query()
                ->withTrashed()
                ->updateOrCreate(
                    [
                        'organization_employee_id' => $employee->id,
                        'organization_id' => $team->organization_id,
                    ],
                    [
                        'organization_role_id' => $team->organization_role_id,
                        'status' => $team->status === 'active' ? 'active' : 'pending',
                        'assigned_by' => $team->created_by,
                        'assigned_at' => $team->created_at ?? now(),
                        'activated_at' => $team->status === 'active'
                            ? ($team->updated_at ?? $team->created_at ?? now())
                            : null,
                        'suspended_at' => null,
                        'deleted_at' => null,
                        'metadata' => [
                            'source' => 'previous_team_sync',
                            'team_uuid' => $team->uuid,
                        ],
                    ]
                );
        }
    }

    private function reconcileDuplicateEmployees(Organization $company): void
    {
        $employees = OrganizationEmployee::query()
            ->with(['assignments.organizationRole'])
            ->where('main_organization_id', $company->id)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();

        $groups = $employees->groupBy(function (OrganizationEmployee $employee) {
            if ($employee->user_id) {
                return 'user:'.$employee->user_id;
            }

            return 'email:'.strtolower(trim((string) $employee->email));
        });

        foreach ($groups as $group) {
            if ($group->count() <= 1) {
                continue;
            }

            $keeper = $group->sortBy([
                fn (OrganizationEmployee $employee) => $employee->user_id ? 0 : 1,
                fn (OrganizationEmployee $employee) => $employee->status === 'active' ? 0 : 1,
                fn (OrganizationEmployee $employee) => $employee->id,
            ])->first();

            foreach ($group as $employee) {
                if ((int) $employee->id === (int) $keeper->id) {
                    continue;
                }

                $keeper = $this->mergeDuplicateEmployeeIntoKeeper($keeper, $employee);
            }
        }
    }

    private function mergeDuplicateEmployeeIntoKeeper(
        OrganizationEmployee $keeper,
        OrganizationEmployee $duplicate
    ): OrganizationEmployee {
        $duplicate->loadMissing(['assignments.organizationRole']);

        return DB::transaction(function () use ($keeper, $duplicate) {
            $resolvedUserId = $keeper->user_id ?: $duplicate->user_id;
            $resolvedStatus = in_array('active', [$keeper->status, $duplicate->status], true) ? 'active' : ($keeper->status ?: $duplicate->status ?: 'pending');

            $keeper->forceFill([
                'user_id' => $resolvedUserId,
                'email' => strtolower(trim((string) ($keeper->email ?: $duplicate->email))),
                'first_name' => $keeper->first_name ?: $duplicate->first_name,
                'last_name' => $keeper->last_name ?: $duplicate->last_name,
                'status' => $resolvedStatus,
                'invited_by' => $keeper->invited_by ?: $duplicate->invited_by,
                'invited_at' => $keeper->invited_at ?: $duplicate->invited_at,
                'accepted_at' => $keeper->accepted_at ?: $duplicate->accepted_at,
                'invite_code' => $resolvedUserId ? null : ($keeper->invite_code ?: $duplicate->invite_code),
                'invite_expires_at' => $resolvedUserId ? null : ($keeper->invite_expires_at ?: $duplicate->invite_expires_at),
                'suspended_at' => null,
                'deleted_at' => null,
                'metadata' => array_merge($keeper->metadata ?? [], [
                    'source' => 'company_team_reconcile',
                ]),
            ])->save();

            foreach ($duplicate->assignments->whereNull('deleted_at') as $assignment) {
                $existingKeeperAssignment = OrganizationEmployeeAssignment::query()
                    ->with(['organizationRole'])
                    ->withTrashed()
                    ->where('organization_employee_id', $keeper->id)
                    ->where('organization_id', $assignment->organization_id)
                    ->first();

                $resolvedRoleId = $assignment->organization_role_id;
                if ($existingKeeperAssignment?->organizationRole?->isOwnerRole()) {
                    $resolvedRoleId = $existingKeeperAssignment->organization_role_id;
                } elseif ($assignment->organizationRole?->isOwnerRole()) {
                    $resolvedRoleId = $assignment->organization_role_id;
                } elseif ($existingKeeperAssignment && $existingKeeperAssignment->organization_role_id) {
                    $resolvedRoleId = $existingKeeperAssignment->organization_role_id;
                }

                OrganizationEmployeeAssignment::query()->updateOrCreate(
                    [
                        'organization_employee_id' => $keeper->id,
                        'organization_id' => $assignment->organization_id,
                    ],
                    [
                        'organization_role_id' => $resolvedRoleId,
                        'status' => in_array('active', [$existingKeeperAssignment?->status, $assignment->status], true) ? 'active' : ($existingKeeperAssignment?->status ?: $assignment->status ?: 'pending'),
                        'assigned_by' => $existingKeeperAssignment?->assigned_by ?: $assignment->assigned_by,
                        'assigned_at' => $existingKeeperAssignment?->assigned_at ?: $assignment->assigned_at ?: now(),
                        'activated_at' => $existingKeeperAssignment?->activated_at ?: $assignment->activated_at,
                        'suspended_at' => null,
                        'deleted_at' => null,
                        'metadata' => array_merge($existingKeeperAssignment?->metadata ?? [], $assignment->metadata ?? [], [
                            'source' => 'company_team_reconcile',
                        ]),
                    ]
                );
            }

            OrganizationEmployeeAssignment::query()
                ->where('organization_employee_id', $duplicate->id)
                ->whereNull('deleted_at')
                ->delete();

            $duplicate->forceFill([
                'invite_code' => null,
                'invite_expires_at' => null,
                'suspended_at' => $duplicate->suspended_at ?? now(),
                'metadata' => array_merge($duplicate->metadata ?? [], [
                    'source' => 'company_team_reconcile',
                    'merged_into_employee_id' => $keeper->id,
                ]),
            ])->save();

            $duplicate->delete();

            $keeper = $keeper->fresh(['assignments.organizationRole']);

            if ($keeper && $keeper->user_id) {
                $this->syncOperationalTeams($keeper);
            }

            return $keeper->fresh(['assignments.organizationRole']);
        });
    }

    private function findCompanyEmployee(Organization $company, string $employeeUuid, array $with = []): ?OrganizationEmployee
    {
        return OrganizationEmployee::query()
            ->with($with)
            ->where('main_organization_id', $company->id)
            ->where('uuid', $employeeUuid)
            ->whereNull('deleted_at')
            ->first();
    }

    private function findUserByEmail(string $email, ?int $ignoreUserId = null): ?User
    {
        return User::query()
            ->where('email', $email)
            ->where('role', 'user')
            ->when($ignoreUserId, fn ($query) => $query->where('id', '!=', $ignoreUserId))
            ->first();
    }

    private function assertCompanyAllowsEmployeeProvisioning(Organization $company): void
    {
        $subscription = app(SubscriptionPlanLimitService::class)->subscriptionForOrganization($company->id);

        if (app(SubscriptionPlanLimitService::class)->isOperationallyActive($subscription)) {
            return;
        }

        throw ValidationException::withMessages([
            'company_uuid' => __('Activate billing for this company before assigning employees and workspace access.'),
        ]);
    }
}
