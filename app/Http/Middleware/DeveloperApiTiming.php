<?php

namespace App\Http\Middleware;

use App\Services\WhatsappService;
use App\Support\ServerTiming;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeveloperApiTiming
{
    // Requests slower than this are logged with their breakdown.
    private const SLOW_MS = 2000;

    public function handle(Request $request, Closure $next)
    {
        ServerTiming::reset();
        $startedAt = microtime(true);
        DB::listen(fn ($query) => ServerTiming::add('db', (float) $query->time));

        WhatsappService::$deferBroadcasts = true;
        try {
            $response = $next($request);
        } finally {
            WhatsappService::$deferBroadcasts = false;
        }

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
