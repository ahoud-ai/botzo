<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\OrganizationSessionService;

class CheckOrganizationId
{
    public function __construct(
        private readonly OrganizationSessionService $organizationSessionService,
    ) {
    }

    public function handle($request, Closure $next)
    {
        $organizationId = session('current_organization');
        $accessibleOrganizationId = $this->organizationSessionService->accessibleOrganizationIdForUser(
            $request->user()?->id,
            $organizationId ? (int) $organizationId : null
        );

        if (!$accessibleOrganizationId) {
            session()->forget('current_organization');

            return redirect()->route('user.organization.index');
        }

        return $next($request);
    }
}
