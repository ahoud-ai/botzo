<?php

namespace App\Http\Controllers;

use App\Services\PermissionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Check if the current user has a specific permission
     *
     * @param string $permission The permission to check (e.g., 'contacts.view_all')
     * @param int|null $organizationId The organization ID (defaults to current session organization)
     * @return void
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function checkPermission(string $permission, ?int $organizationId = null): void
    {
        $permissionService = new PermissionService();
        $organizationId = $organizationId ?? session()->get('current_organization');
        
        if (!$permissionService->can($permission, $organizationId)) {
            abort(403, __('You do not have permission to perform this action.'));
        }
    }
}
