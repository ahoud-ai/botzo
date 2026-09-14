<!DOCTYPE html>
<html dir="{{ $dir }}" lang="{{ $dir === 'rtl' ? 'ar' : 'en' }}">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 17mm 14mm 13mm 14mm;
        }

        .pdf-header-logo {
            position: fixed;
            top: -13mm;
            {{ $dir === 'rtl' ? 'right' : 'left' }}: 0;
            width: 12mm;
            height: 11mm;
        }

        body {
            font-family: 'IBM Plex Sans Arabic', sans-serif;
            direction: {{ $dir }};
            text-align: {{ $dir === 'rtl' ? 'right' : 'left' }};
            color: #1a2332;
            font-size: 11px;
            line-height: 1.5;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .muted { color: #8899aa; }
        .body-text { color: #445566; }
        .brand { color: #1db954; }

        .header-badge { font-size: 10px; color: #8899aa; }

        .offer-box {
            background-color: #e5f8ec;
            border-radius: 14px;
            padding: 13px;
            margin-top: 10px;
        }
        .offer-title { font-size: 19px; font-weight: 700; color: #000; margin: 0 0 5px 0; }
        .offer-desc { font-size: 11px; color: #445566; margin: 0; white-space: pre-line; }

        .price-box {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 9px 14px;
            margin-top: 9px;
        }
        .price-value { font-size: 26px; font-weight: 700; color: #1db954; margin: 2px 0; }

        .info-box {
            border: 1px solid #cfd8e3;
            border-radius: 10px;
            padding: 10px;
        }

        .chips-table td { padding: 0 4px; }
        .chips-table td:first-child { padding-right: 0; }
        .chips-table td:last-child { padding-left: 0; }

        .cols-table td { vertical-align: top; padding: 0 6px; }
        .cols-table td:first-child { padding-right: 0; }
        .cols-table td:last-child { padding-left: 0; }

        ul { margin: 6px 0 0 0; padding-inline-start: 16px; }
        li { margin-bottom: 4px; font-size: 10.5px; color: #445566; white-space: pre-line; }

        .about-box {
            border: 1px solid #cfd8e3;
            border-right: 3px solid #25d366;
            background-color: #f2fbf5;
            border-radius: 10px;
            padding: 10px;
            margin-top: 10px;
        }

        .footer-row {
            border-top: 1px solid #cfd8e3;
            padding-top: 8px;
            margin-top: 10px;
            font-size: 10px;
            color: #445566;
        }
        .footer-row td { text-align: center; }

        .agreement-title { font-size: 18px; font-weight: 700; color: #000; margin: 0 0 4px 0; }
        .agreement-intro { font-size: 10.5px; color: #8899aa; margin: 0 0 14px 0; white-space: pre-line; }

        .article {
            border: 1px solid #cfd8e3;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 10px;
        }
        .article-table td { vertical-align: top; }
        .article-number {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background-color: #25d366;
            color: #04130a;
            text-align: center;
            font-size: 10px;
            font-weight: 700;
            line-height: 22px;
        }
        .article-title { font-size: 12px; font-weight: 700; color: #000; margin: 0 0 4px 0; }
        .article-body { font-size: 10.5px; color: #445566; margin: 0 0 3px 0; white-space: pre-line; }

        .notice-box {
            border: 1px solid #f0c419;
            background-color: #fdf6e3;
            border-radius: 10px;
            padding: 12px;
            margin-top: 4px;
        }
        .notice-title { font-size: 12px; font-weight: 700; color: #000; margin: 0 0 4px 0; }
        .notice-body { font-size: 10.5px; color: #6b5a1a; margin: 0; white-space: pre-line; }

        .official-box {
            background-color: #1db954;
            color: #ffffff;
            border-radius: 10px;
            padding: 14px;
            margin-top: 14px;
        }
        .official-label { font-size: 9.5px; opacity: 0.8; }
        .official-name { font-size: 14px; font-weight: 700; margin: 2px 0 8px 0; }
        .official-table td { padding: 0 6px; vertical-align: top; }
        .official-table td:first-child { padding-right: 0; }
        .official-table td:last-child { padding-left: 0; }
        .official-value { font-size: 10.5px; font-weight: 700; }

        .sign-table td { padding: 0 5px; vertical-align: top; }
        .sign-table td:first-child { padding-right: 0; }
        .sign-table td:last-child { padding-left: 0; }
        .sign-box {
            border: 1px solid #cfd8e3;
            border-radius: 10px;
            padding: 10px;
            margin-top: 14px;
        }
        .sign-line {
            border-bottom: 1px solid #cfd8e3;
            height: 22px;
            margin-top: 14px;
        }

        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <img src="{{ public_path('images/pdf/botzo-logo-lockup.png') }}" class="pdf-header-logo" alt="Botzo">

    <!-- Page 1: offer overview -->
    <p class="header-badge" style="margin: 0;">{{ $headerBadge }}</p>

    <div class="offer-box">
        <p class="offer-title">{{ $offerTitle }}</p>
        <p class="offer-desc">{{ $offerDesc }}</p>
        <div class="price-box">
            <div class="muted" style="font-size: 10px;">{{ $priceLabel }}</div>
            <div class="price-value">{{ $priceValue }}</div>
            <div class="muted" style="font-size: 10px;">{{ $priceNote }}</div>
        </div>
    </div>

    <div class="info-box" style="margin-top: 9px;">
        <div class="brand" style="font-size: 10px; font-weight: 700;">{{ $accreditationBadge }}</div>
        <div style="font-size: 12px; font-weight: 700; margin-top: 2px;">{{ $accreditationTitle }}</div>
        <div class="body-text" style="font-size: 10.5px; margin-top: 2px;">{{ $accreditationDesc }}</div>
    </div>

    <table class="chips-table" style="margin-top: 9px;">
        <tr>
            @foreach ($chips as $chip)
                <td style="width: 33.33%;">
                    <div class="info-box">
                        <div class="muted" style="font-size: 9.5px;">{{ $chip['label'] }}</div>
                        <div style="font-size: 11px; font-weight: 700; margin-top: 2px;">{{ $chip['value'] }}</div>
                    </div>
                </td>
            @endforeach
        </tr>
    </table>

    <table class="cols-table" style="margin-top: 9px;">
        <tr>
            @foreach ($cols as $col)
                <td style="width: 50%;">
                    <div class="info-box">
                        <div style="font-size: 12px; font-weight: 700;">{{ $col['title'] }}</div>
                        <ul>
                            @foreach ($col['items'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    </div>
                </td>
            @endforeach
        </tr>
    </table>

    <div class="about-box">
        <div style="font-size: 12px; font-weight: 700;">{{ $aboutTitle }}</div>
        <div class="body-text" style="font-size: 10.5px; margin-top: 4px;">
            {{ $aboutDesc }}
        </div>
    </div>

    <table class="footer-row">
        <tr>
            @foreach ($footerContacts as $contact)
                <td>{{ $contact }}</td>
            @endforeach
        </tr>
    </table>

    <!-- Agreement: full numbered articles -->
    <div class="page-break"></div>

    <div class="brand" style="font-size: 10px; font-weight: 700;">{{ $agreementBadge }}</div>
    <p class="agreement-title">{{ $agreementTitle }}</p>
    <p class="agreement-intro">{{ $agreementIntro }}</p>

    @foreach ($articles as $article)
        <div class="article">
            <table class="article-table">
                <tr>
                    @if ($articleBadgeFirst)
                        <td style="width: 26px; padding-right: 10px;">
                            <div class="article-number">{{ $article['number'] }}</div>
                        </td>
                    @endif
                    <td>
                        <p class="article-title">{{ $article['heading'] }}</p>
                        @foreach ($article['body'] as $line)
                            <p class="article-body">{{ $line }}</p>
                        @endforeach
                    </td>
                    @if (! $articleBadgeFirst)
                        <td style="width: 26px; padding-left: 10px;">
                            <div class="article-number">{{ $article['number'] }}</div>
                        </td>
                    @endif
                </tr>
            </table>
        </div>
    @endforeach

    <div class="notice-box">
        <p class="notice-title">{{ $noticeTitle }}</p>
        <p class="notice-body">{{ $noticeBody }}</p>
    </div>

    <div class="official-box">
        <div class="official-label">{{ $officialLabel }}</div>
        <div class="official-name">{{ $officialName }}</div>
        <table class="official-table">
            <tr>
                @foreach ($officialFields as $field)
                    <td style="width: 33.33%;">
                        <div class="official-label">{{ $field['label'] }}</div>
                        <div class="official-value">{{ $field['value'] }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    </div>

    <table class="sign-table">
        <tr>
            @foreach ($signFields as $field)
                <td style="width: 33.33%;">
                    <div class="sign-box">
                        <div class="muted" style="font-size: 9.5px;">{{ $field['label'] }}</div>
                        <div class="sign-line"></div>
                    </div>
                </td>
            @endforeach
        </tr>
    </table>
</body>
</html>
