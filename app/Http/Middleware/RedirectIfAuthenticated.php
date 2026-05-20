<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\OrganizationSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  ...$guards
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? ['user', 'admin'] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();
                if ($user->role == 'admin') {
                    return redirect('/admin/dashboard');
                } else {
                    $organizationId = session('current_organization')
                        ?: app(OrganizationSessionService::class)->firstOrganizationIdForUser($user->id);

                    if ($organizationId) {
                        session()->put('current_organization', $organizationId);

                        return redirect('/dashboard');
                    }

                    return redirect(route('user.organization.index'));
                }
            }
        }

        return $next($request);
    }
}
