<?php

namespace App\Services\Salla;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Proves a request came from Salla. Both strategies Salla offers rely on the app's webhook secret:
 * "Signature" sends an HMAC-SHA256 of the raw body, "Token" sends the secret in Authorization.
 */
class SallaWebhookVerifier
{
    public function isValid(Request $request): bool
    {
        $secret = (string) config('services.salla.webhook_secret');

        if ($secret === '') {
            return false;
        }

        $signature = (string) $request->header('X-Salla-Signature');

        if ($signature !== '') {
            return hash_equals(hash_hmac('sha256', $request->getContent(), $secret), strtolower($signature));
        }

        $token = trim((string) $request->header('Authorization'));

        if (Str::startsWith(Str::lower($token), 'bearer ')) {
            $token = trim(substr($token, 7));
        }

        return $token !== '' && hash_equals($secret, $token);
    }
}
