<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Each language's strings as a static file named by its content (/i18n/ar.<hash>.json), so the
 * web server compresses it and browsers keep it until the strings change. Editing a language in
 * the admin changes the source file, which makes a new file with a new name.
 */
class TranslationAssets
{
    private const DIRECTORY = 'i18n';

    /** @return array<string, string> locale => URL */
    public static function urls(array $locales): array
    {
        $urls = [];
        foreach ($locales as $locale) {
            $locale = strtolower((string) $locale);
            if (preg_match('/^[a-z]{2}(_[a-z]{2})?$/', $locale) !== 1) {
                continue;
            }
            if ($url = self::url($locale)) {
                $urls[$locale] = $url;
            }
        }

        return $urls;
    }

    public static function url(string $locale): ?string
    {
        $source = base_path("lang/{$locale}.json");
        if (! is_file($source)) {
            return null;
        }

        $key = sprintf('translation-asset:%s:%d:%d', $locale, filemtime($source), filesize($source));
        $url = Cache::get($key);
        if (is_string($url) && is_file(public_path(ltrim($url, '/')))) {
            return $url;
        }

        $url = self::publish($locale, $source);
        if ($url !== null) {
            Cache::forever($key, $url);
        }

        return $url;
    }

    private static function publish(string $locale, string $source): ?string
    {
        $messages = json_decode((string) file_get_contents($source), true);
        if (! is_array($messages)) {
            return null;
        }

        // Unescaped Arabic is about half the size of ا-style escapes.
        $json = json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $name = $locale.'.'.substr(md5($json), 0, 12).'.json';
        $directory = public_path(self::DIRECTORY);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            return null;
        }

        $path = $directory.DIRECTORY_SEPARATOR.$name;
        if (! is_file($path)) {
            $temporary = $path.'.'.getmypid().'.tmp';
            if (@file_put_contents($temporary, $json) === false || ! @rename($temporary, $path)) {
                @unlink($temporary);

                return null;
            }
        }

        // Older versions of this language are no longer referenced by new pages.
        foreach (glob($directory.DIRECTORY_SEPARATOR.$locale.'.*.json') ?: [] as $old) {
            if (basename($old) !== $name) {
                @unlink($old);
            }
        }

        return '/'.self::DIRECTORY.'/'.$name;
    }
}
