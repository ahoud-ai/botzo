<?php
    $locale = app()->getLocale();
    $isRtl = in_array($locale, ['ar', 'he', 'fa', 'ur'], true);
?>
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="60">
    <meta name="robots" content="noindex, follow">
    <meta name="color-scheme" content="dark">

    <title>{{ __('Under maintenance') }} — {{ $companyName }}</title>

    <link rel="icon" href="{{ $faviconUrl }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg: #0a0f17;
            --surface: #0d1420;
            --border: #1a2332;
            --border-strong: #28374a;
            --text: #ffffff;
            --muted: #94a3b8;
            --primary: {{ $primaryColor }};
            --secondary: {{ $secondaryColor }};
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg);
            background-image:
                radial-gradient(60rem 30rem at 15% -10%, color-mix(in srgb, var(--primary) 16%, transparent), transparent 60%),
                radial-gradient(50rem 26rem at 110% 10%, color-mix(in srgb, var(--secondary) 14%, transparent), transparent 55%);
            color: var(--text);
            font-family: {{ $isRtl ? "'Cairo', 'Outfit'" : "'Outfit', 'Cairo'" }}, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding: 24px;
        }

        .card {
            width: 100%;
            max-width: 460px;
            border: 1px solid var(--border);
            background: var(--surface);
            border-radius: 20px;
            box-shadow: 0 24px 60px -24px rgba(0, 0, 0, 0.7);
            overflow: hidden;
            text-align: center;
        }

        .bar {
            height: 6px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .content {
            padding: 44px 36px 40px;
        }

        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 28px;
        }

        .brand img {
            height: 28px;
            width: auto;
        }

        .brand span {
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 0.01em;
            color: var(--text);
        }

        .icon-ring {
            width: 72px;
            height: 72px;
            margin: 0 auto 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, color-mix(in srgb, var(--primary) 18%, transparent), color-mix(in srgb, var(--secondary) 18%, transparent));
            border: 1px solid var(--border-strong);
        }

        .icon-ring svg {
            width: 32px;
            height: 32px;
            stroke: var(--secondary);
        }

        h1 {
            font-size: 1.4rem;
            font-weight: 700;
            margin: 0 0 12px;
            color: #fff;
            text-wrap: balance;
        }

        p {
            font-size: 0.98rem;
            line-height: 1.65;
            color: var(--muted);
            margin: 0;
        }

        .status {
            margin-top: 26px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: color-mix(in srgb, var(--surface) 60%, var(--bg));
            font-size: 0.82rem;
            color: var(--muted);
        }

        .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--secondary);
            box-shadow: 0 0 0 0 color-mix(in srgb, var(--secondary) 60%, transparent);
            animation: pulse 1.8s ease-out infinite;
        }

        @media (prefers-reduced-motion: reduce) {
            .dot {
                animation: none;
            }
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 color-mix(in srgb, var(--secondary) 50%, transparent);
            }
            70% {
                box-shadow: 0 0 0 8px color-mix(in srgb, var(--secondary) 0%, transparent);
            }
            100% {
                box-shadow: 0 0 0 0 color-mix(in srgb, var(--secondary) 0%, transparent);
            }
        }

        @media (max-width: 420px) {
            .content {
                padding: 36px 24px 32px;
            }
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="bar"></div>
        <div class="content">
            <div class="brand">
                @if ($logo)
                    <img src="{{ url('/media/' . $logo) }}" alt="{{ $companyName }}">
                @endif
                <span>{{ $companyName }}</span>
            </div>

            <div class="icon-ring" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76Z"/>
                </svg>
            </div>

            <h1>{{ __('We\'ll be back soon') }}</h1>
            <p>{{ __(':company is currently undergoing scheduled maintenance. Please check back shortly.', ['company' => $companyName]) }}</p>

            <div class="status">
                <span class="dot"></span>
                {{ __('Maintenance in progress') }}
            </div>
        </div>
    </div>
</body>

</html>
