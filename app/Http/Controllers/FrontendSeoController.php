<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class FrontendSeoController extends BaseController
{
    public function sitemap(): Response
    {
        $canonicalBaseUrl = trim((string) (Setting::where('key', 'seo_canonical_base_url')->value('value') ?? ''));
        if ($canonicalBaseUrl === '') {
            $canonicalBaseUrl = rtrim((string) config('app.url', url('/')), '/');
        }
        $canonicalBaseUrl = rtrim($canonicalBaseUrl, '/');

        $entries = [];

        foreach (config('frontend.seo_public_paths', []) as $path) {
            $normalizedPath = trim((string) $path, '/');
            $entries[] = [
                'loc' => $normalizedPath === '' ? $canonicalBaseUrl : $canonicalBaseUrl.'/'.$normalizedPath,
                'lastmod' => Carbon::now()->toAtomString(),
                'changefreq' => $normalizedPath === '' ? 'daily' : 'weekly',
                'priority' => $normalizedPath === '' ? '1.0' : '0.8',
            ];
        }

        foreach (Page::query()->orderBy('updated_at', 'desc')->get() as $page) {
            $slug = trim((string) $page->localizedSlug('en'));
            if ($slug === '') {
                continue;
            }

            $entries[] = [
                'loc' => $this->sitemapUrl($canonicalBaseUrl, 'pages/'.$slug),
                'lastmod' => optional($page->updated_at)->toAtomString() ?? Carbon::now()->toAtomString(),
                'changefreq' => 'monthly',
                'priority' => '0.7',
            ];
        }

        $uniqueEntries = collect($entries)
            ->unique('loc')
            ->values()
            ->all();

        return response()
            ->view('sitemap', ['entries' => $uniqueEntries], 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function sitemapUrl(string $canonicalBaseUrl, string $path): string
    {
        $segments = array_filter(explode('/', trim($path, '/')), static fn ($segment) => $segment !== '');

        if ($segments === []) {
            return $canonicalBaseUrl;
        }

        return $canonicalBaseUrl.'/'.implode('/', array_map('rawurlencode', $segments));
    }
}
