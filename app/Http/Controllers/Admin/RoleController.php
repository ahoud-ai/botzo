<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreRole;
use App\Services\RoleService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoleController extends BaseController
{
    private RoleService $roleService;

    /**
     * RoleController constructor.
     *
     * @param RoleService $roleService
     */
    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    /**
     * Display a listing of roles.
     *
     * @param Request $request
     * @return \Inertia\Response
     */
    public function index(Request $request){
        return Inertia::render('Admin/Role/Index', [
            'title' => __('Roles'),
            'allowCreate' => true,
            'rows' => $this->roleService->get($request), 
            'filters' => $request->all()
        ]);
    }

    /**
     * Display the details of a specific role.
     *
     * @param string $uuid
     * @return \Inertia\Response
     */
    public function show($uuid = NULL)
    {
        $res = $this->roleService->getByUuid($uuid);

        return Inertia::render('Admin/Role/Show', ['title' => __('Update role'), 'role' => $res['role'], 'modules' => $res['modules'], 'permissions' => $res['permissions']]);
    }

    /**
     * Display Form
     *
     * @param $request
     */
    public function create(Request $request)
    {
        $res = $this->roleService->getByUuid(NULL);

        return Inertia::render('Admin/Role/Show', ['title' => __('Add role'), 'role' => $res['role'], 'modules' => $res['modules'], 'permissions' => $res['permissions']]);
    }

    /**
     * Store a newly created role.
     *
     * @param StoreRole $request
     */
    public function store(StoreRole $request)
    {
        $this->roleService->store($request);

        return redirect('/admin/team/roles')->with(
            'status', [
                'type' => 'success', 
                'message' => __('Role added successfully!')
            ]
        );
    }

   /**
     * Update the details of a specific role.
     *
     * @param StoreRole $request
     * @param string $uuid
     */
    public function update(StoreRole $request, $uuid)
    {
        $this->roleService->update($request, $uuid);

        return redirect('/admin/team/roles')->with(
            'status', [
                'type' => 'success', 
                'message' => __('Role updated successfully!')
            ]
        );
    }

    /**
     * Check if role has users assigned to it.
     *
     * @param string $uuid
     */
    public function checkUsers($uuid)
    {
        $result = $this->roleService->checkUsers($uuid);
        
        if ($result['has_users']) {
            // Get available roles for transfer
            $availableRoles = \App\Models\Role::where('uuid', '!=', $uuid)
                ->whereNull('deleted_at')
                ->get(['uuid', 'name']);
                
            return response()->json([
                'has_users' => true,
                'user_count' => $result['user_count'],
                'users' => $result['users'],
                'role' => $result['role'],
                'available_roles' => $availableRoles
            ]);
        }
        
        return response()->json(['has_users' => false]);
    }

    /**
     * Previous endpoint: deleting with transfer is blocked by policy.
     *
     * @param Request $request
     * @param String $uuid
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroyWithTransfer(Request $request, $uuid)
    {
        $this->roleService->destroyWithTransfer($uuid, (string) $request->input('new_role', ''));

        return redirect('/admin/team/roles')->with('status', [
            'type' => 'success',
            'message' => __('Role deleted successfully!'),
        ]);
    }

    /**
     * Remove a specific role.
     *
     * @param string $uuid
     */
    public function destroy($uuid)
    {
        $this->roleService->destroy((string) $uuid);

        return redirect('/admin/team/roles')->with(
            'status', [
                'type' => 'success',
                'message' => __('Role deleted successfully!'),
            ]
        );
    }
}
