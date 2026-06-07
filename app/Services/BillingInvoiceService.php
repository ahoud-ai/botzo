<?php

namespace App\Services;

use App\Helpers\DateTimeHelper;
use App\Http\Resources\BillingInvoiceResource;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\Organization;
use App\Support\BillingPaymentMethodResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\Response;

class BillingInvoiceService
{
    public function list(object $request, ?string $organizationUuid = null)
    {
        $billingOrganizationId = $this->resolveBillingOrganizationIdFromUuid(
            $organizationUuid ?? $request->query('organization_uuid')
        );
        $search = trim((string) $request->query('search', ''));
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $matchedInvoiceIds = $this->resolveSearchInvoiceIds($search);

        $query = BillingInvoice::query()
            ->with($this->invoiceListRelations())
            ->when($billingOrganizationId !== null, function ($query) use ($billingOrganizationId) {
                $query->where('organization_id', $billingOrganizationId);
            })
            ->when($search !== '', function ($query) use ($search, $matchedInvoiceIds) {
                $query->where(function ($searchQuery) use ($search, $matchedInvoiceIds) {
                    $searchQuery
                        ->whereHas('organization', function ($organizationQuery) use ($search) {
                            $organizationQuery->where('name', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('plan', function ($planQuery) use ($search) {
                            $planQuery
                                ->where('name', 'like', '%' . $search . '%')
                                ->orWhere('name_en', 'like', '%' . $search . '%')
                                ->orWhere('name_ar', 'like', '%' . $search . '%');
                        });

                    if ($matchedInvoiceIds !== []) {
                        $searchQuery->orWhereIn('id', $matchedInvoiceIds);
                    }
                });
            })
            ->when($dateFrom && BillingInvoice::hasColumn('created_at'), function ($query) use ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($dateTo && BillingInvoice::hasColumn('created_at'), function ($query) use ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            });

        if (BillingInvoice::hasColumn('created_at')) {
            $query->orderByDesc('created_at');
        }

        $rows = $query
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return BillingInvoiceResource::collection($rows);
    }

    public function documentForViewerOrganization(string $invoiceUuid, int $viewerOrganizationId): array
    {
        $billingOwnerId = app(OrganizationHierarchyService::class)->billingOwnerId($viewerOrganizationId) ?? $viewerOrganizationId;

        return $this->documentForBillingOwner($invoiceUuid, $billingOwnerId);
    }

    public function documentForAdmin(string $invoiceUuid): array
    {
        $invoice = BillingInvoice::query()
            ->with($this->invoiceDocumentRelations())
            ->where('uuid', $invoiceUuid)
            ->first();

        if (!$invoice) {
            throw (new ModelNotFoundException())->setModel(BillingInvoice::class, [$invoiceUuid]);
        }

        return $this->buildDocumentPayload($invoice);
    }

    public function documentForOrganizationUuid(string $organizationUuid, string $invoiceUuid): array
    {
        $organization = Organization::query()->where('uuid', $organizationUuid)->firstOrFail();
        $billingOwnerId = app(OrganizationHierarchyService::class)->billingOwnerId($organization->id) ?? $organization->id;

        return $this->documentForBillingOwner($invoiceUuid, $billingOwnerId);
    }

    public function documentForBillingOwner(string $invoiceUuid, int $billingOrganizationId): array
    {
        $invoice = BillingInvoice::query()
            ->with($this->invoiceDocumentRelations())
            ->where('uuid', $invoiceUuid)
            ->where('organization_id', $billingOrganizationId)
            ->first();

        if (!$invoice) {
            throw (new ModelNotFoundException())->setModel(BillingInvoice::class, [$invoiceUuid]);
        }

        return $this->buildDocumentPayload($invoice);
    }

    public function downloadFilename(array $document): string
    {
        $invoiceNumber = trim((string) ($document['invoice_number'] ?? 'invoice'));
        $normalized = Str::slug($invoiceNumber !== '' ? $invoiceNumber : 'invoice');

        return ($normalized !== '' ? $normalized : 'invoice') . '.pdf';
    }

    public function downloadResponse(array $viewData): Response
    {
        return $this->pdfResponse($viewData, false);
    }

    public function inlineResponse(array $viewData): Response
    {
        return $this->pdfResponse($viewData, true);
    }

    private function buildDocumentPayload(BillingInvoice $invoice): array
    {
        $payment = $this->resolvePaymentRecord($invoice);
        $organization = $invoice->organization;
        $owner = $organization?->owner?->user;
        $vendor = $this->vendorDetails();
        $planName = $invoice->plan?->localizedName(app()->getLocale())
            ?? $invoice->plan?->name
            ?? __('Subscription plan');
        $planPeriod = $this->planPeriodLabel($invoice->plan?->period);

        $taxLines = $invoice->taxRates->map(function ($taxRate) {
            $percentage = $taxRate->amount !== null ? rtrim(rtrim(number_format((float) $taxRate->amount, 2), '0'), '.') : '0';

            return [
                'label' => __('Tax (:percentage%)', ['percentage' => $percentage]),
                'amount' => $this->formatAmount($taxRate->rate),
            ];
        })->values()->all();

        return [
            'uuid' => $invoice->uuid,
            'invoice_number' => $this->invoiceNumber($invoice),
            'issued_at' => $invoice->getRawOriginal('created_at')
                ? DateTimeHelper::formatDate($invoice->getRawOriginal('created_at'))
                : __('Not set'),
            'status_label' => $this->invoiceStatusLabel($invoice, $payment),
            'vendor' => $vendor,
            'customer' => [
                'name' => $organization?->name ?? __('Not set'),
                'owner_name' => $owner?->full_name ?? __('Not set'),
                'email' => $owner?->email,
                'phone' => $owner?->phone,
                'address_lines' => $this->organizationAddressLines($organization),
            ],
            'subscription' => [
                'plan_name' => $planName,
                'period' => $planPeriod,
            ],
            'items' => [
                [
                    'label' => $planName,
                    'description' => $this->subscriptionItemDescription($planName, $planPeriod),
                    'amount' => $this->formatAmount($invoice->subtotal),
                ],
            ],
            'tax_lines' => $taxLines,
            'summary' => [
                'subtotal' => $this->formatAmount($invoice->subtotal),
                'tax' => $this->formatAmount($invoice->tax),
                'total' => $this->formatAmount($invoice->total),
            ],
            'payment' => [
                'method_label' => $this->paymentMethodLabel($invoice, $payment),
                'reference' => $payment?->details,
                'paid_at' => $payment?->getRawOriginal('created_at')
                    ? DateTimeHelper::formatDate($payment->getRawOriginal('created_at'))
                    : null,
            ],
        ];
    }

    public function resolvePaymentRecord(BillingInvoice $invoice): ?BillingPayment
    {
        $hasInvoicePaymentLink = BillingPayment::hasColumn('invoice_id');

        if ($hasInvoicePaymentLink) {
            try {
                $paymentQuery = BillingPayment::query()->where('invoice_id', $invoice->id);

                if (BillingPayment::hasColumn('created_at')) {
                    $paymentQuery->orderByDesc('created_at');
                }

                $payment = $paymentQuery
                    ->orderByDesc('id')
                    ->first();

                if ($payment) {
                    return $payment;
                }
            } catch (QueryException $exception) {
                // Older databases may miss invoice_id even if a stale schema cache says otherwise.
            }
        }

        $issuedAt = null;

        if (BillingInvoice::hasColumn('created_at')) {
            $issuedAt = $invoice->created_at instanceof Carbon
                ? $invoice->created_at
                : ($invoice->getRawOriginal('created_at') ? Carbon::parse($invoice->getRawOriginal('created_at')) : null);
        }

        $candidateQuery = BillingPayment::query()
            ->where('organization_id', $invoice->organization_id);

        if (BillingPayment::hasColumn('created_at')) {
            $candidateQuery->orderByDesc('created_at');
        }

        $candidateQuery->orderByDesc('id');

        if ($issuedAt && BillingPayment::hasColumn('created_at')) {
            $candidateQuery->whereBetween('created_at', [
                $issuedAt->copy()->subMinutes(10),
                $issuedAt->copy()->addMinutes(5),
            ]);
        }

        $candidates = $candidateQuery->limit(10)->get();
        $exactMatches = $candidates
            ->filter(function (BillingPayment $payment) use ($invoice) {
                return abs(((float) $payment->amount) - ((float) $invoice->total)) < 0.01;
            })
            ->values();

        if ($exactMatches->count() === 1) {
            return $exactMatches->first();
        }

        return null;
    }

    public function invoiceNumber(BillingInvoice $invoice): string
    {
        $prefix = trim((string) app(SettingValueService::class)->getString('invoice_prefix', 'INV'));
        $prefix = $prefix !== '' ? $prefix : 'INV';

        return $prefix . '-' . str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT);
    }

    public function formatAmount($amount): string
    {
        return number_format((float) $amount, 2);
    }

    public function invoiceStatusLabel(BillingInvoice $invoice, ?BillingPayment $payment = null): string
    {
        if ($payment) {
            return __('Paid');
        }

        if ((float) $invoice->total <= 0.0) {
            return __('Covered by balance');
        }

        return __('Settled');
    }

    public function paymentMethodLabel(BillingInvoice $invoice, ?BillingPayment $payment = null): string
    {
        if ($payment) {
            return __(BillingPaymentMethodResolver::displayLabel($payment->payment_method, $payment->processor));
        }

        if ((float) $invoice->total <= 0.0) {
            return __('Account balance');
        }

        return __('Payment completed');
    }

    private function resolveBillingOrganizationIdFromUuid(?string $organizationUuid): ?int
    {
        if ($organizationUuid === null) {
            return null;
        }

        $organization = Organization::query()->where('uuid', $organizationUuid)->first();
        if (!$organization) {
            return null;
        }

        return app(OrganizationHierarchyService::class)->billingOwnerId($organization->id) ?? $organization->id;
    }

    /**
     * @return array<int, int>
     */
    private function resolveSearchInvoiceIds(string $search): array
    {
        if ($search === '') {
            return [];
        }

        $digits = preg_replace('/\D+/', '', $search);
        if ($digits === null || $digits === '') {
            return [];
        }

        $trimmed = ltrim($digits, '0');
        $candidates = array_filter([
            (int) $digits,
            (int) ($trimmed === '' ? '0' : $trimmed),
        ], static fn ($value) => $value > 0);

        return array_values(array_unique($candidates));
    }

    /**
     * @return array{name:string,tax_id:?string,phones:array<int,string>,address_lines:array<int,string>}
     */
    private function vendorDetails(): array
    {
        $settings = app(SettingValueService::class);

        $addressLines = array_values(array_filter([
            $settings->getString('billing_address', ''),
            $settings->getString('billing_city', ''),
            $settings->getString('billing_state', ''),
            $settings->getString('billing_postal_code', ''),
            $settings->getString('billing_country', ''),
        ]));

        $phones = array_values(array_filter([
            $settings->getString('billing_phone_1', ''),
            $settings->getString('billing_phone_2', ''),
        ]));

        $logoPath = trim($settings->getString('logo', ''));

        return [
            'name' => $settings->getString('billing_name', config('app.name', 'App')),
            'company_name' => $settings->getString('company_name', config('app.name', 'App')),
            'tax_id' => $settings->getString('billing_tax_id', ''),
            'phones' => $phones,
            'address_lines' => $addressLines,
            'logo_path' => $logoPath !== '' ? public_path('media/' . ltrim($logoPath, '/')) : public_path('images/logo.png'),
            'logo_url' => $logoPath !== '' ? url('/media/' . ltrim($logoPath, '/')) : url('/images/logo.png'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function organizationAddressLines(?Organization $organization): array
    {
        if (!$organization || !$organization->address) {
            return [];
        }

        $decoded = json_decode($organization->address, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter([
            $decoded['street'] ?? null,
            $decoded['city'] ?? null,
            $decoded['state'] ?? null,
            $decoded['zip'] ?? null,
            $decoded['country'] ?? null,
        ]));
    }

    private function planPeriodLabel(?string $period): string
    {
        return match ($period) {
            'monthly' => __('Monthly'),
            'yearly' => __('Yearly'),
            default => __('Not set'),
        };
    }

    private function subscriptionItemDescription(string $planName, string $planPeriod): string
    {
        if ($planPeriod !== __('Not set')) {
            return __('Subscription access for :plan with :period billing.', [
                'plan' => $planName,
                'period' => $planPeriod,
            ]);
        }

        return __('Subscription access for :plan.', [
            'plan' => $planName,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function invoiceListRelations(): array
    {
        return ['organization', 'plan'];
    }

    /**
     * @return array<int, string>
     */
    private function invoiceDocumentRelations(): array
    {
        return [
            'organization.owner.user',
            'plan',
            'taxRates',
        ];
    }

    private function pdfResponse(array $viewData, bool $inline): Response
    {
        $filename = $this->downloadFilename($viewData['invoice'] ?? []);
        $binary = $this->buildPdfBinary($viewData);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('%s; filename="%s"', $inline ? 'inline' : 'attachment', $filename),
            'Content-Length' => (string) strlen($binary),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    private function buildPdfBinary(array $viewData): string
    {
        $mpdf = $this->makePdfEngine();
        $mpdf->SetTitle(($viewData['title'] ?? __('Invoice')) . ' - ' . (($viewData['invoice']['invoice_number'] ?? 'invoice')));
        $mpdf->SetDirectionality(str_starts_with(strtolower((string) app()->getLocale()), 'ar') ? 'rtl' : 'ltr');
        $html = $this->buildPdfHtmlDocument($viewData);

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function makePdfEngine(): Mpdf
    {
        $baseTempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'botzo-mpdf';

        if (!is_dir($baseTempDir)) {
            mkdir($baseTempDir, 0755, true);
        }

        $tempDir = $baseTempDir . DIRECTORY_SEPARATOR . 'render-' . Str::uuid();

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $configVariables = (new ConfigVariables())->getDefaults();
        $fontVariables = (new FontVariables())->getDefaults();
        $customFontData = $this->invoicePdfFontData();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'margin_header' => 0,
            'margin_footer' => 0,
            'fontDir' => array_values(array_filter(array_unique(array_merge(
                $configVariables['fontDir'],
                $this->invoicePdfFontDirectories()
            )))),
            'fontdata' => $fontVariables['fontdata'] + $customFontData,
            'default_font' => 'dejavusans',
        ]);

        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->useSubstitutions = true;
        $mpdf->showImageErrors = (bool) config('app.debug', false);
        $mpdf->shrink_tables_to_fit = 1;
        $mpdf->simpleTables = true;
        $mpdf->packTableData = true;

        return $mpdf;
    }

    private function invoicePdfFontDirectories(): array
    {
        return array_values(array_filter([
            public_path('fonts/Tajawal'),
            resource_path('fonts/ping-ar-lt'),
            resource_path('fonts/Outfit'),
            is_dir('C:\Windows\Fonts') ? 'C:\Windows\Fonts' : null,
        ]));
    }

    private function invoicePdfFontData(): array
    {
        $tajawalRegular = public_path('fonts/Tajawal/Tajawal-Regular.ttf');
        $tajawalBold = public_path('fonts/Tajawal/Tajawal-Bold.ttf');
        $bundledRegular = resource_path('fonts/ping-ar-lt/ping-ar-lt-regular.otf');
        $bundledBold = resource_path('fonts/ping-ar-lt/ping-ar-lt-bold.otf');
        $windowsRegular = 'C:\Windows\Fonts\tahoma.ttf';
        $windowsBold = 'C:\Windows\Fonts\tahomabd.ttf';

        $fonts = [];

        if (is_file($tajawalRegular) && is_file($tajawalBold)) {
            $fonts['tajawalpdf'] = [
                'R' => 'Tajawal-Regular.ttf',
                'B' => 'Tajawal-Bold.ttf',
                'useOTL' => 0xFF,
                'useKashida' => 75,
            ];
        }

        if (is_file($bundledRegular) && is_file($bundledBold)) {
            $fonts['botzoarabic'] = [
                'R' => 'ping-ar-lt-regular.otf',
                'B' => 'ping-ar-lt-bold.otf',
                'useOTL' => 0xFF,
                'useKashida' => 75,
            ];
        }

        if ($fonts === [] && is_file($windowsRegular) && is_file($windowsBold)) {
            $fonts['botzoarabic'] = [
                'R' => 'tahoma.ttf',
                'B' => 'tahomabd.ttf',
                'useOTL' => 0xFF,
                'useKashida' => 75,
            ];
        }

        return $fonts;
    }

    private function invoicePdfBodyFont(string $direction): string
    {
        if ($direction !== 'rtl') {
            return 'dejavusans, sans-serif';
        }

        if (is_file(resource_path('fonts/ping-ar-lt/ping-ar-lt-regular.otf'))) {
            return 'botzoarabic, dejavusans, sans-serif';
        }

        if (is_file(public_path('fonts/Tajawal/Tajawal-Regular.ttf'))) {
            return 'tajawalpdf, dejavusans, sans-serif';
        }

        return 'dejavusans, sans-serif';
    }

    private function buildPdfHtmlDocument(array $viewData): string
    {
        $invoice = $viewData['invoice'] ?? [];
        $locale = str_replace('_', '-', app()->getLocale());
        $direction = str_starts_with(strtolower((string) app()->getLocale()), 'ar') ? 'rtl' : 'ltr';
        $textAlign = $direction === 'rtl' ? 'right' : 'left';
        $oppositeAlign = $direction === 'rtl' ? 'left' : 'right';
        $bodyFont = $this->invoicePdfBodyFont($direction);
        $title = (string) ($viewData['title'] ?? __('Invoice'));

        $vendor = $invoice['vendor'] ?? [];
        $customer = $invoice['customer'] ?? [];
        $subscription = $invoice['subscription'] ?? [];
        $payment = $invoice['payment'] ?? [];
        $summary = $invoice['summary'] ?? [];
        $items = $invoice['items'] ?? [];
        $taxLines = $invoice['tax_lines'] ?? [];

        $brandName = (string) ($vendor['company_name'] ?? $vendor['name'] ?? config('app.name', 'Botzo'));
        $documentNumber = (string) ($invoice['invoice_number'] ?? __('Invoice'));
        $issuedAt = (string) ($invoice['issued_at'] ?? __('Not set'));
        $statusLabel = (string) ($invoice['status_label'] ?? __('Invoice'));
        $paymentMethodLabel = (string) ($payment['method_label'] ?? __('Payment'));
        $paymentReference = (string) ($payment['reference'] ?? __('Not set'));
        $paidAt = (string) ($payment['paid_at'] ?? __('Not set'));
        $billingPeriod = (string) ($subscription['period'] ?? __('Not set'));
        $planName = (string) ($subscription['plan_name'] ?? __('Not set'));
        $vendorPhones = !empty($vendor['phones']) ? implode(' / ', $vendor['phones']) : __('Not set');
        $customerEmail = (string) ($customer['email'] ?? __('Not set'));
        $vendorTaxId = (string) ($vendor['tax_id'] ?? __('Not set'));
        $vendorAddressLines = array_values(array_filter($vendor['address_lines'] ?? []));
        $customerAddressLines = array_values(array_filter($customer['address_lines'] ?? []));
        $vendorAddress = $vendorAddressLines !== []
            ? implode('<br>', array_map(fn ($line) => $this->escapePdfHtml((string) $line), $vendorAddressLines))
            : $this->escapePdfHtml(__('Not set'));
        $customerAddress = $customerAddressLines !== []
            ? implode('<br>', array_map(fn ($line) => $this->escapePdfHtml((string) $line), $customerAddressLines))
            : $this->escapePdfHtml(__('Not set'));
        $brandInitial = Str::upper(Str::substr(trim($brandName), 0, 1));

        $summaryRows = [
            ['label' => __('Subtotal'), 'value' => $summary['subtotal'] ?? '0.00', 'total' => false],
        ];

        if ($taxLines !== []) {
            foreach ($taxLines as $line) {
                $summaryRows[] = [
                    'label' => $line['label'] ?? __('Tax'),
                    'value' => $line['amount'] ?? '0.00',
                    'total' => false,
                ];
            }
        } else {
            $summaryRows[] = [
                'label' => __('Tax'),
                'value' => $summary['tax'] ?? '0.00',
                'total' => false,
            ];
        }

        $summaryRows[] = [
            'label' => __('Total'),
            'value' => $summary['total'] ?? '0.00',
            'total' => true,
        ];

        $logoPngPath = public_path('bimi/botzo-logo-app.png');
        $logoSvgPath = public_path('bimi/botzo-logo.svg');
        $logoSrc = is_file($logoPngPath)
            ? 'file:///' . str_replace('\\', '/', $logoPngPath)
            : (is_file($logoSvgPath) ? 'file:///' . str_replace('\\', '/', $logoSvgPath) : null);
        $logoHtml = $logoSrc
            ? '<img src="' . $this->escapePdfHtml($logoSrc) . '" alt="' . $this->escapePdfHtml($brandName) . '" style="width:48px;height:48px;display:block;border-radius:10px;object-fit:cover;">'
            : '<div style="width:48px;height:48px;border-radius:10px;background:linear-gradient(135deg,#25D366,#00E5FF 45%,#7C3AED);text-align:center;line-height:48px;font-size:22px;font-weight:bold;color:#ffffff;">' . $this->escapePdfHtml($brandInitial !== '' ? $brandInitial : 'B') . '</div>';

        // ── mPDF-safe rules ──────────────────────────────────────────
        // Layout tables  → border-collapse:separate;border-spacing:0  (prevents mbw crash)
        // Data table     → border-collapse:separate;border-spacing:0  + per-cell borders
        // Never put border on <table> element itself
        // border-radius only on <div>, never on <table>/<td>
        $LT = 'width:100%;border-collapse:separate;border-spacing:0;table-layout:fixed;';
        $B  = '#E5EBF3';   // border color
        $ink = '#0A0F1C';
        $gradient = 'linear-gradient(90deg,#25D366 0%,#00E5FF 34%,#1877F2 68%,#7C3AED 100%)';

        // Section-title row: thin accent bar + bold text (mPDF-safe, no CSS borders on inline elements)
        $accentBar = '<table style="' . $LT . 'margin-bottom:4px;"><tr>'
            . '<td style="width:3px;background:#1877F2;padding:0;line-height:1;">&nbsp;</td>'
            . '<td style="padding:0 8px;font-size:13px;font-weight:bold;color:' . $ink . ';vertical-align:middle;">';
        $accentBarClose = '</td></tr></table>';

        $html = '<html lang="' . $this->escapePdfHtml($locale) . '" dir="' . $this->escapePdfHtml($direction) . '"><head><meta charset="utf-8"><title>'
            . $this->escapePdfHtml($title) . ' - ' . $this->escapePdfHtml($documentNumber)
            . '</title><style>'
            . 'body{font-family:' . $bodyFont . ';font-size:11px;line-height:1.8;color:' . $ink . ';margin:0;padding:12px;background:#F5F7FA;}'
            . '.lbl{font-size:9px;color:#64748B;margin-bottom:3px;}'
            . '.val{font-size:12px;font-weight:bold;color:' . $ink . ';}'
            . '.ltr{direction:ltr;text-align:left;}'
            . '</style></head><body>';

        // ══════════════════════════════════════════════════════════
        // Main white card
        // ══════════════════════════════════════════════════════════
        $html .= '<div style="background:#ffffff;border:1px solid ' . $B . ';">';

        // Gradient top bar (6 px)
        $html .= '<div style="height:6px;background:' . $gradient . ';font-size:0;line-height:0;">&nbsp;</div>';

        // ── SECTION 1: Header ──────────────────────────────────────
        $html .= '<div style="background:#FAFBFD;padding:18px 20px;">';

        // 2-col: main (logo + title) | side (status/no/date/total)
        $html .= '<table style="' . $LT . '"><tr>'

            // ── Main column ──
            . '<td style="width:58%;vertical-align:top;padding-' . ($direction === 'rtl' ? 'left' : 'right') . ':14px;">'

            // logo + badge row
            . '<table style="' . $LT . '"><tr>'
            . '<td style="width:58px;vertical-align:top;">' . $logoHtml . '</td>'
            . '<td style="vertical-align:middle;padding-' . ($direction === 'rtl' ? 'right' : 'left') . ':10px;">'
            . '<div style="display:inline-block;border:1px solid #DDE6F0;background:#ffffff;color:#334155;font-size:9px;font-weight:bold;padding:4px 10px;">'
            . $this->escapePdfHtml(__('Official billing document')) . '</div>'
            . '</td></tr></table>'

            // Big title + subtitle
            . '<div style="font-size:26px;font-weight:bold;color:' . $ink . ';margin:10px 0 5px;">' . $this->escapePdfHtml(__('Invoice')) . '</div>'
            . '<div style="font-size:10px;color:#475569;line-height:1.8;">' . $this->escapePdfHtml(__('A simplified invoice prepared for accounting review, printing, and PDF download.')) . '</div>'
            . '</td>'

            // ── Side column (status / no / date / dark total) ──
            . '<td style="width:42%;vertical-align:top;">'

            // Status
            . '<div style="background:#ecfdf5;border:1px solid #bbf7d0;padding:8px 12px;margin-bottom:6px;">'
            . '<div class="lbl">' . $this->escapePdfHtml(__('Status')) . '</div>'
            . '<div style="font-size:11px;font-weight:bold;color:#15803d;">&#9679; ' . $this->escapePdfHtml($statusLabel) . '</div>'
            . '</div>'

            // Invoice no.
            . '<div style="background:#ffffff;border:1px solid ' . $B . ';padding:8px 12px;margin-bottom:6px;">'
            . '<div class="lbl">' . $this->escapePdfHtml(__('Invoice no.')) . '</div>'
            . '<div class="val ltr">' . $this->escapePdfHtml($documentNumber) . '</div>'
            . '</div>'

            // Issued date
            . '<div style="background:#ffffff;border:1px solid ' . $B . ';padding:8px 12px;margin-bottom:6px;">'
            . '<div class="lbl">' . $this->escapePdfHtml(__('Issued date')) . '</div>'
            . '<div class="val ltr">' . $this->escapePdfHtml($issuedAt) . '</div>'
            . '</div>'

            // Total — dark card
            . '<div style="background:' . $ink . ';padding:10px 12px;">'
            . '<div style="font-size:9px;color:#94A3B8;margin-bottom:4px;">' . $this->escapePdfHtml(__('Total')) . '</div>'
            . '<div style="font-size:19px;font-weight:bold;color:#25D366;direction:ltr;text-align:left;">'
            . $this->escapePdfHtml((string) ($summary['total'] ?? '0.00')) . '</div>'
            . '</div>'

            . '</td></tr></table>';

        // 3-col facts: plan / period / payment method
        $html .= '<table style="' . $LT . 'margin-top:12px;"><tr>'
            . '<td style="width:33%;vertical-align:top;padding-' . ($direction === 'rtl' ? 'left' : 'right') . ':6px;">'
            . '<div style="background:#ffffff;border:1px solid ' . $B . ';padding:10px 12px;">'
            . '<div class="lbl">' . $this->escapePdfHtml(__('Subscription plan')) . '</div>'
            . '<div class="val">' . $this->escapePdfHtml($planName) . '</div>'
            . '</div></td>'

            . '<td style="width:33%;vertical-align:top;padding-left:6px;padding-right:6px;">'
            . '<div style="background:#ffffff;border:1px solid ' . $B . ';padding:10px 12px;">'
            . '<div class="lbl">' . $this->escapePdfHtml(__('Billing period')) . '</div>'
            . '<div class="val">' . $this->escapePdfHtml($billingPeriod) . '</div>'
            . '</div></td>'

            . '<td style="width:33%;vertical-align:top;padding-' . ($direction === 'rtl' ? 'right' : 'left') . ':6px;">'
            . '<div style="background:#ffffff;border:1px solid ' . $B . ';padding:10px 12px;">'
            . '<div class="lbl">' . $this->escapePdfHtml(__('Payment method')) . '</div>'
            . '<div class="val">' . $this->escapePdfHtml($paymentMethodLabel) . '</div>'
            . '</div></td>'
            . '</tr></table>';

        $html .= '</div>'; // end section 1

        // ── SECTION 2: Billing parties ─────────────────────────────
        $html .= '<div style="padding:16px 20px;border-top:1px solid ' . $B . ';">';
        $html .= $accentBar . $this->escapePdfHtml(__('Billing parties')) . $accentBarClose;
        $html .= '<div style="font-size:9.5px;color:#64748B;margin-bottom:10px;">' . $this->escapePdfHtml(__('Essential vendor and customer details required to validate this invoice.')) . '</div>';

        $html .= '<table style="' . $LT . '"><tr>'

            // Vendor
            . '<td style="width:49%;vertical-align:top;padding-' . ($direction === 'rtl' ? 'left' : 'right') . ':6px;">'
            . '<div style="background:#ffffff;border:1px solid ' . $B . ';padding:14px 16px;">'

            // Vendor header row
            . '<table style="' . $LT . 'margin-bottom:8px;"><tr>'
            . '<td style="vertical-align:middle;padding-' . ($direction === 'rtl' ? 'left' : 'right') . ':8px;">'
            . '<div style="width:28px;height:28px;background:#FFF7ED;border:1px solid #FED7AA;text-align:center;line-height:28px;font-size:14px;">&#127968;</div>'
            . '</td>'
            . '<td style="vertical-align:middle;font-size:13px;font-weight:bold;color:' . $ink . ';">' . $this->escapePdfHtml(__('Vendor')) . '</td>'
            . '</tr></table>';

        foreach ([
            [__('Name'),    $this->escapePdfHtml((string) ($vendor['name'] ?? __('Not set'))), false],
            [__('Tax ID'),  '<span style="direction:ltr;">' . $this->escapePdfHtml($vendorTaxId) . '</span>', true],
            [__('Contact'), '<span style="direction:ltr;">' . $this->escapePdfHtml($vendorPhones) . '</span>', true],
            [__('Address'), $vendorAddress, false],
        ] as [$lbl, $v, $ltrVal]) {
            $html .= '<div style="border-top:1px solid ' . $B . ';padding:7px 0;">'
                . '<div class="lbl">' . $this->escapePdfHtml($lbl) . '</div>'
                . '<div style="font-size:11px;color:#334155;margin-top:2px;">' . $v . '</div>'
                . '</div>';
        }
        $html .= '</div></td>'

            // Customer
            . '<td style="width:2%;"></td>'
            . '<td style="width:49%;vertical-align:top;">'
            . '<div style="background:#ffffff;border:1px solid ' . $B . ';padding:14px 16px;">'

            // Customer header row
            . '<table style="' . $LT . 'margin-bottom:8px;"><tr>'
            . '<td style="vertical-align:middle;padding-' . ($direction === 'rtl' ? 'left' : 'right') . ':8px;">'
            . '<div style="width:28px;height:28px;background:#EFF6FF;border:1px solid #BFDBFE;text-align:center;line-height:28px;font-size:14px;">&#128100;</div>'
            . '</td>'
            . '<td style="vertical-align:middle;font-size:13px;font-weight:bold;color:' . $ink . ';">' . $this->escapePdfHtml(__('Customer')) . '</td>'
            . '</tr></table>';

        foreach ([
            [__('Organization'), $this->escapePdfHtml((string) ($customer['name'] ?? __('Not set'))), false],
            [__('Owner'),        $this->escapePdfHtml((string) ($customer['owner_name'] ?? __('Not set'))), false],
            [__('Email'),        '<span style="direction:ltr;">' . $this->escapePdfHtml($customerEmail) . '</span>', true],
            [__('Address'),      $customerAddress, false],
        ] as [$lbl, $v, $ltrVal]) {
            $html .= '<div style="border-top:1px solid ' . $B . ';padding:7px 0;">'
                . '<div class="lbl">' . $this->escapePdfHtml($lbl) . '</div>'
                . '<div style="font-size:11px;color:#334155;margin-top:2px;">' . $v . '</div>'
                . '</div>';
        }
        $html .= '</div></td></tr></table>';
        $html .= '</div>'; // end section 2

        // ── SECTION 3: Invoice items ───────────────────────────────
        $html .= '<div style="padding:16px 20px;border-top:1px solid ' . $B . ';">';
        $html .= $accentBar . $this->escapePdfHtml(__('Invoice items')) . $accentBarClose;
        $html .= '<div style="font-size:9.5px;color:#64748B;margin-bottom:10px;">' . $this->escapePdfHtml(__('Only the invoice lines needed for business review and accounting approval are shown below.')) . '</div>';

        $itemRows = $items === [] ? [[
            'label'       => __('Not set'),
            'description' => __('No invoice items available.'),
            'amount'      => '0.00',
        ]] : $items;

        // Items table: separate spacing, per-cell borders (no border on <table> element)
        $cellBorder = 'border-bottom:1px solid ' . $B . ';border-right:1px solid ' . $B . ';';
        $html .= '<table style="' . $LT . '">'
            // Dark header row
            . '<tr>'
            . '<th style="width:22%;background:' . $ink . ';color:#ffffff;font-size:10px;font-weight:bold;padding:10px 12px;text-align:' . $textAlign . ';border-bottom:1px solid #1E293B;border-right:1px solid #1E293B;border-left:1px solid #1E293B;border-top:1px solid #1E293B;">' . $this->escapePdfHtml(__('Item')) . '</th>'
            . '<th style="width:58%;background:' . $ink . ';color:#ffffff;font-size:10px;font-weight:bold;padding:10px 12px;text-align:' . $textAlign . ';border-bottom:1px solid #1E293B;border-right:1px solid #1E293B;border-top:1px solid #1E293B;">' . $this->escapePdfHtml(__('Description')) . '</th>'
            . '<th style="width:20%;background:' . $ink . ';color:#ffffff;font-size:10px;font-weight:bold;padding:10px 12px;text-align:' . $oppositeAlign . ';border-bottom:1px solid #1E293B;border-right:1px solid #1E293B;border-top:1px solid #1E293B;">' . $this->escapePdfHtml(__('Amount')) . '</th>'
            . '</tr>';

        foreach ($itemRows as $item) {
            $html .= '<tr>'
                . '<td style="' . $cellBorder . 'border-left:1px solid ' . $B . ';border-top:none;padding:10px 12px;font-weight:bold;color:' . $ink . ';font-size:11px;">' . $this->escapePdfHtml((string) ($item['label'] ?? '—')) . '</td>'
                . '<td style="' . $cellBorder . 'border-top:none;padding:10px 12px;color:#475569;font-size:11px;">' . $this->escapePdfHtml((string) ($item['description'] ?? '—')) . '</td>'
                . '<td style="' . $cellBorder . 'border-top:none;padding:10px 12px;text-align:' . $oppositeAlign . ';font-weight:bold;color:#25D366;font-size:11px;direction:ltr;">' . $this->escapePdfHtml((string) ($item['amount'] ?? '0.00')) . '</td>'
                . '</tr>';
        }
        $html .= '</table>';
        $html .= '</div>'; // end section 3

        // ── SECTION 4: Payment details + Invoice summary ────────────
        $html .= '<div style="padding:16px 20px;border-top:1px solid ' . $B . ';">';
        $html .= '<table style="' . $LT . '"><tr>'

            // Payment details (start side)
            . '<td style="width:49%;vertical-align:top;padding-' . ($direction === 'rtl' ? 'left' : 'right') . ':6px;">'
            . $accentBar . $this->escapePdfHtml(__('Payment details')) . $accentBarClose;

        foreach ([
            [__('Payment method'), $this->escapePdfHtml($paymentMethodLabel), false],
            [__('Reference'),       $this->escapePdfHtml($paymentReference),   true],
            [__('Paid at'),         $this->escapePdfHtml($paidAt),             true],
            [__('Billing period'),  $this->escapePdfHtml($billingPeriod),      false],
            [__('Subscription plan'), $this->escapePdfHtml($planName),         false],
        ] as [$lbl, $v, $ltrVal]) {
            $html .= '<div style="border-bottom:1px solid ' . $B . ';padding:8px 0;">'
                . '<div class="lbl">' . $this->escapePdfHtml($lbl) . '</div>'
                . '<div style="font-size:12px;font-weight:bold;color:' . $ink . ';margin-top:2px;' . ($ltrVal ? 'direction:ltr;text-align:left;' : '') . '">' . $v . '</div>'
                . '</div>';
        }

        $html .= '</td>'
            . '<td style="width:2%;"></td>'

            // Invoice summary (end side)
            . '<td style="width:49%;vertical-align:top;">'
            . $accentBar . $this->escapePdfHtml(__('Invoice summary')) . $accentBarClose;

        foreach ($summaryRows as $row) {
            $isTotal = !empty($row['total']);
            if ($isTotal) {
                $html .= '<div style="padding:10px 0;">'
                    . '<table style="' . $LT . '"><tr>'
                    . '<td style="font-size:14px;font-weight:bold;color:' . $ink . ';">' . $this->escapePdfHtml((string) ($row['label'] ?? '')) . '</td>'
                    . '<td style="text-align:' . $oppositeAlign . ';font-size:16px;font-weight:bold;color:#25D366;direction:ltr;">' . $this->escapePdfHtml((string) ($row['value'] ?? '0.00')) . '</td>'
                    . '</tr></table></div>';
            } else {
                $html .= '<div style="border-bottom:1px solid ' . $B . ';padding:8px 0;">'
                    . '<table style="' . $LT . '"><tr>'
                    . '<td style="font-size:11px;color:#334155;">' . $this->escapePdfHtml((string) ($row['label'] ?? '')) . '</td>'
                    . '<td style="text-align:' . $oppositeAlign . ';font-size:11px;font-weight:bold;color:' . $ink . ';direction:ltr;">' . $this->escapePdfHtml((string) ($row['value'] ?? '0.00')) . '</td>'
                    . '</tr></table></div>';
            }
        }

        // Success banner
        $html .= '<div style="background:#ecfdf5;border:1px solid #bbf7d0;padding:10px 12px;text-align:center;color:#15803d;font-weight:bold;font-size:11px;margin-top:10px;">'
            . '&#10003; ' . $this->escapePdfHtml(__('Payment received successfully'))
            . '</div>';

        $html .= '</td></tr></table>';
        $html .= '</div>'; // end section 4

        // ── FOOTER (dark) ──────────────────────────────────────────
        $html .= '<table style="' . $LT . '">'
            . '<tr>'
            . '<td style="background:' . $ink . ';padding:14px 20px;vertical-align:middle;">'
            . '<table style="' . $LT . '"><tr>'
            . '<td style="vertical-align:middle;padding-' . ($direction === 'rtl' ? 'left' : 'right') . ':8px;">' . $logoHtml . '</td>'
            . '<td style="vertical-align:middle;font-size:16px;font-weight:bold;color:#ffffff;">' . $this->escapePdfHtml($brandName) . '</td>'
            . '</tr></table>'
            . '</td>'
            . '<td style="background:' . $ink . ';padding:14px 20px;text-align:' . $oppositeAlign . ';vertical-align:middle;font-size:10px;color:#64748B;">'
            . $this->escapePdfHtml(__('Secure document generated from the subscription billing system.'))
            . '</td>'
            . '</tr></table>';

        $html .= '</div>'; // end main white card
        $html .= '</body></html>';

        return $html;
    }

    private function escapePdfHtml(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function renderPdfDocument(Mpdf $pdf, array $document): void
    {
        $direction = str_starts_with(strtolower((string) app()->getLocale()), 'ar') ? 'rtl' : 'ltr';
        $isRtl = $direction === 'rtl';

        $pageX = 10.0;
        $pageY = 10.0;
        $pageWidth = 190.0;
        $pageHeight = 277.0;
        $gap = 4.0;

        $vendor = $document['vendor'] ?? [];
        $customer = $document['customer'] ?? [];
        $subscription = $document['subscription'] ?? [];
        $payment = $document['payment'] ?? [];
        $summary = $document['summary'] ?? [];
        $items = $document['items'] ?? [];
        $taxLines = $document['tax_lines'] ?? [];

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

        $pdf->SetDirectionality($direction);
        $pdf->SetDrawColor(219, 226, 234);
        $pdf->SetLineWidth(0.2);
        $pdf->Rect($pageX, $pageY, $pageWidth, $pageHeight);

        $brandName = (string) ($vendor['company_name'] ?? $vendor['name'] ?? config('app.name', 'Botzo'));
        $logoPath = $vendor['logo_path'] ?? public_path('images/logo.png');
        $titleStartX = $pageX + 5;

        if (is_string($logoPath) && $logoPath !== '' && file_exists($logoPath)) {
            try {
                $pdf->Image($logoPath, $pageX + 5, $pageY + 5, 18, 18);
                $titleStartX = $pageX + 27;
            } catch (\Throwable) {
                $titleStartX = $pageX + 5;
            }
        }

        $this->writePdfLine($pdf, $titleStartX, $pageY + 5, 82, $brandName, 8.5, '', [71, 85, 105], $direction);
        $this->writePdfLine($pdf, $titleStartX, $pageY + 11, 82, __('Invoice'), 22, 'B', [15, 23, 42], $direction);
        $this->writePdfParagraph(
            $pdf,
            $titleStartX,
            $pageY + 21,
            90,
            __('A simplified invoice prepared for accounting review, printing, and PDF download.'),
            8.1,
            [100, 116, 139],
            $direction
        );

        $metaX = $pageX + 116;
        $metaY = $pageY + 5;
        $metaWidth = 69;
        $metaHeight = 15;
        $metaGap = 2.5;

        $this->drawPdfBox($pdf, $metaX, $metaY, $metaWidth, $metaHeight, [236, 253, 245], [187, 247, 208]);
        $this->writePdfLabelValue($pdf, $metaX + 3, $metaY + 2.2, $metaWidth - 6, __('Status'), (string) ($document['status_label'] ?? __('Invoice')), $direction, [21, 128, 61], [21, 128, 61]);

        $this->drawPdfBox($pdf, $metaX, $metaY + ($metaHeight + $metaGap), $metaWidth, $metaHeight);
        $this->writePdfLabelValue($pdf, $metaX + 3, $metaY + ($metaHeight + $metaGap) + 2.2, $metaWidth - 6, __('Invoice no.'), (string) ($document['invoice_number'] ?? __('Invoice')), 'ltr');

        $this->drawPdfBox($pdf, $metaX, $metaY + (2 * ($metaHeight + $metaGap)), $metaWidth, $metaHeight);
        $this->writePdfLabelValue($pdf, $metaX + 3, $metaY + (2 * ($metaHeight + $metaGap)) + 2.2, $metaWidth - 6, __('Issued date'), (string) ($document['issued_at'] ?? __('Not set')), 'ltr');

        $this->drawPdfBox($pdf, $metaX, $metaY + (3 * ($metaHeight + $metaGap)), $metaWidth, $metaHeight);
        $this->writePdfLabelValue($pdf, $metaX + 3, $metaY + (3 * ($metaHeight + $metaGap)) + 2.2, $metaWidth - 6, __('Total'), (string) ($summary['total'] ?? '0.00'), 'ltr', [100, 116, 139], [15, 23, 42], 'R');

        $partyY = $pageY + 63;
        $partyWidth = ($pageWidth - $gap) / 2;
        $partyHeight = 42;
        $leftPartyX = $pageX;
        $rightPartyX = $pageX + $partyWidth + $gap;

        $this->drawPdfBox($pdf, $leftPartyX, $partyY, $partyWidth, $partyHeight);
        $this->writePdfLine($pdf, $leftPartyX + 3, $partyY + 3, $partyWidth - 6, __('Vendor'), 8.2, 'B', [71, 85, 105], $direction);
        $this->writePdfLabelValue($pdf, $leftPartyX + 3, $partyY + 9, $partyWidth - 6, __('Name'), (string) ($vendor['name'] ?? __('Not set')), $direction);
        $this->writePdfLabelValue($pdf, $leftPartyX + 3, $partyY + 20, $partyWidth - 6, __('Tax ID'), (string) ($vendor['tax_id'] ?? __('Not set')), 'ltr');
        $this->writePdfLabelValue($pdf, $leftPartyX + 3, $partyY + 31, $partyWidth - 6, __('Phone'), implode(' / ', array_filter((array) ($vendor['phones'] ?? []))) ?: __('Not set'), 'ltr');

        $this->drawPdfBox($pdf, $rightPartyX, $partyY, $partyWidth, $partyHeight);
        $this->writePdfLine($pdf, $rightPartyX + 3, $partyY + 3, $partyWidth - 6, __('Customer'), 8.2, 'B', [71, 85, 105], $direction);
        $this->writePdfLabelValue($pdf, $rightPartyX + 3, $partyY + 9, $partyWidth - 6, __('Organization'), (string) ($customer['name'] ?? __('Not set')), $direction);
        $this->writePdfLabelValue($pdf, $rightPartyX + 3, $partyY + 20, $partyWidth - 6, __('Owner'), (string) ($customer['owner_name'] ?? __('Not set')), $direction);
        $this->writePdfLabelValue($pdf, $rightPartyX + 3, $partyY + 31, $partyWidth - 6, __('Email'), (string) ($customer['email'] ?? __('Not set')), 'ltr');

        $overviewY = $partyY + $partyHeight + $gap;
        $overviewWidth = ($pageWidth - ($gap * 3)) / 4;
        $overviewHeight = 18;
        $overviewFields = [
            [__('Subscription plan'), (string) ($subscription['plan_name'] ?? __('Not set')), $direction],
            [__('Billing period'), (string) ($subscription['period'] ?? __('Not set')), $direction],
            [__('Payment method'), (string) ($payment['method_label'] ?? __('Payment')), $direction],
            [__('Paid at'), (string) ($payment['paid_at'] ?? __('Not set')), 'ltr'],
        ];

        foreach ($overviewFields as $index => [$label, $value, $fieldDirection]) {
            $x = $pageX + ($index * ($overviewWidth + $gap));

            $this->drawPdfBox($pdf, $x, $overviewY, $overviewWidth, $overviewHeight, [248, 250, 252]);
            $this->writePdfLabelValue($pdf, $x + 2.5, $overviewY + 2, $overviewWidth - 5, $label, $value, $fieldDirection);
        }

        $itemsY = $overviewY + $overviewHeight + $gap;
        $headerHeight = 9;
        $rowHeight = max(10.0, min(16.0, 8.0 + (count($items) * 2.0)));
        $itemsHeight = $headerHeight + ($rowHeight * max(1, count($items)));
        $col1 = 50.0;
        $col2 = 95.0;
        $col3 = $pageWidth - $col1 - $col2;

        $this->writePdfLine($pdf, $pageX, $itemsY - 4, $pageWidth, __('Invoice items'), 8.2, 'B', [71, 85, 105], $direction);
        $this->drawPdfBox($pdf, $pageX, $itemsY, $pageWidth, $itemsHeight);
        $this->drawPdfBox($pdf, $pageX, $itemsY, $pageWidth, $headerHeight, [248, 250, 252]);
        $pdf->Line($pageX + $col1, $itemsY, $pageX + $col1, $itemsY + $itemsHeight);
        $pdf->Line($pageX + $col1 + $col2, $itemsY, $pageX + $col1 + $col2, $itemsY + $itemsHeight);
        $pdf->Line($pageX, $itemsY + $headerHeight, $pageX + $pageWidth, $itemsY + $headerHeight);

        $this->writePdfLine($pdf, $pageX + 2.5, $itemsY + 2.2, $col1 - 5, __('Item'), 7.8, 'B', [71, 85, 105], $direction);
        $this->writePdfLine($pdf, $pageX + $col1 + 2.5, $itemsY + 2.2, $col2 - 5, __('Description'), 7.8, 'B', [71, 85, 105], $direction);
        $this->writePdfLine($pdf, $pageX + $col1 + $col2 + 2.5, $itemsY + 2.2, $col3 - 5, __('Amount'), 7.8, 'B', [71, 85, 105], 'ltr', 'R');

        $itemRows = $items === [] ? [[
            'label' => __('Not set'),
            'description' => __('No invoice items available.'),
            'amount' => '0.00',
        ]] : $items;

        foreach (array_values($itemRows) as $index => $item) {
            $rowY = $itemsY + $headerHeight + ($index * $rowHeight);

            if ($index > 0) {
                $pdf->Line($pageX, $rowY, $pageX + $pageWidth, $rowY);
            }

            $this->writePdfParagraph($pdf, $pageX + 2.5, $rowY + 2.2, $col1 - 5, (string) ($item['label'] ?? __('Not set')), 9.0, [15, 23, 42], $direction);
            $this->writePdfParagraph($pdf, $pageX + $col1 + 2.5, $rowY + 2.2, $col2 - 5, (string) ($item['description'] ?? '—'), 8.6, [100, 116, 139], $direction);
            $this->writePdfLine($pdf, $pageX + $col1 + $col2 + 2.5, $rowY + 2.2, $col3 - 5, (string) ($item['amount'] ?? '0.00'), 9.2, 'B', [15, 23, 42], 'ltr', 'R');
        }

        $paymentY = $itemsY + $itemsHeight + $gap;
        $boxHeight = 44.0;
        $summaryX = $pageX + $partyWidth + $gap;

        $this->drawPdfBox($pdf, $pageX, $paymentY, $partyWidth, $boxHeight);
        $this->writePdfLine($pdf, $pageX + 3, $paymentY + 3, $partyWidth - 6, __('Payment'), 8.2, 'B', [71, 85, 105], $direction);
        $this->writePdfLabelValue($pdf, $pageX + 3, $paymentY + 9, $partyWidth - 6, __('Reference'), (string) ($payment['reference'] ?? __('Not set')), 'ltr');
        $this->writePdfLabelValue($pdf, $pageX + 3, $paymentY + 20, $partyWidth - 6, __('Payment method'), (string) ($payment['method_label'] ?? __('Payment')), $direction);
        $this->writePdfLabelValue($pdf, $pageX + 3, $paymentY + 31, $partyWidth - 6, __('Paid at'), (string) ($payment['paid_at'] ?? __('Not set')), 'ltr');

        $this->drawPdfBox($pdf, $summaryX, $paymentY, $partyWidth, $boxHeight);
        $this->writePdfLine($pdf, $summaryX + 3, $paymentY + 3, $partyWidth - 6, __('Invoice summary'), 8.2, 'B', [71, 85, 105], $direction);

        foreach ($summaryRows as $index => $row) {
            $rowY = $paymentY + 10 + ($index * 8.5);

            if ($index > 0) {
                $pdf->Line($summaryX + 2.5, $rowY - 1.2, $summaryX + $partyWidth - 2.5, $rowY - 1.2);
            }

            $valueColor = !empty($row['total']) ? [29, 78, 216] : [15, 23, 42];
            $labelColor = !empty($row['total']) ? [29, 78, 216] : [15, 23, 42];
            $labelStyle = !empty($row['total']) ? 'B' : '';
            $valueStyle = !empty($row['total']) ? 'B' : '';

            $this->writePdfLine($pdf, $summaryX + 3, $rowY, 35, (string) ($row['label'] ?? ''), 8.8, $labelStyle, $labelColor, $direction);
            $this->writePdfLine($pdf, $summaryX + $partyWidth - 38, $rowY, 35, (string) ($row['value'] ?? '0.00'), 9.0, $valueStyle, $valueColor, 'ltr', 'R');
        }

        $this->writePdfLine(
            $pdf,
            $pageX,
            $pageY + $pageHeight - 8,
            $pageWidth,
            __('Secure document generated from the subscription billing system.'),
            7.6,
            '',
            [100, 116, 139],
            $direction,
            'C'
        );

        if ($isRtl) {
            $pdf->SetDirectionality('rtl');
        } else {
            $pdf->SetDirectionality('ltr');
        }
    }

    private function drawPdfBox(Mpdf $pdf, float $x, float $y, float $w, float $h, ?array $fill = null, ?array $border = null): void
    {
        $borderColor = $border ?? [219, 226, 234];
        $pdf->SetDrawColor($borderColor[0], $borderColor[1], $borderColor[2]);

        if ($fill !== null) {
            $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
            $pdf->Rect($x, $y, $w, $h, 'DF');

            return;
        }

        $pdf->Rect($x, $y, $w, $h);
    }

    private function writePdfLabelValue(
        Mpdf $pdf,
        float $x,
        float $y,
        float $w,
        string $label,
        string $value,
        string $direction,
        array $labelColor = [100, 116, 139],
        array $valueColor = [15, 23, 42],
        ?string $valueAlign = null
    ): void {
        $align = $direction === 'ltr' ? 'L' : 'R';

        $this->writePdfLine($pdf, $x, $y, $w, $label, 7.8, '', $labelColor, $direction, $align);
        $this->writePdfLine($pdf, $x, $y + 4.6, $w, $value, 10.0, 'B', $valueColor, $direction, $valueAlign ?? $align);
    }

    private function writePdfParagraph(
        Mpdf $pdf,
        float $x,
        float $y,
        float $w,
        string $text,
        float $fontSize,
        array $color,
        string $direction,
        ?string $align = null
    ): void {
        $pdf->SetDirectionality($direction);
        $pdf->SetTextColor($color[0], $color[1], $color[2]);
        $pdf->SetFont('botzoarabic', '', $fontSize);
        $pdf->SetXY($x, $y);
        $pdf->MultiCell($w, 4.1, $text, 0, $align ?? ($direction === 'ltr' ? 'L' : 'R'), false);
    }

    private function writePdfLine(
        Mpdf $pdf,
        float $x,
        float $y,
        float $w,
        string $text,
        float $fontSize,
        string $fontStyle,
        array $color,
        string $direction,
        ?string $align = null
    ): void {
        $pdf->SetDirectionality($direction);
        $pdf->SetTextColor($color[0], $color[1], $color[2]);
        $pdf->SetFont('botzoarabic', $fontStyle, $fontSize);
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, 4.2, $text, 0, 0, $align ?? ($direction === 'ltr' ? 'L' : 'R'));
    }
}
