<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Organization;
use App\Services\Admin\OrganizationContactDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationContactController extends BaseController
{
    /**
     * Read-only, paginated contacts of an organization and its branches (JSON for the admin tab).
     */
    public function index(Request $request, string $organization, OrganizationContactDirectory $directory): JsonResponse
    {
        $organization = Organization::query()->where('uuid', $organization)->whereNull('deleted_at')->firstOrFail();
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = $directory->paginate($organization, $validated['search'] ?? null, (int) ($validated['page'] ?? 1));

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
        ]);
    }
}
