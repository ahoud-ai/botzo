<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CheckAppStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $currentPath = $request->path();

        if (Str::startsWith($currentPath, ['update'])) {
            abort(404);
        }

        if (! $this->isBootstrapped()) {
            abort(503, __('Application setup must be completed through the VPS deployment workflow.'));
        }

        return $next($request);
    }

    /**
     * Checks if the application has completed its deployment bootstrap.
     *
     * @return bool
     */
    public function isBootstrapped(): bool
    {
        return file_exists(storage_path('in'.'stalled'));
    }
}
