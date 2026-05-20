<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Auth;

class CheckClientRole
{
    public function handle($request, Closure $next)
    {
        if (Auth::check() && Auth::user()?->role === 'user') {
            $organizationId = (int) session()->get('current_organization');
            $requiredPermission = $this->requiredPermissionForRequest($request);

            if ($requiredPermission !== null && ! app(PermissionService::class)->can($requiredPermission, $organizationId)) {
                return to_route('dashboard');
            }
        }

        return $next($request);
    }

    private function requiredPermissionForRequest($request): ?string
    {
        if (
            $request->is('billing*')
            || $request->is('subscription*')
            || $request->is('pay')
            || $request->is('dismiss-team-prompt/*')
        ) {
            return 'settings.billing_subscription';
        }

        if ($request->is('developer-tools*')) {
            return 'developer_tools.view';
        }

        if (
            $request->is('settings*')
            || $request->is('contact-fields*')
            || $request->is('whatsapp/exchange-code')
        ) {
            return 'settings.manage';
        }

        return null;
    }
}
