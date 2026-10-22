<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The developer guide at /developers: one Markdown file per page and language in
 * resources/docs/developers/{ar,en}, rendered with a table of contents.
 */
class DeveloperDocsController extends Controller
{
    public const PAGES = [
        'index' => ['ar' => 'البداية', 'en' => 'Getting started'],
        'authentication' => ['ar' => 'المفاتيح والأمان', 'en' => 'Keys and security'],
        'whatsapp-basics' => ['ar' => 'قواعد واتساب', 'en' => 'WhatsApp rules'],
        'messages' => ['ar' => 'إرسال رسائل واتساب', 'en' => 'Sending WhatsApp messages'],
        'resources' => ['ar' => 'جهات الاتصال والقوالب والحملات', 'en' => 'Contacts, templates and campaigns'],
        'assistant' => ['ar' => 'API موتشي (المساعد الذكي)', 'en' => 'Mochi API (AI assistant)'],
        'webhooks' => ['ar' => 'الـ Webhooks', 'en' => 'Webhooks'],
        'errors' => ['ar' => 'الأخطاء', 'en' => 'Errors'],
        'limits' => ['ar' => 'الحدود والسرعة', 'en' => 'Limits and performance'],
        'examples' => ['ar' => 'أمثلة كاملة', 'en' => 'Complete examples'],
    ];

    public function show(Request $request, string $page = 'index')
    {
        abort_unless(array_key_exists($page, self::PAGES), 404);

        $locale = $this->locale($request);
        $path = resource_path("docs/developers/{$locale}/{$page}.md");
        abort_unless(is_file($path), 404);

        $rendered = Cache::remember('developer-docs:'.$locale.':'.$page.':'.filemtime($path), now()->addDay(), function () use ($path) {
            return $this->render((string) file_get_contents($path));
        });

        $slugs = array_keys(self::PAGES);
        $index = array_search($page, $slugs, true);

        return response()->view('developers.docs', [
            'locale' => $locale,
            'page' => $page,
            'pages' => self::PAGES,
            'title' => self::PAGES[$page][$locale],
            'html' => str_replace('{{BASE_URL}}', rtrim(url('/'), '/'), $rendered['html']),
            'toc' => $rendered['toc'],
            'previous' => $slugs[$index - 1] ?? null,
            'next' => $slugs[$index + 1] ?? null,
        ]);
    }

    private function locale(Request $request): string
    {
        $requested = (string) $request->query('lang', '');
        if (in_array($requested, ['ar', 'en'], true)) {
            session(['developer_docs_locale' => $requested]);

            return $requested;
        }

        $remembered = session('developer_docs_locale') ?: app()->getLocale();

        return $remembered === 'en' ? 'en' : 'ar';
    }

    /** Markdown to HTML, with an id on every h2/h3 and a table of contents of the h2s. */
    private function render(string $markdown): array
    {
        $html = (string) Str::markdown($markdown, ['html_input' => 'allow', 'allow_unsafe_links' => false]);
        $toc = [];
        $count = 0;

        $html = preg_replace_callback('/<h([23])>(.*?)<\/h\1>/s', function (array $match) use (&$toc, &$count) {
            $count++;
            $id = 's'.$count;
            if ($match[1] === '2') {
                $toc[] = ['id' => $id, 'text' => strip_tags($match[2])];
            }

            return sprintf('<h%1$s id="%2$s"><a class="anchor" href="#%2$s" aria-hidden="true">#</a>%3$s</h%1$s>', $match[1], $id, $match[2]);
        }, $html);

        // Wide tables scroll inside their own box on small screens.
        $html = str_replace(['<table>', '</table>'], ['<div class="table-wrap"><table>', '</table></div>'], $html);

        return ['html' => $html, 'toc' => $toc];
    }
}
