@php
    $locale = str_replace('_', '-', app()->getLocale());
    $documentDirection = str_starts_with(strtolower((string) app()->getLocale()), 'ar') ? 'rtl' : 'ltr';
    $pdfMode = !empty($pdfMode);
    $embeddedMode = !empty($embeddedMode);

    $vendor = $invoice['vendor'] ?? [];
    $customer = $invoice['customer'] ?? [];
    $subscription = $invoice['subscription'] ?? [];
    $payment = $invoice['payment'] ?? [];
    $summary = $invoice['summary'] ?? [];
    $items = $invoice['items'] ?? [];
    $taxLines = $invoice['tax_lines'] ?? [];

    $brandName = $vendor['company_name'] ?? $vendor['name'] ?? config('app.name', 'Botzo');
    $documentNumber = $invoice['invoice_number'] ?? __('Invoice');
    $issuedAt = $invoice['issued_at'] ?? __('Not set');
    $statusLabel = $invoice['status_label'] ?? __('Invoice');
    $paymentMethodLabel = $payment['method_label'] ?? __('Payment');
    $paymentReference = $payment['reference'] ?? __('Not set');
    $paidAt = $payment['paid_at'] ?? __('Not set');
    $billingPeriod = $subscription['period'] ?? __('Not set');
    $vendorPhones = !empty($vendor['phones']) ? implode(' / ', $vendor['phones']) : __('Not set');
    $customerEmail = $customer['email'] ?? __('Not set');
    $vendorTaxId = $vendor['tax_id'] ?? __('Not set');
    $vendorAddressLines = array_values(array_filter($vendor['address_lines'] ?? []));
    $customerAddressLines = array_values(array_filter($customer['address_lines'] ?? []));

    $resolvedLogoPath = $vendor['logo_path'] ?? public_path('images/logo.png');

    if ($pdfMode) {
        $pdfPreferredLogoPath = public_path('bimi/botzo-logo.svg');

        if (is_file($pdfPreferredLogoPath)) {
            $resolvedLogoPath = $pdfPreferredLogoPath;
        }
    }

    $logoExists = is_string($resolvedLogoPath) && $resolvedLogoPath !== '' && file_exists($resolvedLogoPath);
    $logoUrl = null;

    if ($logoExists) {
        $logoUrl = $pdfMode
            ? 'file:///' . str_replace('\\', '/', $resolvedLogoPath)
            : ($vendor['logo_url'] ?? url('/images/logo.png'));
    }

    $summaryRows = [
        ['label' => __('Subtotal'), 'value' => $summary['subtotal'] ?? '0.00'],
    ];

    if ($taxLines !== []) {
        foreach ($taxLines as $line) {
            $summaryRows[] = [
                'label' => $line['label'] ?? __('Tax'),
                'value' => $line['amount'] ?? '0.00',
            ];
        }
    } else {
        $summaryRows[] = ['label' => __('Tax'), 'value' => $summary['tax'] ?? '0.00'];
    }

    $summaryRows[] = ['label' => __('Total'), 'value' => $summary['total'] ?? '0.00', 'total' => true];
    $bodyFontFamily = $pdfMode
        ? ($documentDirection === 'rtl'
            ? "'tajawalpdf', 'botzoarabic', 'DejaVu Sans', sans-serif"
            : "'DejaVu Sans', sans-serif")
        : "'Tajawal', 'Segoe UI', Tahoma, Arial, sans-serif";
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $documentDirection }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title ?? __('Invoice') }} - {{ $documentNumber }}</title>
    <style>
        @font-face {
            font-family: 'Tajawal';
            src: url('{{ asset('fonts/Tajawal/Tajawal-Regular.ttf') }}') format('truetype');
            font-weight: 400;
            font-style: normal;
        }

        @font-face {
            font-family: 'Tajawal';
            src: url('{{ asset('fonts/Tajawal/Tajawal-Bold.ttf') }}') format('truetype');
            font-weight: 700;
            font-style: normal;
        }

        @page {
            margin: 16mm 14mm;
            size: A4;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            background: {{ $pdfMode ? '#ffffff' : 'linear-gradient(180deg, #eef4ff 0%, #f8fafc 38%, #f3f6fb 100%)' }};
            color: #0f172a;
        }

        body {
            padding: {{ $pdfMode ? '18px' : '26px' }};
            font-family: {!! $bodyFontFamily !!};
            font-size: 13.5px;
            line-height: 1.7;
        }

        body.embedded-mode {
            padding: 0;
        }

        .page-shell {
            max-width: 980px;
            margin: 0 auto;
        }

        .toolbar {
            margin-bottom: 20px;
            padding: 16px 18px;
            border: 1px solid rgba(191, 219, 254, 0.85);
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
            backdrop-filter: blur(14px);
        }

        .toolbar-table,
        .header-table,
        .header-facts-table,
        .party-table,
        .payment-table,
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: {{ $pdfMode ? 'auto' : 'fixed' }};
        }

        .toolbar-table td {
            vertical-align: middle;
        }

        .toolbar-title {
            font-size: 26px;
            font-weight: 700;
            margin: 0;
        }

        .toolbar-subtitle {
            color: #5b6b82;
            font-size: 12px;
            margin-top: 4px;
        }

        .toolbar-actions {
            text-align: {{ $documentDirection === 'rtl' ? 'left' : 'right' }};
        }

        .btn {
            display: inline-block;
            padding: 10px 16px;
            margin-{{ $documentDirection === 'rtl' ? 'left' : 'right' }}: 8px;
            border: 1px solid #cbd5e1;
            color: #334155;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            background: #ffffff;
            border-radius: 999px;
            box-shadow: 0 10px 26px rgba(148, 163, 184, 0.16);
            transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 32px rgba(148, 163, 184, 0.22);
        }

        .btn:last-child {
            margin-{{ $documentDirection === 'rtl' ? 'left' : 'right' }}: 0;
        }

        .btn-primary {
            border-color: #1d4ed8;
            background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%);
            color: #ffffff;
        }

        .btn-soft {
            border-color: #bfdbfe;
            background: linear-gradient(135deg, #eff6ff 0%, #f8fbff 100%);
            color: #1d4ed8;
        }

        .document {
            border: 1px solid #dbe7f3;
            background: #ffffff;
            border-radius: {{ $pdfMode ? '0' : '30px' }};
            overflow: hidden;
            box-shadow: {{ $pdfMode ? 'none' : '0 28px 70px rgba(15, 23, 42, 0.10)' }};
        }

        .document-accent {
            height: 6px;
            background: linear-gradient(90deg, #2563eb 0%, #38bdf8 55%, #67e8f9 100%);
        }

        .section {
            padding: 24px 26px;
            border-top: 1px solid #e6edf5;
            page-break-inside: avoid;
        }

        .section:first-child {
            border-top: none;
            background: linear-gradient(135deg, #f8fbff 0%, #ffffff 55%, #f5f9ff 100%);
        }

        .section-title {
            margin: 0 0 6px;
            font-size: 17px;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .section-subtitle {
            margin: 0 0 16px;
            color: #64748b;
            font-size: 12px;
        }

        .header-table td,
        .party-table td,
        .payment-table td,
        .summary-table td {
            vertical-align: top;
        }

        .header-left {
            width: 58%;
            padding-{{ $documentDirection === 'rtl' ? 'left' : 'right' }}: 16px;
        }

        .header-right {
            width: 42%;
        }

        .brand-row {
            width: 100%;
            border-collapse: collapse;
        }

        .brand-row td {
            vertical-align: top;
        }

        .brand-logo-cell {
            width: 74px;
        }

        .brand-logo {
            width: 60px;
            height: 60px;
            border: 1px solid #d7e7ff;
            text-align: center;
            vertical-align: middle;
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #f3f8ff 100%);
            box-shadow: 0 16px 32px rgba(59, 130, 246, 0.12);
        }

        .brand-logo img {
            max-width: 46px;
            max-height: 46px;
            margin-top: 5px;
        }

        .brand-name {
            color: #64748b;
            font-size: 11px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }

        .invoice-title {
            margin: 0 0 10px;
            font-size: 34px;
            font-weight: 700;
            letter-spacing: -0.03em;
        }

        .document-note {
            color: #475569;
            font-size: 13px;
            margin: 0;
            max-width: 520px;
        }

        .header-badge {
            display: inline-block;
            padding: 7px 12px;
            margin-bottom: 10px;
            border: 1px solid #dbeafe;
            border-radius: 999px;
            background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
            color: #2563eb;
            font-size: 11px;
            font-weight: 700;
        }

        .meta-box {
            border: 1px solid #dbe7f3;
            padding: 12px 14px;
            margin-bottom: 10px;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.05);
        }

        .meta-label,
        .field-label {
            color: #64748b;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .meta-value,
        .field-value {
            font-size: 14px;
            font-weight: 700;
        }

        .field-copy {
            font-size: 13px;
            font-weight: 500;
            color: #475569;
            line-height: 1.8;
        }

        .header-facts-table {
            margin-top: 12px;
        }

        .header-facts-table td {
            width: 33.33%;
            vertical-align: top;
        }

        .header-facts-cell-start {
            padding-{{ $documentDirection === 'rtl' ? 'left' : 'right' }}: 8px;
        }

        .header-facts-cell-middle {
            padding-left: 4px;
            padding-right: 4px;
        }

        .header-facts-cell-end {
            padding-{{ $documentDirection === 'rtl' ? 'right' : 'left' }}: 8px;
        }

        .hero-fact {
            border: 1px solid #dbe7f3;
            padding: 14px 15px;
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }

        .status-box {
            border: 1px solid #bbf7d0;
            background: linear-gradient(135deg, #ecfdf5 0%, #f4fff8 100%);
            color: #15803d;
        }

        .party-card,
        .payment-card,
        .summary-card {
            border: 1px solid #dbe7f3;
            padding: 18px;
            height: {{ $pdfMode ? 'auto' : '100%' }};
            border-radius: 22px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            box-shadow: {{ $pdfMode ? 'none' : '0 18px 40px rgba(15, 23, 42, 0.06)' }};
        }

        .party-left,
        .payment-left {
            padding-{{ $documentDirection === 'rtl' ? 'left' : 'right' }}: 8px;
        }

        .party-right,
        .payment-right {
            padding-{{ $documentDirection === 'rtl' ? 'right' : 'left' }}: 8px;
        }

        .field {
            margin-top: 14px;
        }

        .field:first-child {
            margin-top: 0;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #dbe7f3;
            padding: 13px 14px;
            vertical-align: top;
            text-align: {{ $documentDirection === 'rtl' ? 'right' : 'left' }};
        }

        .items-table th {
            background: linear-gradient(180deg, #eff6ff 0%, #f7fbff 100%);
            color: #334155;
            font-size: 11px;
            font-weight: 700;
        }

        .summary-row td {
            border-bottom: 1px solid #e8eef5;
            padding: 12px 0;
        }

        .summary-row:last-child td {
            border-bottom: none;
        }

        .summary-row.total td {
            padding-top: 16px;
            font-size: 16px;
            font-weight: 700;
            color: #1d4ed8;
        }

        .summary-value-cell {
            text-align: {{ $documentDirection === 'rtl' ? 'left' : 'right' }};
        }

        .ltr-value {
            direction: ltr;
            text-align: left;
            unicode-bidi: embed;
        }

        .footer {
            padding: 18px 26px 24px;
            border-top: 1px solid #e6edf5;
            color: #64748b;
            font-size: 12px;
            background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-end {
            text-align: {{ $documentDirection === 'rtl' ? 'left' : 'right' }};
        }

        @media print {
            body {
                padding: 0;
            }

            .toolbar {
                display: none !important;
            }

            .page-shell {
                max-width: none;
            }

            .document {
                border: none;
            }
        }
    </style>
</head>
<body class="{{ $embeddedMode ? 'embedded-mode' : '' }}" @if(!empty($printMode)) onload="window.print()" @endif>
    <div class="page-shell">
        @if(empty($printMode) && empty($downloadMode) && !$pdfMode && !$embeddedMode)
            <div class="toolbar">
                <table class="toolbar-table">
                    <tr>
                        <td>
                            <h1 class="toolbar-title">{{ __('Invoice') }}</h1>
                            <div class="toolbar-subtitle ltr-value">{{ $documentNumber }}</div>
                        </td>
                        <td class="toolbar-actions">
                            @if(!empty($backUrl))
                                <a href="{{ $backUrl }}" class="btn">{{ __('Back') }}</a>
                            @endif
                            @if(!empty($downloadUrl))
                                <a href="{{ $downloadUrl }}" class="btn btn-soft">{{ __('Download PDF') }}</a>
                            @endif
                            @if(!empty($printUrl))
                                <a href="{{ $printUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary">{{ __('Print') }}</a>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        @endif

        <div class="document">
            <div class="document-accent"></div>
            <div class="section">
                <table class="header-table">
                    <tr>
                        <td class="header-left">
                            <table class="brand-row">
                                <tr>
                                    @if(!empty($logoUrl))
                                        <td class="brand-logo-cell">
                                            <div class="brand-logo">
                                                <img src="{{ $logoUrl }}" alt="{{ $brandName }}">
                                            </div>
                                        </td>
                                    @endif
                                    <td>
                                        <div class="header-badge">{{ __('Official billing document') }}</div>
                                        <div class="brand-name">{{ $brandName }}</div>
                                        <h2 class="invoice-title">{{ __('Invoice') }}</h2>
                                        <p class="document-note">{{ __('A simplified invoice prepared for accounting review, printing, and PDF download.') }}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                        <td class="header-right">
                            <div class="meta-box status-box">
                                <div class="meta-label">{{ __('Status') }}</div>
                                <div class="meta-value">{{ $statusLabel }}</div>
                            </div>
                            <div class="meta-box">
                                <div class="meta-label">{{ __('Invoice no.') }}</div>
                                <div class="meta-value ltr-value">{{ $documentNumber }}</div>
                            </div>
                            <div class="meta-box">
                                <div class="meta-label">{{ __('Issued date') }}</div>
                                <div class="meta-value ltr-value">{{ $issuedAt }}</div>
                            </div>
                            <div class="meta-box">
                                <div class="meta-label">{{ __('Total') }}</div>
                                <div class="meta-value ltr-value">{{ $summary['total'] ?? '0.00' }}</div>
                            </div>
                        </td>
                    </tr>
                </table>

                <table class="header-facts-table">
                    <tr>
                        <td class="header-facts-cell-start">
                            <div class="hero-fact">
                                <div class="meta-label">{{ __('Subscription plan') }}</div>
                                <div class="meta-value">{{ $subscription['plan_name'] ?? __('Not set') }}</div>
                            </div>
                        </td>
                        <td class="header-facts-cell-middle">
                            <div class="hero-fact">
                                <div class="meta-label">{{ __('Billing period') }}</div>
                                <div class="meta-value">{{ $billingPeriod }}</div>
                            </div>
                        </td>
                        <td class="header-facts-cell-end">
                            <div class="hero-fact">
                                <div class="meta-label">{{ __('Payment method') }}</div>
                                <div class="meta-value">{{ $paymentMethodLabel }}</div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="section">
                <h3 class="section-title">{{ __('Billing parties') }}</h3>
                <p class="section-subtitle">{{ __('Essential vendor and customer details required to validate this invoice.') }}</p>
                <table class="party-table">
                    <tr>
                        <td class="party-left">
                            <div class="party-card">
                                <div class="section-title">{{ __('Vendor') }}</div>
                                <div class="field">
                                    <div class="field-label">{{ __('Name') }}</div>
                                    <div class="field-value">{{ $vendor['name'] ?? __('Not set') }}</div>
                                </div>
                                <div class="field">
                                    <div class="field-label">{{ __('Tax ID') }}</div>
                                    <div class="field-value ltr-value">{{ $vendorTaxId }}</div>
                                </div>
                                <div class="field">
                                    <div class="field-label">{{ __('Phone') }}</div>
                                    <div class="field-value ltr-value">{{ $vendorPhones }}</div>
                                </div>
                                <div class="field">
                                    <div class="field-label">{{ __('Address') }}</div>
                                    @forelse($vendorAddressLines as $line)
                                        <div class="field-copy">{{ $line }}</div>
                                    @empty
                                        <div class="field-copy">{{ __('Not set') }}</div>
                                    @endforelse
                                </div>
                            </div>
                        </td>
                        <td class="party-right">
                            <div class="party-card">
                                <div class="section-title">{{ __('Customer') }}</div>
                                <div class="field">
                                    <div class="field-label">{{ __('Organization') }}</div>
                                    <div class="field-value">{{ $customer['name'] ?? __('Not set') }}</div>
                                </div>
                                <div class="field">
                                    <div class="field-label">{{ __('Owner') }}</div>
                                    <div class="field-value">{{ $customer['owner_name'] ?? __('Not set') }}</div>
                                </div>
                                <div class="field">
                                    <div class="field-label">{{ __('Email') }}</div>
                                    <div class="field-value ltr-value">{{ $customerEmail }}</div>
                                </div>
                                <div class="field">
                                    <div class="field-label">{{ __('Address') }}</div>
                                    @forelse($customerAddressLines as $line)
                                        <div class="field-copy">{{ $line }}</div>
                                    @empty
                                        <div class="field-copy">{{ __('Not set') }}</div>
                                    @endforelse
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="section">
                <h3 class="section-title">{{ __('Invoice items') }}</h3>
                <p class="section-subtitle">{{ __('Only the invoice lines needed for business review and accounting approval are shown below.') }}</p>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>{{ __('Item') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th>{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            <tr>
                                <td>{{ $item['label'] ?? __('Not set') }}</td>
                                <td>{{ $item['description'] ?? '—' }}</td>
                                <td class="ltr-value">{{ $item['amount'] ?? '0.00' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">{{ __('No invoice items available.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="section">
                <table class="payment-table">
                    <tr>
                        <td class="payment-left">
                            <div class="payment-card">
                                <div class="section-title">{{ __('Payment details') }}</div>
                                <div class="field">
                                    <div class="field-label">{{ __('Payment method') }}</div>
                                    <div class="field-value">{{ $paymentMethodLabel }}</div>
                                </div>
                                <div class="field">
                                    <div class="field-label">{{ __('Reference') }}</div>
                                    <div class="field-value ltr-value">{{ $paymentReference }}</div>
                                </div>
                                <div class="field">
                                    <div class="field-label">{{ __('Paid at') }}</div>
                                    <div class="field-value ltr-value">{{ $paidAt }}</div>
                                </div>
                                <div class="field">
                                    <div class="field-label">{{ __('Billing period') }}</div>
                                    <div class="field-value">{{ $billingPeriod }}</div>
                                </div>
                                <div class="field">
                                    <div class="field-label">{{ __('Subscription plan') }}</div>
                                    <div class="field-value">{{ $subscription['plan_name'] ?? __('Not set') }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="payment-right">
                            <div class="summary-card">
                                <div class="section-title">{{ __('Invoice summary') }}</div>
                                <table class="summary-table">
                                    @foreach($summaryRows as $row)
                                        <tr class="summary-row @if(!empty($row['total'])) total @endif">
                                            <td>{{ $row['label'] }}</td>
                                            <td class="summary-value-cell ltr-value">{{ $row['value'] }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="footer">
                <table class="footer-table">
                    <tr>
                        <td>{{ __('Secure document generated from the subscription billing system.') }}</td>
                        <td class="footer-end">{{ $brandName }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
