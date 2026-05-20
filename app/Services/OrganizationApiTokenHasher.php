<?php

namespace App\Services;

use Illuminate\Support\Str;

class OrganizationApiTokenHasher
{
    public function generatePlainTextToken(int $length = 40): string
    {
        return $this->generatePlainToken($length);
    }

    public function generatePlainToken(int $length = 40): string
    {
        return Str::random($length);
    }

    public function hashToken(?string $plainToken): ?string
    {
        return $this->hash($plainToken);
    }

    public function hash(?string $plainToken): ?string
    {
        if (empty($plainToken)) {
            return null;
        }

        return hash_hmac('sha256', $plainToken, $this->resolveKey());
    }

    public function lastFour(?string $plainToken): ?string
    {
        if (empty($plainToken)) {
            return null;
        }

        return substr($plainToken, -4);
    }

    public function looksHashed(?string $storedToken): bool
    {
        return is_string($storedToken)
            && strlen($storedToken) === 64
            && ctype_xdigit($storedToken);
    }

    private function resolveKey(): string
    {
        $key = (string) config('app.key', '');

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded !== false && $decoded !== '') {
                return $decoded;
            }
        }

        return $key;
    }
}
