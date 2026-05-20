<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreUser;
use App\Services\AdminUserDirectoryService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class UserController extends BaseController
{
    private $userService;
    private AdminUserDirectoryService $adminUserDirectoryService;

    /**
     * UserController constructor.
     *
     * @param UserService $userService
     */
    public function __construct($role = 'user')
    {
        $this->userService = new UserService($role);
        $this->adminUserDirectoryService = app(AdminUserDirectoryService::class);
    }

    /**
     * Display a listing of users.
     *
     * @param Request $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $payload = $this->adminUserDirectoryService->indexPayload($request);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            $rows = collect($payload['rows']->items())
                ->map(fn (array $row) => [
                    'id' => $row['id'],
                    'first_name' => $row['first_name'] ?? null,
                    'last_name' => $row['last_name'] ?? null,
                    'full_name' => $row['full_name'] ?? null,
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                ])
                ->values();

            return response()->json([
                'rows' => $rows,
            ]);
        }

        return Inertia::render('Admin/User/Directory', $payload);
    }

    /**
     * Display the specified user.
     *
     * @param string $uuid
     * @return \Inertia\Response
     */
    public function show(Request $request, $uuid = NULL)
    {
        $payload = $this->adminUserDirectoryService->detailPayload((int) $uuid);
        $payload['initialTab'] = in_array($request->query('tab'), ['overview', 'access', 'edit'], true)
            ? $request->query('tab')
            : 'overview';

        return Inertia::render('Admin/User/ShowDetails', $payload);
    }

    /**
     * Display Form
     *
     * @param $request
     */
    public function create(Request $request)
    {
        $catalog = app(\App\Services\OrganizationService::class)->adminProvisioningPayload();

        return Inertia::render('Admin/User/Create', [
            'title' => __('Create user'),
            'plans' => $catalog['plans'],
            'companies' => $catalog['companies'],
            'companyCatalog' => $catalog['companyCatalog'],
            'filters' => $request->all(),
        ]);
    }

    /**
     * Store a newly created user.
     *
     * @param Request $request
     */
    public function store(StoreUser $request)
    {
        $user = $this->userService->store($request);

        return redirect('/admin/users/'.$user->id)->with(
            'status', [
                'type' => 'success', 
                'message' => __('User created successfully!')
            ]
        );
    }

    /**
     * Update the specified user.
     *
     * @param Request $request
     */
    public function update(StoreUser $request, $uuid)
    {
        $this->userService->update($request, $uuid);

        return redirect('/admin/users/'.$uuid.'?tab=edit')->with(
            'status', [
                'type' => 'success', 
                'message' => __('User updated successfully!')
            ]
        );
    }

    /**
     * Remove the specified user.
     *
     * @param String $uuid
     */
    public function destroy($uuid)
    {
        try {
            $this->userService->destroy($uuid);
        } catch (ValidationException $exception) {
            return redirect()->back()->with(
                'status',
                [
                    'type' => 'error',
                    'message' => collect($exception->errors())->flatten()->first() ?: __('Unable to delete this user right now.'),
                ]
            );
        }

        return redirect('/admin/users')->with(
            'status',
            [
                'type' => 'success',
                'message' => __('User deleted successfully!'),
            ]
        );
    }

    /**
     * Suspend the specified platform user account.
     */
    public function suspend($uuid)
    {
        $this->userService->suspend($uuid);

        return redirect()->back()->with(
            'status',
            [
                'type' => 'success',
                'message' => __('User account suspended successfully!'),
            ]
        );
    }

    /**
     * Restore the specified suspended platform user account.
     */
    public function restore($uuid)
    {
        $this->userService->restore($uuid);

        return redirect()->back()->with(
            'status',
            [
                'type' => 'success',
                'message' => __('User account restored successfully!'),
            ]
        );
    }
}
