<?php

namespace App\Services;

use App\Models\OrganizationEmployee;
use App\Models\Team;
use App\Models\TeamInvite;
use App\Models\User;

class OrganizationUserSeatUsageService
{
    public function __construct(
        private readonly OrganizationHierarchyService $organizationHierarchyService,
    ) {
    }

    /**
     * @param  array{
     *     employee_ids?:array<int>,
     *     team_invite_ids?:array<int>
     * }  $exclude
     * @return array{
     *     organization_id:int,
     *     billing_organization_id:int,
     *     usage_organization_ids:array<int>,
     *     identity_keys:array<int, string>,
     *     used:int
     * }
     */
    public function snapshot(int $organizationId, array $exclude = []): array
    {
        $organizationId = (int) $organizationId;
        $billingOrganizationId = $this->organizationHierarchyService->billingOwnerId($organizationId) ?? $organizationId;
        $usageOrganizationIds = $this->organizationHierarchyService->familyOrganizationIds($organizationId);
        $usageOrganizationIds = $usageOrganizationIds !== [] ? $usageOrganizationIds : [$billingOrganizationId];

        $excludeEmployeeIds = collect($exclude['employee_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        $excludeTeamInviteIds = collect($exclude['team_invite_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        $identityKeys = [];

        foreach ($this->activeTeamIdentityKeys($usageOrganizationIds) as $identityKey) {
            $identityKeys[$identityKey] = true;
        }

        foreach ($this->companyEmployeeIdentityKeys($billingOrganizationId, $usageOrganizationIds, $excludeEmployeeIds) as $identityKey) {
            $identityKeys[$identityKey] = true;
        }

        foreach ($this->teamInviteIdentityKeys($usageOrganizationIds, $excludeTeamInviteIds) as $identityKey) {
            $identityKeys[$identityKey] = true;
        }

        $resolvedIdentityKeys = array_keys($identityKeys);
        sort($resolvedIdentityKeys);

        return [
            'organization_id' => $organizationId,
            'billing_organization_id' => $billingOrganizationId,
            'usage_organization_ids' => $usageOrganizationIds,
            'identity_keys' => $resolvedIdentityKeys,
            'used' => count($resolvedIdentityKeys),
        ];
    }

    public function identityKey(?int $userId, ?string $email): ?string
    {
        if ($userId) {
            return 'user:'.(int) $userId;
        }

        $normalizedEmail = $this->normalizeEmail($email);
        if ($normalizedEmail === null) {
            return null;
        }

        $resolvedUserId = User::query()
            ->where('email', $normalizedEmail)
            ->where('role', 'user')
            ->value('id');

        if ($resolvedUserId) {
            return 'user:'.(int) $resolvedUserId;
        }

        return 'email:'.$normalizedEmail;
    }

    /**
     * @param  array<int>  $usageOrganizationIds
     * @return array<int, string>
     */
    private function activeTeamIdentityKeys(array $usageOrganizationIds): array
    {
        return Team::query()
            ->whereIn('organization_id', $usageOrganizationIds)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($userId) => $userId ? 'user:'.(int) $userId : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int>  $usageOrganizationIds
     * @param  array<int>  $excludeEmployeeIds
     * @return array<int, string>
     */
    private function companyEmployeeIdentityKeys(
        int $billingOrganizationId,
        array $usageOrganizationIds,
        array $excludeEmployeeIds = []
    ): array {
        $employees = OrganizationEmployee::query()
            ->where('main_organization_id', $billingOrganizationId)
            ->whereNull('deleted_at')
            ->whereIn('status', ['pending', 'active'])
            ->when(
                $excludeEmployeeIds !== [],
                fn ($query) => $query->whereNotIn('id', $excludeEmployeeIds)
            )
            ->whereHas('assignments', function ($query) use ($usageOrganizationIds) {
                $query->whereNull('deleted_at')
                    ->whereIn('status', ['pending', 'active'])
                    ->whereIn('organization_id', $usageOrganizationIds);
            })
            ->get(['user_id', 'email']);

        return $this->resolveIdentityKeys(
            $employees->map(fn (OrganizationEmployee $employee) => [
                'user_id' => $employee->user_id ? (int) $employee->user_id : null,
                'email' => $employee->email,
            ])->all()
        );
    }

    /**
     * @param  array<int>  $usageOrganizationIds
     * @param  array<int>  $excludeTeamInviteIds
     * @return array<int, string>
     */
    private function teamInviteIdentityKeys(array $usageOrganizationIds, array $excludeTeamInviteIds = []): array
    {
        $invites = TeamInvite::query()
            ->whereIn('organization_id', $usageOrganizationIds)
            ->where('expire_at', '>=', now())
            ->when(
                $excludeTeamInviteIds !== [],
                fn ($query) => $query->whereNotIn('id', $excludeTeamInviteIds)
            )
            ->get(['email']);

        return $this->resolveIdentityKeys(
            $invites->map(fn (TeamInvite $invite) => [
                'user_id' => null,
                'email' => $invite->email,
            ])->all()
        );
    }

    /**
     * @param  array<int, array{user_id:?int, email:?string}>  $records
     * @return array<int, string>
     */
    private function resolveIdentityKeys(array $records): array
    {
        $identityKeys = [];
        $emails = [];

        foreach ($records as $record) {
            $userId = $record['user_id'] ?? null;
            if ($userId) {
                $identityKeys['user:'.(int) $userId] = true;

                continue;
            }

            $normalizedEmail = $this->normalizeEmail($record['email'] ?? null);
            if ($normalizedEmail !== null) {
                $emails[$normalizedEmail] = true;
            }
        }

        if ($emails !== []) {
            $usersByEmail = User::query()
                ->whereIn('email', array_keys($emails))
                ->where('role', 'user')
                ->get(['id', 'email'])
                ->mapWithKeys(fn (User $user) => [strtolower((string) $user->email) => (int) $user->id])
                ->all();

            foreach (array_keys($emails) as $email) {
                if (isset($usersByEmail[$email])) {
                    $identityKeys['user:'.$usersByEmail[$email]] = true;

                    continue;
                }

                $identityKeys['email:'.$email] = true;
            }
        }

        return array_keys($identityKeys);
    }

    private function normalizeEmail(?string $email): ?string
    {
        $normalizedEmail = strtolower(trim((string) $email));

        return $normalizedEmail !== '' ? $normalizedEmail : null;
    }
}
