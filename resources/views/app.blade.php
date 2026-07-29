<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @if(app()->environment('production'))
            <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
        @endif
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <script>
            // Applies the saved theme before first paint so the splash screen
            // below (and the rest of the page) never flashes the wrong theme
            // while waiting for the Vue bundle to boot and call initTheme().
            (function () {
                try {
                    if (localStorage.getItem('theme') !== 'light') {
                        document.documentElement.classList.add('dark');
                    }
                } catch (e) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>
        @php
            $configData = (isset($page['props']) && isset($page['props']['config'])) ? $page['props']['config'] : [];
            $config = collect(is_array($configData) ? $configData : []);

            $configValue = function (string $key, $default = null) use ($config) {
                $item = $config->firstWhere('key', $key);

                if (is_array($item)) {
                    return $item['value'] ?? $default;
                }

                if (is_object($item)) {
                    return $item->value ?? $default;
                }

                return $default;
            };

            $booleanConfigValue = function (string $key, bool $default = false) use ($configValue) {
                $value = $configValue($key, null);
                if ($value === null || $value === '') {
                    return $default;
                }

                return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
            };

            $currentLanguage = strtolower((string) ($page['props']['currentLanguage'] ?? app()->getLocale() ?? 'en'));
            $isArabic = str_starts_with($currentLanguage, 'ar');
            $requestPath = trim(request()->path(), '/');
            if ($requestPath === '/') {
                $requestPath = '';
            }
            $seoPayload = data_get($page, 'props.seo', []);
            if (! is_array($seoPayload)) {
                $seoPayload = [];
            }

            $seoPublicPaths = collect(config('frontend.seo_public_paths', []))
                ->map(fn ($path) => trim((string) $path, '/'))
                ->unique()
                ->values();
            $seoPublicPathPrefixes = collect(config('frontend.seo_public_path_prefixes', ['pages/']))
                ->map(fn ($path) => trim((string) $path, '/').'/')
                ->filter(fn ($path) => $path !== '/')
                ->unique()
                ->values();

            $isDynamicFrontendPage = $seoPublicPathPrefixes->contains(fn ($prefix) => str_starts_with($requestPath, $prefix));
            $isPublicFrontendPage = $seoPublicPaths->contains($requestPath) || $isDynamicFrontendPage;

            $localizedConfigValue = function (string $arabicKey, string $englishKey, string $fallback = '') use ($configValue, $isArabic) {
                $arabicValue = trim((string) $configValue($arabicKey, ''));
                $englishValue = trim((string) $configValue($englishKey, ''));

                if ($isArabic) {
                    return $arabicValue !== '' ? $arabicValue : ($englishValue !== '' ? $englishValue : $fallback);
                }

                return $englishValue !== '' ? $englishValue : ($arabicValue !== '' ? $arabicValue : $fallback);
            };

            $strictLocalizedConfigValue = function (string $arabicKey, string $englishKey, string $fallback = '') use ($configValue, $isArabic) {
                $arabicValue = trim((string) $configValue($arabicKey, ''));
                $englishValue = trim((string) $configValue($englishKey, ''));

                if ($isArabic) {
                    return $arabicValue !== '' ? $arabicValue : $fallback;
                }

                return $englishValue !== '' ? $englishValue : $fallback;
            };

            $companyName = trim((string) $configValue('company_name', config('app.name', 'Botzo')));
            if ($companyName === '') {
                $companyName = (string) config('app.name', 'Botzo');
            }

            $siteName = $localizedConfigValue('seo_site_name_ar', 'seo_site_name_en', $companyName);
            $defaultTitle = $localizedConfigValue('seo_default_title_ar', 'seo_default_title_en', $siteName);
            $homeTitle = $strictLocalizedConfigValue('seo_home_title_ar', 'seo_home_title_en', '');

            $defaultDescription = $localizedConfigValue('seo_default_description_ar', 'seo_default_description_en', '');
            $homeDescription = $localizedConfigValue('seo_home_description_ar', 'seo_home_description_en', $defaultDescription);

            $dynamicPageName = trim((string) data_get($page, 'props.page.display_name', ''));
            if ($dynamicPageName === '') {
                $dynamicPageName = trim((string) data_get($page, 'props.page.name', ''));
            }

            $dynamicPageDescription = trim(strip_tags((string) data_get($page, 'props.page.display_content', '')));
            if ($dynamicPageDescription === '') {
                $dynamicPageDescription = trim(strip_tags((string) data_get($page, 'props.page.content', '')));
            }

            if ($requestPath === '') {
                $computedTitle = $homeTitle;
            } elseif ($isDynamicFrontendPage && $dynamicPageName !== '') {
                $computedTitle = $dynamicPageName.' | '.$siteName;
            } else {
                $computedTitle = $defaultTitle !== '' ? $defaultTitle : $siteName;
            }

            if ($requestPath === '') {
                $computedDescription = $homeDescription !== '' ? $homeDescription : $defaultDescription;
            } elseif ($isDynamicFrontendPage && $dynamicPageDescription !== '') {
                $computedDescription = $dynamicPageDescription;
            } else {
                $computedDescription = $defaultDescription !== '' ? $defaultDescription : $homeDescription;
            }

            $payloadTitle = trim((string) data_get($seoPayload, 'title', ''));
            if ($payloadTitle !== '') {
                $computedTitle = $payloadTitle;
            }

            $payloadDescription = trim(strip_tags((string) data_get($seoPayload, 'description', '')));
            if ($payloadDescription !== '') {
                $computedDescription = $payloadDescription;
            }

            $computedDescription = trim(\Illuminate\Support\Str::limit((string) $computedDescription, 320, ''));
            $computedKeywords = trim((string) $localizedConfigValue('seo_keywords_ar', 'seo_keywords_en', ''));

            $canonicalBaseUrl = trim((string) $configValue('seo_canonical_base_url', config('app.url', url('/'))));
            if ($canonicalBaseUrl === '') {
                $canonicalBaseUrl = rtrim((string) config('app.url', url('/')), '/');
            }
            $canonicalBaseUrl = rtrim($canonicalBaseUrl, '/');
            $canonicalUrl = $canonicalBaseUrl;
            if ($requestPath !== '') {
                $canonicalUrl .= '/'.$requestPath;
            }
            $payloadCanonicalUrl = trim((string) data_get($seoPayload, 'canonical_url', ''));
            if ($payloadCanonicalUrl !== '' && filter_var($payloadCanonicalUrl, FILTER_VALIDATE_URL)) {
                $canonicalUrl = $payloadCanonicalUrl;
            }

            $ogTitle = $payloadTitle !== '' ? $payloadTitle : $localizedConfigValue('seo_og_title_ar', 'seo_og_title_en', $computedTitle);
            $ogDescription = $payloadDescription !== '' ? $payloadDescription : $localizedConfigValue('seo_og_description_ar', 'seo_og_description_en', $computedDescription);
            $ogDescription = trim(\Illuminate\Support\Str::limit((string) $ogDescription, 320, ''));
            $ogLocale = $isArabic ? 'ar_AR' : 'en_US';
            $ogType = trim((string) data_get($seoPayload, 'type', 'website'));
            if (! in_array($ogType, ['website', 'article'], true)) {
                $ogType = 'website';
            }

            $seoShareImage = trim((string) $configValue('seo_share_image', ''));
            $logoPath = trim((string) $configValue('logo', ''));
            $shareImageUrl = $seoShareImage !== ''
                ? url('/media/'.$seoShareImage)
                : ($logoPath !== '' ? url('/media/'.$logoPath) : url('/images/favicon.png'));
            $payloadShareImage = trim((string) data_get($seoPayload, 'image_url', ''));
            if ($payloadShareImage !== '') {
                $shareImageUrl = \Illuminate\Support\Str::startsWith($payloadShareImage, ['http://', 'https://'])
                    ? $payloadShareImage
                    : url($payloadShareImage);
            }
            $shareImageWidth = (int) data_get($seoPayload, 'image_width', 0);
            $shareImageHeight = (int) data_get($seoPayload, 'image_height', 0);

            $twitterCard = trim((string) $configValue('seo_twitter_card', 'summary_large_image'));
            if (! in_array($twitterCard, ['summary', 'summary_large_image'], true)) {
                $twitterCard = 'summary_large_image';
            }

            $twitterSite = trim((string) $configValue('seo_twitter_site', ''));
            if ($twitterSite !== '' && ! str_starts_with($twitterSite, '@')) {
                $twitterSite = '@'.$twitterSite;
            }

            $seoRobotsIndex = $booleanConfigValue('seo_robots_index', true);
            $seoRobotsFollow = $booleanConfigValue('seo_robots_follow', true);
            $robotsMetaContent = $isPublicFrontendPage
                ? (($seoRobotsIndex ? 'index' : 'noindex').','.($seoRobotsFollow ? 'follow' : 'nofollow'))
                : 'noindex,nofollow';
            $payloadRobots = trim((string) data_get($seoPayload, 'robots', ''));
            if ($payloadRobots !== '' && preg_match('/^[a-z,\-\s]+$/i', $payloadRobots)) {
                $robotsMetaContent = $payloadRobots;
            }

            $googleVerification = trim((string) $configValue('seo_google_verification', ''));
            $bingVerification = trim((string) $configValue('seo_bing_verification', ''));

            $metaPixelId = trim((string) $configValue('tracking_meta_pixel_id', ''));
            $tiktokPixelId = trim((string) $configValue('tracking_tiktok_pixel_id', ''));
            if ($metaPixelId !== '' && ! preg_match('/^[0-9]{5,32}$/', $metaPixelId)) {
                $metaPixelId = '';
            }
            if ($tiktokPixelId !== '' && ! preg_match('/^[A-Z0-9]{10,64}$/i', $tiktokPixelId)) {
                $tiktokPixelId = '';
            }

            $schemaPayload = data_get($seoPayload, 'schema', []);
            if (is_array($schemaPayload) && isset($schemaPayload['@context'])) {
                $schemaPayload = [$schemaPayload];
            }
            $schemaPayload = is_array($schemaPayload) ? array_values(array_filter($schemaPayload, 'is_array')) : [];
            $articlePublishedTime = trim((string) data_get($seoPayload, 'published_time', ''));
            $articleModifiedTime = trim((string) data_get($seoPayload, 'modified_time', ''));
            $articleAuthorName = trim((string) data_get($seoPayload, 'author_name', ''));

            $faviconPath = trim((string) $configValue('favicon', ''));
            $faviconUrl = $faviconPath !== '' ? url('/media/'.$faviconPath) : url('/images/favicon.png');

            $head_scripts = $configValue('head_scripts');
            $head_styles = $configValue('head_styles');
            $meta_tags = $configValue('meta_tags');
            $body_scripts = $configValue('body_scripts');

            $primary_color = trim((string) $configValue('primary_color', '#034737'));
            if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $primary_color)) {
                $primary_color = '#034737';
            }

            $secondary_color = trim((string) $configValue('secondary_color', '#008000'));
            if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $secondary_color)) {
                $secondary_color = '#008000';
            }
        @endphp

        <link rel="icon" href="{{ $faviconUrl }}">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
        @if($isPublicFrontendPage)
            <title>{{ $computedTitle }}</title>
            @if($computedDescription !== '')
                <meta name="description" content="{{ $computedDescription }}">
            @endif
            @if($computedKeywords !== '')
                <meta name="keywords" content="{{ $computedKeywords }}">
            @endif

            <meta name="robots" content="{{ $robotsMetaContent }}">
            <link rel="canonical" href="{{ $canonicalUrl }}">

            <meta property="og:type" content="{{ $ogType }}">
            <meta property="og:locale" content="{{ $ogLocale }}">
            <meta property="og:site_name" content="{{ $siteName }}">
            @if($ogTitle !== '')
                <meta property="og:title" content="{{ $ogTitle }}">
            @endif
            @if($ogDescription !== '')
                <meta property="og:description" content="{{ $ogDescription }}">
            @endif
            <meta property="og:url" content="{{ $canonicalUrl }}">
            <meta property="og:image" content="{{ $shareImageUrl }}">
            @if($shareImageWidth > 0)
                <meta property="og:image:width" content="{{ $shareImageWidth }}">
            @endif
            @if($shareImageHeight > 0)
                <meta property="og:image:height" content="{{ $shareImageHeight }}">
            @endif
            @if($ogType === 'article')
                @if($articlePublishedTime !== '')
                    <meta property="article:published_time" content="{{ $articlePublishedTime }}">
                @endif
                @if($articleModifiedTime !== '')
                    <meta property="article:modified_time" content="{{ $articleModifiedTime }}">
                @endif
                @if($articleAuthorName !== '')
                    <meta property="article:author" content="{{ $articleAuthorName }}">
                @endif
            @endif

            <meta name="twitter:card" content="{{ $twitterCard }}">
            @if($ogTitle !== '')
                <meta name="twitter:title" content="{{ $ogTitle }}">
            @endif
            @if($ogDescription !== '')
                <meta name="twitter:description" content="{{ $ogDescription }}">
            @endif
            <meta name="twitter:image" content="{{ $shareImageUrl }}">
            @if($twitterSite !== '')
                <meta name="twitter:site" content="{{ $twitterSite }}">
            @endif

            @if($googleVerification !== '')
                <meta name="google-site-verification" content="{{ $googleVerification }}">
            @endif
            @if($bingVerification !== '')
                <meta name="msvalidate.01" content="{{ $bingVerification }}">
            @endif

            @foreach($schemaPayload as $schema)
                <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
            @endforeach
        @endif

        @vite(['resources/js/app.js', 'resources/css/app.css'])
        @inertiaHead

        @if ($isPublicFrontendPage && $metaPixelId !== '')
        <script>
            !function(f,b,e,v,n,t,s)
            {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};
            if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
            n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t,s)}(window, document,'script',
            'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', '{{ $metaPixelId }}');
            fbq('track', 'PageView');
        </script>
        @endif

        @if ($isPublicFrontendPage && $tiktokPixelId !== '')
        <script>
            !function (w, d, t) {
                w.TiktokAnalyticsObject = t;
                var ttq = w[t] = w[t] || [];
                ttq.methods = ['page', 'track', 'identify', 'instances', 'debug', 'on', 'off', 'once', 'ready', 'alias', 'group', 'enableCookie', 'disableCookie'];
                ttq.setAndDefer = function (t, e) { t[e] = function () { t.push([e].concat(Array.prototype.slice.call(arguments, 0))); }; };
                for (var i = 0; i < ttq.methods.length; i++) ttq.setAndDefer(ttq, ttq.methods[i]);
                ttq.load = function (e, n) {
                    var r = 'https://analytics.tiktok.com/i18n/pixel/events.js';
                    ttq._i = ttq._i || {};
                    ttq._i[e] = [];
                    ttq._i[e]._u = r;
                    ttq._t = ttq._t || {};
                    ttq._t[e] = +new Date();
                    ttq._o = ttq._o || {};
                    ttq._o[e] = n || {};
                    var o = document.createElement('script');
                    o.type = 'text/javascript';
                    o.async = true;
                    o.src = r + '?sdkid=' + e + '&lib=' + t;
                    var a = document.getElementsByTagName('script')[0];
                    a.parentNode.insertBefore(o, a);
                };
                ttq.load('{{ $tiktokPixelId }}');
                ttq.page();
            }(window, document, 'ttq');
        </script>
        @endif

        @if ($isPublicFrontendPage && !empty($meta_tags))
            {!! $meta_tags !!}
        @endif

        <!-- Dynamic Color Scheme -->
        <style>
            :root {
                --color-primary: {{ $primary_color }};
                --color-secondary: {{ $secondary_color }};
            }

            html,
            body,
            button,
            input,
            textarea,
            select {
                font-family: "IBM Plex Sans Arabic", "Ping AR LT", "Tajawal", Outfit, "Segoe UI", Tahoma, Helvetica, Arial, sans-serif !important;
            }
        </style>

        @if ($isPublicFrontendPage && !empty($head_styles))
        <!-- Custom Head Styles -->
        @if(str_contains($head_styles, '<style>'))
            {!! $head_styles !!}
        @else
            <style>
                {!! $head_styles !!}
            </style>
        @endif
        @endif

        @if ($isPublicFrontendPage && !empty($head_scripts))
        <!-- Custom Head Scripts -->
        @if(str_contains($head_scripts, '<script>'))
            {!! $head_scripts !!}
        @else
            <script>
                {!! $head_scripts !!}
            </script>
        @endif
        @endif
    </head>
    <body>
        @if ($isPublicFrontendPage && $metaPixelId !== '')
        <noscript>
            <img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={{ $metaPixelId }}&ev=PageView&noscript=1"/>
        </noscript>
        @endif

        @if ($isPublicFrontendPage)
        <div id="botzo-splash" aria-hidden="true">
            <div class="botzo-splash__glow"></div>
            <div class="botzo-splash__mark">
                <img src="/images/nav/nav-icon-dark.svg" alt="" class="botzo-splash__icon botzo-splash__icon--dark">
                <img src="/images/nav/nav-icon-light.svg" alt="" class="botzo-splash__icon botzo-splash__icon--light">
            </div>
            <img src="/images/nav/nav-wordmark-dark.svg" alt="Botzo" class="botzo-splash__word botzo-splash__word--dark">
            <img src="/images/nav/nav-wordmark-light.svg" alt="Botzo" class="botzo-splash__word botzo-splash__word--light">
        </div>
        <style>
            #botzo-splash {
                position: fixed;
                inset: 0;
                z-index: 999999;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 18px;
                background: #ffffff;
                transition: opacity 420ms cubic-bezier(0.16, 1, 0.3, 1);
            }

            html.dark #botzo-splash {
                background: #0a0f17;
            }

            #botzo-splash.botzo-splash--hide {
                opacity: 0;
                pointer-events: none;
            }

            .botzo-splash__glow {
                position: absolute;
                width: 220px;
                height: 220px;
                border-radius: 999px;
                background: radial-gradient(circle, rgba(37, 211, 102, 0.22), rgba(59, 130, 246, 0.1) 55%, transparent 72%);
                animation: botzo-splash-pulse 2.2s ease-in-out infinite;
            }

            .botzo-splash__mark {
                position: relative;
                width: 72px;
                height: 66px;
                animation: botzo-splash-icon-in 700ms cubic-bezier(0.16, 1, 0.3, 1) both;
            }

            .botzo-splash__icon {
                position: absolute;
                inset: 0;
                width: 100%;
                height: 100%;
            }

            .botzo-splash__icon--dark { display: none; }
            html.dark .botzo-splash__icon--dark { display: block; }
            html.dark .botzo-splash__icon--light { display: none; }

            .botzo-splash__word {
                position: relative;
                width: 128px;
                height: auto;
                animation: botzo-splash-word-in 700ms cubic-bezier(0.16, 1, 0.3, 1) 200ms both;
            }

            .botzo-splash__word--dark { display: none; }
            html.dark .botzo-splash__word--dark { display: block; }
            html.dark .botzo-splash__word--light { display: none; }

            @keyframes botzo-splash-icon-in {
                from { opacity: 0; transform: scale(0.82); }
                to { opacity: 1; transform: scale(1); }
            }

            @keyframes botzo-splash-word-in {
                from { opacity: 0; transform: translateY(6px); }
                to { opacity: 1; transform: translateY(0); }
            }

            @keyframes botzo-splash-pulse {
                0%, 100% { transform: scale(1); opacity: 0.6; }
                50% { transform: scale(1.12); opacity: 1; }
            }

            @media (prefers-reduced-motion: reduce) {
                .botzo-splash__glow,
                .botzo-splash__mark,
                .botzo-splash__word {
                    animation: none;
                    opacity: 1;
                    transform: none;
                }
            }
        </style>
        <script>
            (function () {
                var splash = document.getElementById('botzo-splash');
                if (!splash) return;

                var SESSION_KEY = 'botzo_splash_shown';
                var alreadyShown;
                try {
                    alreadyShown = sessionStorage.getItem(SESSION_KEY) === '1';
                } catch (e) {
                    alreadyShown = false;
                }

                if (alreadyShown) {
                    splash.style.display = 'none';
                    return;
                }

                try {
                    sessionStorage.setItem(SESSION_KEY, '1');
                } catch (e) {
                    // Private browsing / storage disabled: still show once, just won't persist across tabs.
                }

                var MIN_VISIBLE_MS = 900;
                var MAX_VISIBLE_MS = 4000;
                var start = Date.now();
                var hidden = false;

                function hide() {
                    if (hidden) return;
                    hidden = true;
                    splash.classList.add('botzo-splash--hide');
                    window.setTimeout(function () {
                        splash.style.display = 'none';
                    }, 450);
                }

                function attemptHide() {
                    var elapsed = Date.now() - start;
                    window.setTimeout(hide, Math.max(0, MIN_VISIBLE_MS - elapsed));
                }

                if (document.readyState === 'complete') {
                    attemptHide();
                } else {
                    window.addEventListener('load', attemptHide);
                }
                window.setTimeout(hide, MAX_VISIBLE_MS);
            })();
        </script>
        @endif

        @inertia

        @if ($isPublicFrontendPage && !empty($body_scripts))
        <!-- Custom Body Scripts -->
        @if(str_contains($body_scripts, '<script>'))
            {!! $body_scripts !!}
        @else
            <script>
                {!! $body_scripts !!}
            </script>
        @endif
        @endif
    </body>
</html>
