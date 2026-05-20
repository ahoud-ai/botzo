<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Team;

class OrganizationSessionService
{
    public function organizationIdsForUser(?int $userId): array
    {
        if (!$userId) {
            return [];
        }

        return Team::query()
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->pluck('organization_id')
            ->map(fn ($organizationId) => (int) $organizationId)
            ->unique()
            ->values()
            ->all();
    }

    public function accessibleOrganizationIdsForUser(?int $userId): array
    {
        if (!$userId) {
            return [];
        }

        return array_values(array_unique(array_merge(
            $this->organizationIdsForUser($userId),
            $this->branchOrganizationIdsAccessibleThroughOwnedParents($userId)
        )));
    }

    public function firstOrganizationIdForUser(?int $userId): ?int
    {
        return $this->organizationIdsForUser($userId)[0] ?? null;
    }

    public function accessibleOrganizationIdForUser(?int $userId, ?int $organizationId): ?int
    {
        if (!$userId || !$organizationId) {
            return null;
        }

        $organizationId = (int) $organizationId;

        return in_array($organizationId, $this->accessibleOrganizationIdsForUser($userId), true)
            ? $organizationId
            : null;
    }

    public function selectableOrganizationIdForUserByUuid(?int $userId, ?string $organizationUuid): ?int
    {
        if (!$userId || !$organizationUuid) {
            return null;
        }

        $accessibleOrganizationIds = $this->accessibleOrganizationIdsForUser($userId);
        if ($accessibleOrganizationIds === []) {
            return null;
        }

        return Organization::query()
            ->whereIn('id', $accessibleOrganizationIds)
            ->where('uuid', $organizationUuid)
            ->value('id');
    }

    private function branchOrganizationIdsAccessibleThroughOwnedParents(?int $userId): array
    {
        if (!$userId) {
            return [];
        }

        $ownedParentIds = Team::query()
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->whereHas('organizationRole', function ($query) {
                $query->whereNull('organization_id')
                    ->where('name', 'Owner');
            })
            ->whereHas('organization', function ($query) {
                $query->where('organization_type', 'main')
                    ->whereNull('deleted_at');
            })
            ->pluck('organization_id')
            ->map(fn ($organizationId) => (int) $organizationId)
            ->unique()
            ->values()
            ->all();

        if ($ownedParentIds === []) {
            return [];
        }

        return Organization::query()
            ->whereIn('parent_organization_id', $ownedParentIds)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn ($organizationId) => (int) $organizationId)
            ->values()
            ->all();
    }
}
