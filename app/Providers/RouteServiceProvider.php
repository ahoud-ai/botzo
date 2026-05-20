<?php

namespace App\Providers;

use App\Support\DeveloperApiResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     * 
     * Note: Routes are now registered in bootstrap/app.php in Laravel 12.
     */
    public function boot(): void
    {
        // Register API rate limiting
        // This is done here to ensure cache service is available
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('developer-api', function (Request $request) {
            $organizationId = (int) $request->input('organization', 0);
            $key = $organizationId > 0
                ? 'organization:'.$organizationId
                : 'token:'.sha1((string) $request->bearerToken());
            $limit = max(1, (int) config('services.developer_api.rate_limit_per_minute', 120));

            return Limit::perMinute($limit)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    $response = DeveloperApiResponse::error(
                        429,
                        __('Too many API requests. Please retry shortly.'),
                        'rate_limited'
                    );

                    foreach ($headers as $header => $value) {
                        $response->headers->set($header, $value);
                    }

                    return $response;
                });
        });

    }
}
