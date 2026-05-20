<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller as BaseController;
use App\Services\OrganizationRoleService;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Redirect;

class RoleController extends BaseController
{
    private $organizationRoleService;

    public function __construct(OrganizationRoleService $organizationRoleService)
    {
        $this->organizationRoleService = $organizationRoleService;
    }

    /**
     * Display a listing of organization roles
     *
     * @param Request $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $this->authorizeOwnerAccess(__('Only owners can manage roles'));

        $modules = \App\Models\Module::all();
        
        return Inertia::render('User/Role/Index', [
            'title' => __('Roles & Permissions'),
            'rows' => $this->organizationRoleService->get($request),
            'filters' => $request->all(),
            'modules' => $modules
        ]);
    }

    /**
     * Show the form for creating a new role
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        $this->authorizeOwnerAccess(__('Only owners can create roles'));

        $data = $this->organizationRoleService->getByUuid(null);
        $modules = \App\Models\Module::all();

        return Inertia::render('User/Role/Show', [
            'title' => __('Add Role'),
            'role' => $data['role'],
            'permissions' => $data['permissions'],
            'modules' => $modules
        ]);
    }

    /**
     * Display the specified role
     *
     * @param string $uuid
     * @return \Inertia\Response
     */
    public function show(string $uuid)
    {
        $this->authorizeOwnerAccess(__('Only owners can view roles'));

        $data = $this->organizationRoleService->getByUuid($uuid);

        if (!$data['role']) {
            abort(404, __('Role not found'));
        }
        
        $modules = \App\Models\Module::all();

        return Inertia::render('User/Role/Show', [
            'title' => __('Update Role'),
            'role' => $data['role'],
            'permissions' => $data['permissions'],
            'modules' => $modules
        ]);
    }

    /**
     * Store a newly created role
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $this->authorizeOwnerAccess(__('Only owners can create roles'));

        $request->validate([
            'name' => 'required|string|max:255',
            'permissions' => 'required|array',
        ]);

        try {
            $this->organizationRoleService->store($request);

            return Redirect::route('user.team.roles.index')->with(
                'status', [
                    'type' => 'success',
                    'message' => __('Role added successfully!')
                ]
            );
        } catch (\Exception $e) {
            return Redirect::back()->with(
                'status', [
                    'type' => 'error',
                    'message' => $e->getMessage()
                ]
            )->withInput();
        }
    }

    /**
     * Update the specified role
     *
     * @param Request $request
     * @param string $uuid
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, string $uuid)
    {
        $this->authorizeOwnerAccess(__('Only owners can update roles'));

        $request->validate([
            'name' => 'required|string|max:255',
            'permissions' => 'required|array',
        ]);

        try {
            $this->organizationRoleService->update($request, $uuid);

            return Redirect::route('user.team.roles.index')->with(
                'status', [
                    'type' => 'success',
                    'message' => __('Role updated successfully!')
                ]
            );
        } catch (\Exception $e) {
            return Redirect::back()->with(
                'status', [
                    'type' => 'error',
                    'message' => $e->getMessage()
                ]
            )->withInput();
        }
    }

    /**
     * Remove the specified role
     *
     * @param string $uuid
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(string $uuid)
    {
        $this->authorizeOwnerAccess(__('Only owners can delete roles'));

        try {
            $this->organizationRoleService->destroy($uuid);

            return Redirect::route('user.team.roles.index')->with(
                'status', [
                    'type' => 'success',
                    'message' => __('Role deleted successfully!')
                ]
            );
        } catch (\Exception $e) {
            return Redirect::back()->with(
                'status', [
                    'type' => 'error',
                    'message' => $e->getMessage()
                ]
            );
        }
    }

    /**
     * Get all roles for select dropdown
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllForSelect()
    {
        $this->authorizeOwnerAccess(__('Only owners can view organization roles'));

        $roles = $this->organizationRoleService->getAllForSelect();

        return response()->json([
            'roles' => $roles
        ]);
    }

    private function authorizeOwnerAccess(string $message): void
    {
        $permissionService = new PermissionService();

        if (! $permissionService->isOwner(session()->get('current_organization'))) {
            abort(403, $message);
        }
    }
}
