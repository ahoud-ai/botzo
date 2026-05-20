<?php

namespace App\Services;

use App\Http\Resources\RoleResource;
use App\Models\Module;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleService
{
    private const SYSTEM_ROLE_NAME = 'admin';

    /**
     * Get all roles based on the provided request filters.
     */
    public function get(object $request)
    {
        $searchTerm = trim((string) $request->query('search', ''));

        $roles = Role::query()
            ->whereNull('deleted_at')
            ->when($searchTerm !== '', function ($query) use ($searchTerm) {
                $query->where('name', 'like', '%' . $searchTerm . '%');
            })
            ->withCount('permissions')
            ->withCount('members')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return RoleResource::collection($roles);
    }

    /**
     * Retrieve a role by its UUID.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getByUuid($uuid)
    {
        $role = $uuid
            ? Role::query()
                ->where('uuid', $uuid)
                ->whereNull('deleted_at')
                ->withCount('permissions')
                ->withCount('members')
                ->firstOrFail()
            : null;

        $modules = Module::all();
        $permissions = [];

        if ($role) {
            $permissions = RolePermission::where('role_id', $role->id)->get();
        }

        return ['role' => $role, 'modules' => $modules, 'permissions' => $permissions];
    }

    /**
     * Store a new role based on the provided request data.
     */
    public function store(object $request)
    {
        return DB::transaction(function () use ($request) {
            $newRole = Role::create([
                'name' => $request->input('name'),
            ]);

            $permissions = $request->input('permissions', []);

            foreach ($permissions as $module => $actions) {
                foreach ($actions as $action => $value) {
                    if ($value) {
                        RolePermission::create([
                            'role_id' => $newRole->id,
                            'module' => $module,
                            'action' => $action,
                        ]);
                    }
                }
            }

            return $newRole;
        });
    }

    /**
     * Update an existing role and its associated permissions.
     */
    public function update(object $request, string $uuid): Role
    {
        return DB::transaction(function () use ($request, $uuid) {
            $role = Role::query()
                ->where('uuid', $uuid)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $this->ensureRoleIsEditable($role);

            $role->update([
                'name' => $request->input('name'),
            ]);

            $permissions = $request->input('permissions', []);

            RolePermission::where('role_id', $role->id)->delete();

            foreach ($permissions as $module => $actions) {
                foreach ($actions as $action => $value) {
                    if ($value) {
                        RolePermission::create([
                            'role_id' => $role->id,
                            'module' => $module,
                            'action' => $action,
                        ]);
                    }
                }
            }

            $role->touch();

            return $role;
        });
    }

    /**
     * Check if role has users assigned to it.
     */
    public function checkUsers(string $uuid): array
    {
        $role = Role::query()
            ->where('uuid', $uuid)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $users = $this->usersForRole($role)->get();

        return [
            'has_users' => $users->count() > 0,
            'user_count' => $users->count(),
            'users' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->first_name . ' ' . $user->last_name,
                    'email' => $user->email,
                ];
            }),
            'role' => [
                'uuid' => $role->uuid,
                'name' => $role->name,
            ],
        ];
    }

    /**
     * Previous endpoint compatibility: delete with transfer is blocked by policy.
     */
    public function destroyWithTransfer(string $uuid, string $_newRoleUuid): void
    {
        $role = Role::query()
            ->where('uuid', $uuid)
            ->whereNull('deleted_at')
            ->firstOrFail();

        // Keep a strict policy: no transfer-based deletion in admin roles.
        if ($this->usersForRole($role)->exists()) {
            throw ValidationException::withMessages([
                'role' => __('Cannot delete role that has team members assigned. Please reassign members first.'),
            ]);
        }

        $this->ensureRoleIsDeletable($role);
        $role->delete();
    }

    /**
     * Directly delete a role.
     */
    public function destroyDirect(string $uuid): void
    {
        $this->destroy($uuid);
    }

    /**
     * Remove the specified role.
     */
    public function destroy(string $uuid): void
    {
        $role = Role::query()
            ->where('uuid', $uuid)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $this->ensureRoleIsDeletable($role);
        $role->delete();
    }

    private function ensureRoleIsEditable(Role $role): void
    {
        if ($this->isSystemRole($role)) {
            throw ValidationException::withMessages([
                'role' => __('System admin role cannot be modified.'),
            ]);
        }
    }

    private function ensureRoleIsDeletable(Role $role): void
    {
        if ($this->isSystemRole($role)) {
            throw ValidationException::withMessages([
                'role' => __('System admin role cannot be deleted.'),
            ]);
        }

        if ($this->usersForRole($role)->exists()) {
            throw ValidationException::withMessages([
                'role' => __('Cannot delete role that has team members assigned. Please reassign members first.'),
            ]);
        }
    }

    private function usersForRole(Role $role)
    {
        return User::query()
            ->whereRaw('LOWER(role) = ?', [strtolower((string) $role->name)])
            ->whereNull('deleted_at');
    }

    private function isSystemRole(Role $role): bool
    {
        return strtolower((string) $role->name) === self::SYSTEM_ROLE_NAME;
    }
}
