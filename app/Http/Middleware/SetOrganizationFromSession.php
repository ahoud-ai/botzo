<?php

namespace App\Http\Middleware;

use App\Services\OrganizationSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetOrganizationFromSession
{
    public function __construct(
        private readonly OrganizationSessionService $organizationSessionService,
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next)
    {
        $organizationId = $request->session()->get('current_organization');

        if ($organizationId && $request->user()) {
            $accessibleOrganizationId = $this->organizationSessionService->accessibleOrganizationIdForUser(
                $request->user()->id,
                (int) $organizationId
            );

            if (!$accessibleOrganizationId) {
                $request->session()->forget('current_organization');

                return $next($request);
            }

            $request->merge(['organization' => $accessibleOrganizationId]);
        } elseif ($organizationId) {
            $request->merge(['organization' => $organizationId]);
        }

        return $next($request);
    }
}
