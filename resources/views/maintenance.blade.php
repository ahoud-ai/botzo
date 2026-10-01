<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="60">
    <meta name="robots" content="noindex, follow">

    <title>{{ __('Under maintenance') }} — {{ $companyName }}</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0d1420;
            color: #e2e8f0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            text-align: center;
            padding: 24px;
        }

        .card {
            max-width: 480px;
        }

        .logo {
            max-height: 48px;
            margin-bottom: 24px;
        }

        .icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 0 12px;
            color: #fff;
        }

        p {
            font-size: 1rem;
            line-height: 1.6;
            color: #94a3b8;
            margin: 0;
        }
    </style>
</head>

<body>
    <div class="card">
        @if ($logo)
            <img class="logo" src="{{ url('/media/' . $logo) }}" alt="{{ $companyName }}">
        @endif
        <div class="icon">🛠️</div>
        <h1>{{ __('We\'ll be back soon') }}</h1>
        <p>{{ __(':company is currently undergoing scheduled maintenance. Please check back shortly.', ['company' => $companyName]) }}</p>
    </div>
</body>

</html>
