<!doctype html>
<html lang="{{ $lang }}" dir="{{ $lang === 'en' ? 'ltr' : 'rtl' }}" data-theme="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Botzo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <main id="panel" aria-live="polite"></main>
    <script src="{{ $script }}" defer></script>
</body>
</html>
