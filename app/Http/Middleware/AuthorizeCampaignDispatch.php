<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeCampaignDispatch
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isAuthorized($request)) {
            return $next($request);
        }

        return response()->json([
            'status' => 'forbidden',
            'message' => __('Unauthorized campaign dispatch request.'),
        ], 403);
    }

    private function isAuthorized(Request $request): bool
    {
        if ($request->hasValidSignature()) {
            return true;
        }

        $configuredToken = trim((string) config('app.campaign_dispatch_token', ''));

        if ($configuredToken !== '') {
            $providedToken = trim((string) (
                $request->header('X-Campaign-Dispatch-Token')
                ?: $request->query('token', $request->input('token', ''))
            ));

            return $providedToken !== '' && hash_equals($configuredToken, $providedToken);
        }

        return app()->environment(['local', 'testing']);
    }
}
