<?php

namespace App\Services;

use App\Models\Organization;

class OrganizationHierarchyService
{
    public function find(int $organizationId): ?Organization
    {
        return Organization::query()
            ->with('parentOrganization')
            ->find($organizationId);
    }

    public function billingOwner(?int $organizationId): ?Organization
    {
        if (!$organizationId) {
            return null;
        }

        $organization = $this->find($organizationId);
        if (!$organization) {
            return null;
        }

        if ($organization->organization_type === 'branch' && $organization->parentOrganization) {
            return $organization->parentOrganization;
        }

        return $organization;
    }

    public function billingOwnerId(?int $organizationId): ?int
    {
        return $this->billingOwner($organizationId)?->id;
    }

    public function isBranch(?int $organizationId): bool
    {
        if (!$organizationId) {
            return false;
        }

        return $this->find($organizationId)?->organization_type === 'branch';
    }

    /**
     * @return array<int>
     */
    public function familyOrganizationIds(?int $organizationId): array
    {
        $billingOwnerId = $this->billingOwnerId($organizationId);
        if (!$billingOwnerId) {
            return [];
        }

        $ids = Organization::query()
            ->whereNull('deleted_at')
            ->where(function ($query) use ($billingOwnerId) {
                $query->where('id', $billingOwnerId)
                    ->orWhere('parent_organization_id', $billingOwnerId);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return $ids !== [] ? $ids : [$billingOwnerId];
    }

    public function familyWorkspaceCount(?int $organizationId, ?int $excludeOrganizationId = null): int
    {
        $familyIds = $this->familyOrganizationIds($organizationId);

        if ($excludeOrganizationId !== null) {
            $familyIds = array_values(array_filter(
                $familyIds,
                fn (int $id): bool => $id !== $excludeOrganizationId
            ));
        }

        return count($familyIds);
    }
}
