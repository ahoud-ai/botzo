<?php

namespace App\Services;

use App\Models\OrganizationRole;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;

class PermissionService
{
    /**
     * Check if the current user has a specific permission
     *
     * @param string $permission The permission to check (e.g., 'contacts.view_all')
     * @param int|null $organizationId The organization ID (defaults to current session organization)
     * @return bool
     */
    public function can(string $permission, ?int $organizationId = null): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        $organizationId = $organizationId ?? session()->get('current_organization');

        if (!$organizationId) {
            return false;
        }

        $organizationId = (int) $organizationId;
        $userId = (int) $user->id;

        if ($this->mustResolvePermissionFromBillingOwner($permission, $organizationId)) {
            $billingOwnerId = $this->billingOwnerId($organizationId);

            return $billingOwnerId
                ? $this->roleAllowsPermission(
                    $this->teamForUserAndOrganization($userId, $billingOwnerId),
                    $permission
                )
                : false;
        }

        $directTeam = $this->teamForUserAndOrganization($userId, $organizationId);

        if ($this->roleAllowsPermission($directTeam, $permission)) {
            return true;
        }

        return $this->inheritedBranchOwnerTeam($userId, $organizationId) !== null;
    }

    /**
     * Check if the current user is an owner
     *
     * @param int|null $organizationId The organization ID (defaults to current session organization)
     * @return bool
     */
    public function isOwner(?int $organizationId = null): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        $organizationId = $organizationId ?? session()->get('current_organization');

        if (!$organizationId) {
            return false;
        }

        $organizationId = (int) $organizationId;
        $team = $this->teamForUserAndOrganization((int) $user->id, $organizationId);

        if ($this->teamIsOwner($team)) {
            return true;
        }

        return $this->inheritedBranchOwnerTeam((int) $user->id, $organizationId) !== null;
    }

    /**
     * Get the current user's role for the organization
     *
     * @param int|null $organizationId The organization ID (defaults to current session organization)
     * @return OrganizationRole|null
     */
    public function getCurrentRole(?int $organizationId = null): ?OrganizationRole
    {
        $user = Auth::user();

        if (!$user) {
            return null;
        }

        $organizationId = $organizationId ?? session()->get('current_organization');

        if (!$organizationId) {
            return null;
        }

        $organizationId = (int) $organizationId;
        $team = $this->teamForUserAndOrganization((int) $user->id, $organizationId)
            ?: $this->inheritedBranchOwnerTeam((int) $user->id, $organizationId);

        return $team->organizationRole ?? null;
    }

    /**
     * Get all permissions for the current user
     *
     * @param int|null $organizationId The organization ID (defaults to current session organization)
     * @return array
     */
    public function getPermissions(?int $organizationId = null): array
    {
        $role = $this->getCurrentRole($organizationId);

        if (!$role) {
            return [];
        }

        if ($role->isOwnerRole()) {
            return ['*']; // All permissions
        }

        return $role->getPermissionsArray();
    }

    private function teamForUserAndOrganization(int $userId, ?int $organizationId): ?Team
    {
        if ($organizationId === null || $organizationId <= 0) {
            return null;
        }

        return Team::query()
            ->where('user_id', $userId)
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->with('organizationRole')
            ->first();
    }

    private function inheritedBranchOwnerTeam(int $userId, int $organizationId): ?Team
    {
        if (!$this->isBranch($organizationId)) {
            return null;
        }

        $billingOwnerId = $this->billingOwnerId($organizationId);
        if (!$billingOwnerId || $billingOwnerId === $organizationId) {
            return null;
        }

        $team = $this->teamForUserAndOrganization($userId, $billingOwnerId);

        return $this->teamIsOwner($team) ? $team : null;
    }

    private function roleAllowsPermission(?Team $team, string $permission): bool
    {
        return (bool) ($team?->organizationRole?->hasPermission($permission));
    }

    private function teamIsOwner(?Team $team): bool
    {
        return (bool) ($team?->organizationRole?->isOwnerRole());
    }

    private function mustResolvePermissionFromBillingOwner(string $permission, int $organizationId): bool
    {
        return $permission === 'settings.billing_subscription' && $this->isBranch($organizationId);
    }

    private function billingOwnerId(int $organizationId): ?int
    {
        return app(OrganizationHierarchyService::class)->billingOwnerId($organizationId);
    }

    private function isBranch(int $organizationId): bool
    {
        return app(OrganizationHierarchyService::class)->isBranch($organizationId);
    }
}
