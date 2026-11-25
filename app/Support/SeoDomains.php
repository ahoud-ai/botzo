<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * The public sites that Google indexes separately (botzo.net and botzo.sa).
 *
 * A request on one of them uses its own host for canonical URLs and the
 * sitemap, and every page lists all of them as hreflang alternates, so Google
 * sees one site per market instead of duplicates. Any other host keeps the
 * admin canonical base URL.
 */
class SeoDomains
{
    /**
     * @return array<string, string> hreflang => host
     */
    public static function alternates(): array
    {
        $alternates = [];

        foreach ((array) config('frontend.seo_domain_alternates', []) as $hreflang => $host) {
            $host = self::normalizeHost((string) $host);
            if ($host !== '' && is_string($hreflang) && $hreflang !== '') {
                $alternates[$hreflang] = $host;
            }
        }

        return $alternates;
    }

    public static function isAlternateHost(string $host): bool
    {
        return in_array(self::normalizeHost($host), self::alternates(), true);
    }

    public static function canonicalBaseUrl(?Request $request = null, ?string $configuredBase = null): string
    {
        $request ??= request();
        $host = self::normalizeHost((string) $request->getHost());

        if (self::isAlternateHost($host)) {
            return 'https://'.$host;
        }

        $base = trim((string) ($configuredBase ?? Setting::where('key', 'seo_canonical_base_url')->value('value') ?? ''));
        if ($base === '') {
            $base = (string) config('app.url', url('/'));
        }

        return rtrim($base, '/');
    }

    /**
     * hreflang links for a path, or none when the request is not on an indexed host.
     *
     * @return array<string, string> hreflang => absolute URL
     */
    public static function alternateLinks(string $path, ?Request $request = null): array
    {
        $request ??= request();
        if (! self::isAlternateHost((string) $request->getHost())) {
            return [];
        }

        $path = trim($path, '/');
        $links = [];
        foreach (self::alternates() as $hreflang => $host) {
            $links[$hreflang] = 'https://'.$host.($path === '' ? '' : '/'.$path);
        }

        return $links;
    }

    /**
     * @return list<string>
     */
    public static function sitemapUrls(): array
    {
        return array_values(array_map(
            static fn (string $host) => 'https://'.$host.'/sitemap.xml',
            array_unique(self::alternates())
        ));
    }

    private static function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
