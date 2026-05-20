<?php

namespace App\Modules\Platform\Application\Context;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrganizationContextResolver
{
    public function currentId(?Request $request = null): ?int
    {
        $request ??= request();

        $organizationId = $request->input('organization')
            ?? $request->attributes->get('organization')
            ?? $request->session()->get('current_organization');

        if (is_numeric($organizationId)) {
            return (int) $organizationId;
        }

        $user = Auth::user();
        $fallbackId = data_get($user, 'organization_id');

        return is_numeric($fallbackId) ? (int) $fallbackId : null;
    }

    public function current(?Request $request = null): ?Organization
    {
        $organizationId = $this->currentId($request);

        if ($organizationId === null) {
            return null;
        }

        return Organization::query()->find($organizationId);
    }
}
