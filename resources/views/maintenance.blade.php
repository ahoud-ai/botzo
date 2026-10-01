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

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --surface: #0d1420;
            --surface-soft: #121a29;
            --border: rgba(255, 255, 255, 0.08);
            --text: #f1f5f9;
            --muted: #94a3b8;
            --accent-1: #0ea5e9;
            --accent-2: #6366f1;
            --accent-3: #f43f5e;
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
            background: var(--surface);
            background-image:
                radial-gradient(60rem 30rem at 15% -10%, rgba(14, 165, 233, 0.16), transparent 60%),
                radial-gradient(50rem 26rem at 110% 10%, rgba(244, 63, 94, 0.14), transparent 55%);
            color: var(--text);
            font-family: {{ $isRtl ? "'Cairo', 'Outfit'" : "'Outfit', 'Cairo'" }}, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding: 24px;
        }

        .card {
            width: 100%;
            max-width: 460px;
            border: 1px solid var(--border);
            background: var(--surface-soft);
            border-radius: 20px;
            box-shadow: 0 24px 60px -24px rgba(0, 0, 0, 0.6);
            overflow: hidden;
            text-align: center;
        }

        .bar {
            height: 6px;
            background: linear-gradient(90deg, var(--accent-1), var(--accent-2), var(--accent-3));
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
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.16), rgba(99, 102, 241, 0.16));
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .icon-ring svg {
            width: 32px;
            height: 32px;
            stroke: var(--accent-1);
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
            background: rgba(255, 255, 255, 0.03);
            font-size: 0.82rem;
            color: var(--muted);
        }

        .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--accent-1);
            box-shadow: 0 0 0 0 rgba(14, 165, 233, 0.6);
            animation: pulse 1.8s ease-out infinite;
        }

        @media (prefers-reduced-motion: reduce) {
            .dot {
                animation: none;
            }
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(14, 165, 233, 0.5);
            }
            70% {
                box-shadow: 0 0 0 8px rgba(14, 165, 233, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(14, 165, 233, 0);
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
