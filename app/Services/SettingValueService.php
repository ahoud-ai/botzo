<?php

namespace App\Services;

use App\Models\Setting;

class SettingValueService
{
    private array $cache = [];

    public function getRaw(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $value = Setting::query()
            ->where('key', $key)
            ->value('value');

        $this->cache[$key] = $value ?? $default;

        return $this->cache[$key];
    }

    public function getString(string $key, string $default = ''): string
    {
        $value = $this->getRaw($key, $default);

        if ($value === null) {
            return $default;
        }

        return (string) $value;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->getRaw($key, $default);

        if ($value === null || $value === '') {
            return $default;
        }

        return (int) $value;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->getRaw($key, $default ? '1' : '0');

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
            return false;
        }

        return $default;
    }

    public function getMany(array $keys): array
    {
        if (empty($keys)) {
            return [];
        }

        $keysToFetch = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $this->cache)) {
                $keysToFetch[] = $key;
            }
        }

        if (!empty($keysToFetch)) {
            $rows = Setting::query()
                ->whereIn('key', $keysToFetch)
                ->pluck('value', 'key')
                ->toArray();

            foreach ($keysToFetch as $key) {
                $this->cache[$key] = $rows[$key] ?? null;
            }
        }

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->cache[$key] ?? null;
        }

        return $result;
    }
}

