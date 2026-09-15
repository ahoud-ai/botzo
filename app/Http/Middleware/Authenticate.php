<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Authenticate extends Middleware
{
    public function handle($request, Closure $next, ...$guards)
    {
        if ($request->is('broadcasting/auth')) {
            file_put_contents(storage_path('logs/temp_auth_diag.log'), date('c') . ' ' . json_encode([
                'guards_param' => $guards,
                'expectsJson' => $request->expectsJson(),
                'guard_user_check' => Auth::guard('user')->check(),
                'guard_user_id' => Auth::guard('user')->id(),
                'guard_admin_check' => Auth::guard('admin')->check(),
                'guard_admin_id' => Auth::guard('admin')->id(),
                'has_session' => $request->hasSession(),
                'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            ]) . PHP_EOL, FILE_APPEND);
        }

        return parent::handle($request, $next, ...$guards);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        return route('login');
    }
}
