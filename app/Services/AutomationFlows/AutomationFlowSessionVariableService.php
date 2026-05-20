<?php

namespace App\Services\AutomationFlows;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AutomationFlowSessionVariableService
{
    private const MAX_KEY_LENGTH = 80;

    public function sanitizeKey(?string $key): string
    {
        $value = preg_replace('/\s+/u', ' ', trim((string) $key));

        return is_string($value) ? $value : '';
    }

    public function hasUsableKey(?string $key): bool
    {
        $sanitized = $this->sanitizeKey($key);

        return $sanitized !== '' && Str::length($sanitized) <= self::MAX_KEY_LENGTH;
    }

    public function put(array $state, ?string $key, mixed $value): array
    {
        $sanitized = $this->sanitizeKey($key);

        if ($sanitized === '') {
            return $state;
        }

        Arr::set($state, 'session_variables.'.$sanitized, $value);

        return $state;
    }

    public function get(array $state, ?string $key, mixed $default = null): mixed
    {
        $sanitized = $this->sanitizeKey($key);

        if ($sanitized === '') {
            return $default;
        }

        return Arr::get($state, 'session_variables.'.$sanitized, $default);
    }

    public function all(array $state): array
    {
        $variables = Arr::get($state, 'session_variables', []);

        return is_array($variables) ? $variables : [];
    }

    public function maxKeyLength(): int
    {
        return self::MAX_KEY_LENGTH;
    }
}
