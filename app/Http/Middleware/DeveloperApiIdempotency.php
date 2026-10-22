<?php

namespace App\Http\Middleware;

use App\Support\DeveloperApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * An app that retries a send after a timeout passes the same Idempotency-Key header and gets the
 * first result back instead of a second message. Keys are kept for 24 hours per organization.
 */
class DeveloperApiIdempotency
{
    private const TTL_SECONDS = 86400;

    public function handle(Request $request, Closure $next)
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '') {
            return $next($request);
        }

        if (strlen($key) > 100) {
            return DeveloperApiResponse::validation(
                ['Idempotency-Key' => [__('The Idempotency-Key header may not be longer than 100 characters.')]]
            );
        }

        $cacheKey = 'developer-api:idempotency:'.$request->input('organization').':'.sha1($key);
        $fingerprint = sha1($request->method().'|'.$request->path().'|'.json_encode($request->except('organization')));

        $stored = Cache::get($cacheKey);
        if (is_array($stored)) {
            return $this->replay($stored, $fingerprint);
        }

        $lock = Cache::lock($cacheKey.':lock', 60);
        if (! $lock->get()) {
            return DeveloperApiResponse::error(409, __('A request with this Idempotency-Key is still being processed.'), 'idempotency_in_progress');
        }

        try {
            // Another request may have finished while this one waited for the lock.
            $stored = Cache::get($cacheKey);
            if (is_array($stored)) {
                return $this->replay($stored, $fingerprint);
            }

            $response = $next($request);

            // Server errors and rate limits are worth retrying, so only final answers are kept.
            if ($response->getStatusCode() < 500 && $response->getStatusCode() !== 429) {
                Cache::put($cacheKey, [
                    'fingerprint' => $fingerprint,
                    'status' => $response->getStatusCode(),
                    'body' => $response->getContent(),
                ], self::TTL_SECONDS);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    private function replay(array $stored, string $fingerprint)
    {
        if (($stored['fingerprint'] ?? null) !== $fingerprint) {
            return DeveloperApiResponse::unprocessable(
                __('This Idempotency-Key was already used for a different request.'),
                'idempotency_key_reused'
            );
        }

        return response((string) $stored['body'], (int) $stored['status'])
            ->header('Content-Type', 'application/json')
            ->header('Idempotent-Replayed', 'true');
    }
}
