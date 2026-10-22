<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ $locale === 'ar' ? 'دليل المطوّرين' : 'Developer guide' }} · Botzo</title>
    <meta name="description" content="{{ $locale === 'ar' ? 'دليل ربط تطبيقك بواتساب ومساعد موتشي الذكي عبر Botzo API.' : 'Connect your app to WhatsApp and the Mochi AI assistant with the Botzo API.' }}">
    <link rel="alternate" hreflang="ar" href="{{ url('/developers'.($page === 'index' ? '' : '/'.$page)) }}?lang=ar">
    <link rel="alternate" hreflang="en" href="{{ url('/developers'.($page === 'index' ? '' : '/'.$page)) }}?lang=en">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #ffffff; --surface: #f6f8fa; --surface-2: #eef2f6; --border: #e3e8ee; --text: #0f172a; --muted: #5b6577;
            --primary: #16a34a; --primary-ink: #ffffff; --link: #0f7a3a; --code-bg: #0d1117; --code-text: #e6edf3; --inline-code: #eef2f6;
            --note: #eff6ff; --note-border: #3b82f6; --warn: #fffbeb; --warn-border: #f59e0b;
            --get: #2563eb; --post: #16a34a; --put: #d97706; --delete: #dc2626;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0b0f14; --surface: #11171e; --surface-2: #17202a; --border: #232d38; --text: #e6edf3; --muted: #9aa7b6;
                --primary: #22c55e; --primary-ink: #04120a; --link: #4ade80; --code-bg: #0a0d12; --code-text: #e6edf3; --inline-code: #1b2530;
                --note: #0f1b2d; --note-border: #3b82f6; --warn: #231b0b; --warn-border: #f59e0b;
            }
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; scroll-padding-top: 80px; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 16px/1.75 "IBM Plex Sans Arabic", system-ui, -apple-system, "Segoe UI", Tahoma, sans-serif; -webkit-font-smoothing: antialiased; }
        a { color: var(--link); text-underline-offset: 3px; }
        a:focus-visible, button:focus-visible, summary:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; border-radius: 4px; }
        .topbar { position: sticky; top: 0; z-index: 20; display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .75rem 1.25rem; background: color-mix(in srgb, var(--bg) 88%, transparent); backdrop-filter: blur(10px); border-bottom: 1px solid var(--border); }
        .brand { display: flex; align-items: center; gap: .6rem; font-weight: 700; color: var(--text); text-decoration: none; }
        .brand-badge { font-size: .72rem; font-weight: 600; padding: .1rem .55rem; border-radius: 999px; background: var(--surface-2); color: var(--muted); }
        .top-links { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
        .pill { display: inline-flex; align-items: center; min-height: 36px; padding: .3rem .85rem; border-radius: 999px; border: 1px solid var(--border); color: var(--text); text-decoration: none; font-size: .88rem; font-weight: 500; }
        .pill:hover { border-color: var(--primary); }
        .pill.primary { background: var(--primary); color: var(--primary-ink); border-color: transparent; }
        .layout { display: grid; grid-template-columns: 260px minmax(0, 1fr) 220px; gap: 2.5rem; max-width: 1360px; margin: 0 auto; padding: 0 1.25rem; }
        .sidebar, .toc { position: sticky; top: 72px; align-self: start; max-height: calc(100vh - 88px); overflow-y: auto; padding: 1.5rem 0; }
        .sidebar h2, .toc h2 { margin: 0 0 .6rem; font-size: .75rem; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }
        .nav { list-style: none; margin: 0; padding: 0; }
        .nav a { display: block; padding: .45rem .75rem; border-radius: .6rem; color: var(--text); text-decoration: none; font-size: .93rem; }
        .nav a:hover { background: var(--surface); }
        .nav a[aria-current="page"] { background: color-mix(in srgb, var(--primary) 14%, transparent); color: var(--text); font-weight: 600; }
        .toc a { display: block; padding: .3rem 0; color: var(--muted); text-decoration: none; font-size: .86rem; line-height: 1.5; }
        .toc a:hover { color: var(--text); }
        .mobile-nav { display: none; margin: 1rem 0 0; border: 1px solid var(--border); border-radius: .8rem; background: var(--surface); }
        .mobile-nav summary { cursor: pointer; padding: .8rem 1rem; font-weight: 600; min-height: 44px; }
        .mobile-nav .nav { padding: 0 .5rem .6rem; }
        main { min-width: 0; padding: 2rem 0 4rem; }
        .content { max-width: 820px; }
        .content h1 { font-size: 2.1rem; line-height: 1.3; margin: .2rem 0 1rem; }
        .content h2 { font-size: 1.45rem; line-height: 1.4; margin: 2.6rem 0 .9rem; padding-top: .4rem; border-top: 1px solid var(--border); }
        .content h3 { font-size: 1.12rem; margin: 1.8rem 0 .6rem; }
        .content h2, .content h3 { position: relative; }
        .content h2 .anchor, .content h3 .anchor { position: absolute; inset-inline-start: -1.1em; color: var(--muted); text-decoration: none; opacity: 0; }
        .content h2:hover .anchor, .content h3:hover .anchor { opacity: 1; }
        .content p, .content li { color: var(--text); }
        .content ul, .content ol { padding-inline-start: 1.4rem; }
        .content li { margin: .25rem 0; }
        .content code { font-family: "JetBrains Mono", ui-monospace, Menlo, Consolas, monospace; font-size: .86em; background: var(--inline-code); padding: .12rem .38rem; border-radius: .35rem; direction: ltr; unicode-bidi: isolate; }
        .content pre { position: relative; margin: 1rem 0 1.4rem; padding: 1rem 1.1rem; background: var(--code-bg); color: var(--code-text); border-radius: .8rem; overflow-x: auto; direction: ltr; text-align: left; font-size: .85rem; line-height: 1.6; border: 1px solid #1f2933; }
        .content :not(pre) > code { overflow-wrap: anywhere; }
        .content pre code { background: none; padding: 0; font-size: inherit; color: inherit; }
        .copy { position: absolute; top: .5rem; inset-inline-end: .5rem; right: .5rem; min-height: 32px; padding: .2rem .65rem; border-radius: .5rem; border: 1px solid #30363d; background: #161b22; color: #c9d1d9; font: 500 .75rem system-ui, sans-serif; cursor: pointer; }
        .copy:hover { border-color: #8b949e; }
        .code-tabs { margin: 1rem 0 1.4rem; border: 1px solid #1f2933; border-radius: .8rem; overflow: hidden; background: var(--code-bg); }
        .code-tabs .tabs { display: flex; gap: .25rem; padding: .45rem .5rem 0; overflow-x: auto; border-bottom: 1px solid #1f2933; direction: ltr; }
        .code-tabs .tabs button { min-height: 36px; padding: .35rem .8rem; border: 0; border-radius: .5rem .5rem 0 0; background: transparent; color: #8b949e; font: 500 .8rem system-ui, sans-serif; cursor: pointer; white-space: nowrap; }
        .code-tabs .tabs button[aria-selected="true"] { background: #161b22; color: #e6edf3; }
        .code-tabs pre { margin: 0; border: 0; border-radius: 0; }
        .table-wrap { overflow-x: auto; margin: 1rem 0 1.4rem; border: 1px solid var(--border); border-radius: .8rem; }
        .content table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .content th, .content td { padding: .6rem .8rem; border-bottom: 1px solid var(--border); text-align: start; vertical-align: top; }
        .content th { background: var(--surface); font-weight: 600; white-space: nowrap; }
        .content tr:last-child td { border-bottom: 0; }
        .content blockquote { margin: 1.2rem 0; padding: .8rem 1rem; border-inline-start: 4px solid var(--note-border); background: var(--note); border-radius: .5rem; }
        .content blockquote p { margin: .3rem 0; }
        .content blockquote.warn { border-color: var(--warn-border); background: var(--warn); }
        .method { display: inline-block; min-width: 3.6rem; padding: .05rem .5rem; margin-inline-end: .4rem; border-radius: .4rem; color: #fff; font: 700 .72rem "JetBrains Mono", monospace; text-align: center; vertical-align: middle; direction: ltr; }
        .method.get { background: var(--get); } .method.post { background: var(--post); } .method.put { background: var(--put); } .method.delete { background: var(--delete); }
        .pager { display: flex; justify-content: space-between; gap: 1rem; margin-top: 3rem; padding-top: 1.5rem; border-top: 1px solid var(--border); }
        .pager a { flex: 1; padding: .9rem 1rem; border: 1px solid var(--border); border-radius: .8rem; text-decoration: none; color: var(--text); }
        .pager a:hover { border-color: var(--primary); }
        .pager small { display: block; color: var(--muted); }
        .pager .next { text-align: end; }
        @media (max-width: 1200px) { .layout { grid-template-columns: 240px minmax(0, 1fr); } .toc { display: none; } }
        @media (max-width: 860px) {
            .layout { grid-template-columns: minmax(0, 1fr); gap: 0; padding: 0 1rem; }
            .sidebar { display: none; }
            .mobile-nav { display: block; }
            .content h1 { font-size: 1.7rem; }
            .top-links .hide-sm { display: none; }
        }
        @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } }
    </style>
</head>
<body>
    <header class="topbar">
        <a class="brand" href="{{ url('/developers') }}">
            <img src="{{ asset('images/botzo-logo-new.svg') }}" alt="Botzo" height="26">
            <span class="brand-badge">{{ $locale === 'ar' ? 'دليل المطوّرين' : 'Developers' }}</span>
        </a>
        <nav class="top-links" aria-label="{{ $locale === 'ar' ? 'روابط' : 'Links' }}">
            <a class="pill" href="?lang={{ $locale === 'ar' ? 'en' : 'ar' }}" hreflang="{{ $locale === 'ar' ? 'en' : 'ar' }}">{{ $locale === 'ar' ? 'English' : 'العربية' }}</a>
            <a class="pill hide-sm" href="{{ url('/') }}">botzo.net</a>
            <a class="pill primary" href="{{ url('/developer-tools/access-tokens') }}">{{ $locale === 'ar' ? 'أنشئ مفتاح API' : 'Create an API key' }}</a>
        </nav>
    </header>

    <div class="layout">
        <aside class="sidebar" aria-label="{{ $locale === 'ar' ? 'أقسام الدليل' : 'Guide sections' }}">
            <h2>{{ $locale === 'ar' ? 'الدليل' : 'Guide' }}</h2>
            <ul class="nav">
                @foreach ($pages as $slug => $names)
                    <li><a href="{{ url('/developers'.($slug === 'index' ? '' : '/'.$slug)) }}" @if ($slug === $page) aria-current="page" @endif>{{ $names[$locale] }}</a></li>
                @endforeach
            </ul>
        </aside>

        <main>
            <details class="mobile-nav">
                <summary>{{ $locale === 'ar' ? 'أقسام الدليل' : 'Guide sections' }}: {{ $title }}</summary>
                <ul class="nav">
                    @foreach ($pages as $slug => $names)
                        <li><a href="{{ url('/developers'.($slug === 'index' ? '' : '/'.$slug)) }}" @if ($slug === $page) aria-current="page" @endif>{{ $names[$locale] }}</a></li>
                    @endforeach
                </ul>
            </details>

            <article class="content">
                {!! $html !!}
            </article>

            <nav class="pager content" aria-label="{{ $locale === 'ar' ? 'التنقل بين الصفحات' : 'Page navigation' }}">
                @if ($previous)
                    <a href="{{ url('/developers'.($previous === 'index' ? '' : '/'.$previous)) }}"><small>{{ $locale === 'ar' ? 'السابق' : 'Previous' }}</small>{{ $pages[$previous][$locale] }}</a>
                @else <span></span> @endif
                @if ($next)
                    <a class="next" href="{{ url('/developers/'.$next) }}"><small>{{ $locale === 'ar' ? 'التالي' : 'Next' }}</small>{{ $pages[$next][$locale] }}</a>
                @endif
            </nav>
        </main>

        @if (count($toc) > 1)
            <aside class="toc" aria-label="{{ $locale === 'ar' ? 'في هذه الصفحة' : 'On this page' }}">
                <h2>{{ $locale === 'ar' ? 'في هذه الصفحة' : 'On this page' }}</h2>
                @foreach ($toc as $item)
                    <a href="#{{ $item['id'] }}">{{ $item['text'] }}</a>
                @endforeach
            </aside>
        @endif
    </div>

    <script>
        (function () {
            var labels = { bash: 'cURL', shell: 'cURL', js: 'JavaScript', javascript: 'JavaScript', ts: 'TypeScript', php: 'PHP', python: 'Python', dart: 'Flutter (Dart)', swift: 'Swift', kotlin: 'Kotlin', java: 'Java', ruby: 'Ruby', json: 'JSON', http: 'HTTP' };
            var copied = @json($locale === 'ar' ? 'تم النسخ' : 'Copied');
            var copyText = @json($locale === 'ar' ? 'نسخ' : 'Copy');
            var language = function (pre) {
                var code = pre.querySelector('code');
                var match = code && /language-([\w-]+)/.exec(code.className);
                return match ? match[1] : 'text';
            };
            document.querySelectorAll('.content pre').forEach(function (pre) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'copy';
                button.textContent = copyText;
                button.addEventListener('click', function () {
                    navigator.clipboard.writeText(pre.innerText.replace(new RegExp(copyText + '$|' + copied + '$'), '').trim()).then(function () {
                        button.textContent = copied;
                        setTimeout(function () { button.textContent = copyText; }, 1500);
                    });
                });
                pre.appendChild(button);
            });
            // Several code blocks in one .code-tabs box become tabs; the chosen language is remembered.
            var preferred = null;
            try { preferred = localStorage.getItem('botzo-docs-lang'); } catch (e) {}
            var groups = [];
            document.querySelectorAll('.code-tabs').forEach(function (box) {
                var blocks = Array.prototype.slice.call(box.querySelectorAll(':scope > pre'));
                if (blocks.length < 2) { return; }
                var bar = document.createElement('div');
                bar.className = 'tabs';
                bar.setAttribute('role', 'tablist');
                var select = function (index) {
                    blocks.forEach(function (pre, i) {
                        pre.hidden = i !== index;
                        bar.children[i].setAttribute('aria-selected', i === index ? 'true' : 'false');
                        bar.children[i].tabIndex = i === index ? 0 : -1;
                    });
                };
                blocks.forEach(function (pre, i) {
                    var tab = document.createElement('button');
                    tab.type = 'button';
                    tab.setAttribute('role', 'tab');
                    tab.textContent = labels[language(pre)] || language(pre);
                    tab.addEventListener('click', function () {
                        try { localStorage.setItem('botzo-docs-lang', language(pre)); } catch (e) {}
                        groups.forEach(function (group) { group.choose(language(pre)); });
                    });
                    bar.appendChild(tab);
                });
                box.insertBefore(bar, box.firstChild);
                var group = { choose: function (lang) {
                    var index = blocks.findIndex(function (pre) { return language(pre) === lang; });
                    if (index !== -1) { select(index); }
                } };
                groups.push(group);
                select(0);
                if (preferred) { group.choose(preferred); }
            });
        })();
    </script>
</body>
</html>
