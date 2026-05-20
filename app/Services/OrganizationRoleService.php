<?php

namespace App\Services;

use App\Models\OrganizationRole;
use App\Models\Team;
use App\Support\OrganizationPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationRoleService
{
    /**
     * Get all organization roles for the current organization
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function get(Request $request)
    {
        $organizationId = session()->get('current_organization');
        $searchTerm = $request->query('search');

        $query = OrganizationRole::where(function($q) use ($organizationId) {
            // Include organization-specific roles
            $q->where('organization_id', $organizationId)
              // Also include universal owner role
              ->orWhere(function($subQ) {
                  $subQ->whereNull('organization_id')
                       ->where('name', 'Owner');
              });
        })
        ->withCount([
            'teams as teams_count' => function ($query) use ($organizationId) {
                $query->where('teams.organization_id', $organizationId)
                    ->whereNull('teams.deleted_at');
            },
        ]);

        if ($searchTerm) {
            $query->where('name', 'like', "%{$searchTerm}%");
        }

        return $query->orderBy('name')->paginate(10);
    }

    /**
     * Get role by UUID
     *
     * @param string|null $uuid
     * @return array
     */
    public function getByUuid(?string $uuid = null): array
    {
        $organizationId = session()->get('current_organization');
        
        $role = null;
        if ($uuid) {
            $role = OrganizationRole::where('uuid', $uuid)
                ->where(function($q) use ($organizationId) {
                    $q->where('organization_id', $organizationId)
                      ->orWhere(function($subQ) {
                          $subQ->whereNull('organization_id')
                               ->where('name', 'Owner');
                      });
                })
                ->first();
        }

        return [
            'role' => $role,
            'permissions' => OrganizationPermissions::availablePermissions(),
        ];
    }

    /**
     * Store a new organization role
     *
     * @param Request $request
     * @return OrganizationRole
     */
    public function store(Request $request): OrganizationRole
    {
        $organizationId = session()->get('current_organization');
        
        // Only owners can create roles
        $permissionService = new PermissionService();
        if (!$permissionService->isOwner($organizationId)) {
            throw new \Exception(__('Only owners can create roles'));
        }

        // Validate role name uniqueness within organization
        $exists = OrganizationRole::where('organization_id', $organizationId)
            ->where('name', $request->name)
            ->exists();

        if ($exists) {
            throw new \Exception(__('A role with this name already exists in your organization'));
        }

        // Build permissions array from request
        $permissions = [];
        foreach ($request->permissions ?? [] as $module => $actions) {
            foreach ($actions as $action => $value) {
                if ($value) {
                    $permissions[] = "{$module}.{$action}";
                }
            }
        }

        $permissions = OrganizationPermissions::normalizePermissions($permissions);

        return OrganizationRole::create([
            'uuid' => Str::uuid(),
            'organization_id' => $organizationId,
            'name' => $request->name,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Update an existing organization role
     *
     * @param Request $request
     * @param string $uuid
     * @return OrganizationRole
     */
    public function update(Request $request, string $uuid): OrganizationRole
    {
        $organizationId = session()->get('current_organization');
        
        // Only owners can update roles
        $permissionService = new PermissionService();
        if (!$permissionService->isOwner($organizationId)) {
            throw new \Exception(__('Only owners can update roles'));
        }

        $role = OrganizationRole::where('uuid', $uuid)
            ->where(function($q) use ($organizationId) {
                $q->where('organization_id', $organizationId)
                  ->orWhere(function($subQ) {
                      $subQ->whereNull('organization_id')
                           ->where('name', 'Owner');
                  });
            })
            ->firstOrFail();

        // Prevent modifying universal owner role
        if ($role->isOwnerRole()) {
            throw new \Exception(__('Cannot modify the universal Owner role'));
        }

        // Check name uniqueness if name changed
        if ($request->name !== $role->name) {
            $exists = OrganizationRole::where('organization_id', $organizationId)
                ->where('name', $request->name)
                ->where('id', '!=', $role->id)
                ->exists();

            if ($exists) {
                throw new \Exception(__('A role with this name already exists in your organization'));
            }
        }

        // Build permissions array from request
        $permissions = [];
        foreach ($request->permissions ?? [] as $module => $actions) {
            foreach ($actions as $action => $value) {
                if ($value) {
                    $permissions[] = "{$module}.{$action}";
                }
            }
        }

        $permissions = OrganizationPermissions::normalizePermissions($permissions);

        $role->update([
            'name' => $request->name,
            'permissions' => $permissions,
        ]);

        return $role;
    }

    /**
     * Delete an organization role
     *
     * @param string $uuid
     * @return bool
     */
    public function destroy(string $uuid): bool
    {
        $organizationId = session()->get('current_organization');
        
        // Only owners can delete roles
        $permissionService = new PermissionService();
        if (!$permissionService->isOwner($organizationId)) {
            throw new \Exception(__('Only owners can delete roles'));
        }

        $role = OrganizationRole::where('uuid', $uuid)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        // Prevent deleting universal owner role
        if ($role->isOwnerRole()) {
            throw new \Exception(__('Cannot delete the universal Owner role'));
        }

        // Check if role has team members assigned
        $hasMembers = Team::where('organization_role_id', $role->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($hasMembers) {
            throw new \Exception(__('Cannot delete role that has team members assigned. Please reassign members first.'));
        }

        return $role->delete();
    }

    /**
     * Get all organization roles for dropdown/select
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllForSelect()
    {
        $organizationId = session()->get('current_organization');

        return OrganizationRole::query()
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'organization_id']);
    }
}
