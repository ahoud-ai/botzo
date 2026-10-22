<?php

namespace App\Http\Middleware;

use App\Support\ServerTiming;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeveloperApiTiming
{
    // Requests slower than this are logged with their breakdown.
    private const SLOW_MS = 2000;

    public function handle(Request $request, Closure $next)
    {
        ServerTiming::reset();
        $startedAt = microtime(true);

        $response = $next($request);

        $totalMs = (microtime(true) - $startedAt) * 1000;
        $header = ServerTiming::header($totalMs);
        $response->headers->set('Server-Timing', $header);

        if ($totalMs > self::SLOW_MS) {
            Log::warning('Slow developer API request', [
                'path' => $request->path(),
                'organization_id' => $request->input('organization'),
                'timing' => $header,
            ]);
        }

        return $response;
    }
}
