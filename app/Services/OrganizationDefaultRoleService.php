<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Support\OrganizationPermissions;
use App\Support\OrganizationRolePresetCatalog;
use Illuminate\Support\Str;

class OrganizationDefaultRoleService
{
    public function ensureDefaultsForOrganization(Organization $organization): void
    {
        if ($organization->deleted_at !== null || $this->isSeeded($organization)) {
            return;
        }

        foreach (OrganizationRolePresetCatalog::presets() as $preset) {
            OrganizationRole::query()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'name' => $preset['name'],
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'description' => $preset['description'],
                    'permissions' => OrganizationPermissions::normalizePermissions($preset['permissions']),
                ]
            );
        }

        $this->markSeeded($organization);
    }

    private function isSeeded(Organization $organization): bool
    {
        $metadata = $this->metadata($organization);
        $version = data_get($metadata, 'system.default_role_seed_version');

        return (int) $version >= OrganizationRolePresetCatalog::SEED_VERSION;
    }

    private function markSeeded(Organization $organization): void
    {
        $metadata = $this->metadata($organization);
        data_set($metadata, 'system.default_role_seed_version', OrganizationRolePresetCatalog::SEED_VERSION);

        $organization->forceFill([
            'metadata' => json_encode($metadata),
        ])->save();
    }

    private function metadata(Organization $organization): array
    {
        $metadata = $organization->metadata;

        if (is_array($metadata)) {
            return $metadata;
        }

        $decoded = json_decode((string) $metadata, true);

        return is_array($decoded) ? $decoded : [];
    }
}
