<?php

namespace App\Support;

/**
 * Values computed once per HTTP request (kept on the request, so the next request starts clean).
 * Groups are cleared when their models change mid-request; see AppServiceProvider.
 */
class RequestMemo
{
    public static function remember(string $group, string|int $key, callable $callback): mixed
    {
        $attributes = request()->attributes;
        $name = 'memo:'.$group.':'.$key;

        if ($attributes->has($name)) {
            return $attributes->get($name);
        }

        $value = $callback();
        $attributes->set($name, $value);

        return $value;
    }

    public static function forget(string ...$groups): void
    {
        $attributes = request()->attributes;

        foreach (array_keys($attributes->all()) as $name) {
            foreach ($groups as $group) {
                if (str_starts_with((string) $name, 'memo:'.$group.':')) {
                    $attributes->remove($name);
                }
            }
        }
    }
}
