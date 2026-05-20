<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     * Note: CSRF exclusions are now configured in bootstrap/app.php using validateCsrfTokens()
     * This method is kept as a safeguard for webhook routes
     *
     * @var array<int, string>
     */
    protected $except = [
        // Exclusions are configured in bootstrap/app.php
    ];

    /**
     * Determine if the request has a URI that should pass through CSRF verification.
     * This is a safeguard to ensure webhook routes are always excluded
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function inExceptArray($request)
    {
        $path = $request->path();
        
        // Check if path starts with webhook (safeguard)
        if (str_starts_with($path, 'webhook/')) {
            return true;
        }
        
        return parent::inExceptArray($request);
    }
}
