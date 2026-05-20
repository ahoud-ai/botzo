<?php

namespace App\Support;

use App\Models\Organization;
use App\Services\OrganizationHierarchyService;

class OrganizationProfileContext
{
    public function build(Organization $organization): array
    {
        $billingOwner = app(OrganizationHierarchyService::class)->billingOwner((int) $organization->id);
        $companyProfileManagedByParent = $organization->organization_type === 'branch'
            && $billingOwner
            && (int) $billingOwner->id !== (int) $organization->id;

        $companyProfileSource = $companyProfileManagedByParent ? $billingOwner : $organization;

        return [
            'companyProfileManagedByParent' => $companyProfileManagedByParent,
            'companyProfile' => $companyProfileSource
                ? $this->serializeOrganizationIdentity($companyProfileSource)
                : null,
            'branchWorkspace' => [
                'uuid' => $organization->uuid,
                'name' => $organization->name,
                'organization_type' => $organization->organization_type,
            ],
            'billingOwner' => $billingOwner ? [
                'uuid' => $billingOwner->uuid,
                'name' => $billingOwner->name,
            ] : null,
        ];
    }

    private function serializeOrganizationIdentity(Organization $organization): array
    {
        return [
            'uuid' => $organization->uuid,
            'name' => $organization->name,
            'organization_type' => $organization->organization_type,
            'address' => $this->decodeAddress($organization->address),
        ];
    }

    private function decodeAddress(?string $address): array
    {
        if (! $address) {
            return [];
        }

        $decoded = json_decode($address, true);

        return is_array($decoded) ? $decoded : [];
    }
}
