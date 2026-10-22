<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\OrganizationApiService;
use App\Support\DeveloperApiResponse;

class AuthenticateBearerToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return DeveloperApiResponse::unauthorized(
                __('Unauthorized. Bearer Token is missing.'),
                'bearer_token_missing'
            );
        }

        $organizationApiService = app(OrganizationApiService::class);
        $organizationApiKey = $organizationApiService->findActiveTokenRecord($token);

        if (!$organizationApiKey) {
            return DeveloperApiResponse::unauthorized(
                __('Unauthorized. Invalid Bearer Token.'),
                'bearer_token_invalid'
            );
        }

        if (!$organizationApiKey->allows($request)) {
            return DeveloperApiResponse::forbidden(
                __('This API key is not allowed to use this endpoint.'),
                'api_key_scope_denied'
            );
        }

        // Attach organization to the request
        $request->merge(['organization' => $organizationApiKey->organization_id]);
        $request->attributes->set('developer_api_key_id', $organizationApiKey->id);
        $request->attributes->set('developer_api_key', $organizationApiKey);
        $organizationApiService->recordUsage($organizationApiKey);

        return $next($request);
    }
}
