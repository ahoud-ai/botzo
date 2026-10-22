<?php

namespace App\Services\WebWidget;

use App\Models\WebWidget;

/**
 * The pages of a customer's site the assistant may take a visitor to (an allowlist per widget).
 * A page is a label and a link: a path on the site ("/pricing"), a section ("#features") or a
 * full address on one of the widget's allowed domains.
 */
class WebWidgetPages
{
    /**
     * Clean pages from the settings form.
     *
     * @param  array<int, mixed>  $pages
     * @return array{pages: list<array{label:string,url:string}>, errors: array<string,string>}
     */
    public static function normalize(array $pages, WebWidget $widget): array
    {
        $clean = [];
        $errors = [];
        $maxLabel = (int) config('web_widget.max_page_label_length', 40);

        foreach (array_values($pages) as $index => $page) {
            $label = trim((string) data_get($page, 'label', ''));
            $url = trim((string) data_get($page, 'url', ''));

            if ($label === '' && $url === '') {
                continue;
            }

            if ($label === '' || mb_strlen($label) > $maxLabel) {
                $errors["pages.$index.label"] = __('Enter a page name of up to :count characters.', ['count' => $maxLabel]);
            }

            if (! self::urlAllowed($url, $widget)) {
                $errors["pages.$index.url"] = __('Use a link on your own website, like /pricing or https://yoursite.com/pricing.');
            }

            $clean[] = ['label' => mb_substr($label, 0, $maxLabel), 'url' => mb_substr($url, 0, 500)];
        }

        return ['pages' => array_slice($clean, 0, (int) config('web_widget.max_pages', 10)), 'errors' => $errors];
    }

    /**
     * The pages as the widget and the assistant see them, each with a stable key ("p1", "p2"…).
     *
     * @return list<array{key:string,label:string,url:string}>
     */
    public static function forWidget(array $settings): array
    {
        $pages = [];

        foreach (array_values((array) ($settings['pages'] ?? [])) as $index => $page) {
            $label = trim((string) data_get($page, 'label', ''));
            $url = trim((string) data_get($page, 'url', ''));

            if ($label !== '' && $url !== '') {
                $pages[] = ['key' => 'p'.($index + 1), 'label' => $label, 'url' => $url];
            }
        }

        return $pages;
    }

    /**
     * @return array{key:string,label:string,url:string}|null
     */
    public static function find(array $settings, string $key): ?array
    {
        foreach (self::forWidget($settings) as $page) {
            if ($page['key'] === $key) {
                return $page;
            }
        }

        return null;
    }

    public static function urlAllowed(string $url, WebWidget $widget): bool
    {
        if ($url === '' || preg_match('/[\s<>"\'`]/u', $url) === 1) {
            return false;
        }

        // A path or a section of the current site.
        if (preg_match('#^(/(?!/)|\#)#', $url) === 1) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && $widget->allowsOrigin($scheme.'://'.parse_url($url, PHP_URL_HOST));
    }
}
