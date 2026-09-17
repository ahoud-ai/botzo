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
            storage_path('fonts/src'),
            public_path('fonts/Tajawal'),
            resource_path('fonts/ping-ar-lt'),
            resource_path('fonts/Outfit'),
            is_dir('C:\Windows\Fonts') ? 'C:\Windows\Fonts' : null,
        ]));
    }

    private function invoicePdfFontData(): array
    {
        // IBM Plex Sans Arabic is the same face used by the Meta verification agreement PDF
        // (see resources/views/pdf/meta-verification-agreement.blade.php) — used first here too
        // so every customer-facing PDF this system generates shares one typographic identity.
        $plexRegular = storage_path('fonts/src/IBMPlexSansArabic-Regular.ttf');
        $plexBold = storage_path('fonts/src/IBMPlexSansArabic-Bold.ttf');
        $tajawalRegular = public_path('fonts/Tajawal/Tajawal-Regular.ttf');
        $tajawalBold = public_path('fonts/Tajawal/Tajawal-Bold.ttf');
        $bundledRegular = resource_path('fonts/ping-ar-lt/ping-ar-lt-regular.otf');
        $bundledBold = resource_path('fonts/ping-ar-lt/ping-ar-lt-bold.otf');
        $windowsRegular = 'C:\Windows\Fonts\tahoma.ttf';
        $windowsBold = 'C:\Windows\Fonts\tahomabd.ttf';

        $fonts = [];

        if (is_file($plexRegular) && is_file($plexBold)) {
            $fonts['plexarabicpdf'] = [
                'R' => 'IBMPlexSansArabic-Regular.ttf',
                'B' => 'IBMPlexSansArabic-Bold.ttf',
                'useOTL' => 0xFF,
                'useKashida' => 75,
            ];
        }

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

        if (is_file(storage_path('fonts/src/IBMPlexSansArabic-Regular.ttf'))) {
            return 'plexarabicpdf, dejavusans, sans-serif';
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
            ? implode('<br>', array_map(fn ($line) => $this->isolateBidiValue((string) $line, $direction), $vendorAddressLines))
            : $this->escapePdfHtml(__('Not set'));
        $customerAddress = $customerAddressLines !== []
            ? implode('<br>', array_map(fn ($line) => $this->isolateBidiValue((string) $line, $direction), $customerAddressLines))
            : $this->escapePdfHtml(__('Not set'));
        $brandInitial = Str::upper(Str::substr(trim($brandName), 0, 1));
        $brandHasArabic = (bool) preg_match('/\p{Arabic}/u', $brandName);

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

        $logoPngPath = public_path('bimi/botzo-logo-new-512.png');
        $logoSvgPath = public_path('bimi/botzo-logo.svg');
        $logoSrc = is_file($logoPngPath)
            ? 'file:///' . str_replace('\\', '/', $logoPngPath)
            : (is_file($logoSvgPath) ? 'file:///' . str_replace('\\', '/', $logoSvgPath) : null);
        $logoHtml = $logoSrc
            ? '<img src="' . $this->escapePdfHtml($logoSrc) . '" alt="' . $this->escapePdfHtml($brandName) . '" style="width:30px;height:30px;display:block;">'
            : '<div style="width:38px;height:38px;border-radius:11px;background:linear-gradient(135deg,#A4ED41,#63DB9B 45%,#21C8F5 75%,#4230F8);text-align:center;line-height:38px;font-size:17px;font-weight:bold;color:#ffffff;">' . $this->escapePdfHtml($brandInitial !== '' ? $brandInitial : 'B') . '</div>';

        // Brand palette matches the Botzo mark's own gradient (lime -> teal -> cyan -> indigo),
        // reduced to one readable solid accent for text/badges plus the full gradient for accent
        // bars only — mirroring the visual language of the Meta verification agreement PDF,
        // whose overall composition (big bold price box, chip row, solid official-info footer
        // bar) this template now follows directly rather than the denser many-small-boxes layout
        // it used before.
        $accent = '#0E9F6E';
        $accentDark = '#046C4E';
        $accentSoft = '#EAFBF3';
        $accentBorder = '#BEEFD9';
        $brandGradient = 'linear-gradient(90deg,#A4ED41 0%,#63DB9B 34%,#21C8F5 68%,#4230F8 100%)';

        $html = '<html lang="' . $this->escapePdfHtml($locale) . '" dir="' . $this->escapePdfHtml($direction) . '"><head><meta charset="utf-8"><title>'
            . $this->escapePdfHtml($title) . ' - ' . $this->escapePdfHtml($documentNumber)
            . '</title><style>'
            . 'body{font-family:' . $bodyFont . ';font-size:12px;line-height:1.55;color:#1A2332;margin:0;background:#ffffff;}'
            . '.sheet{background:#ffffff;}'
            . '.brand-row{width:100%;border-collapse:collapse;}'
            . '.brand-row td{vertical-align:middle;}'
            . '.logo-cell{width:46px;}'
            . '.brand-name{font-size:15px;font-weight:bold;color:#0A0F1C;letter-spacing:' . ($brandHasArabic ? 'normal' : '0.01em') . ';}'
            . '.kicker{display:inline-block;padding:6px 14px;border:1px solid ' . $accentBorder . ';background:' . $accentSoft . ';color:' . $accentDark . ';font-size:10px;font-weight:bold;border-radius:999px;}'
            . '.title{font-size:27px;font-weight:bold;color:#0A0F1C;margin:14px 0 5px;}'
            . '.note{font-size:11px;color:#8899AA;line-height:1.6;margin-bottom:11px;}'
            . '.price-box{border:1px solid ' . $accentBorder . ';background:' . $accentSoft . ';border-radius:16px;padding:12px 18px;margin-bottom:10px;}'
            . '.price-label{font-size:10.5px;color:' . $accentDark . ';font-weight:bold;}'
            . '.price-value{font-size:28px;font-weight:bold;color:' . $accentDark . ';margin:2px 0;font-variant-numeric:tabular-nums lining-nums;}'
            . '.price-note{font-size:9.5px;color:#3E8E76;}'
            . '.chips-table{width:100%;border-collapse:collapse;margin-bottom:9px;}'
            . '.chips-table td{width:33.33%;vertical-align:top;}'
            . '.chip-start{padding-' . ($direction === 'rtl' ? 'left' : 'right') . ':7px;}'
            . '.chip-mid{padding-left:3.5px;padding-right:3.5px;}'
            . '.chip-end{padding-' . ($direction === 'rtl' ? 'right' : 'left') . ':7px;}'
            . '.chip{border:1px solid #CFD8E3;border-radius:11px;background:#ffffff;padding:8px 12px;}'
            . '.chip-status{border-color:' . $accentBorder . ';background:' . $accentSoft . ';}'
            . '.chip-label{font-size:9.5px;color:#8899AA;}'
            . '.chip-value{margin-top:3px;font-size:12.2px;font-weight:bold;color:#0A0F1C;font-variant-numeric:tabular-nums lining-nums;}'
            . '.chip-status .chip-value{color:' . $accentDark . ';}'
            . '.divider{height:1px;background:#E5EBF3;margin:11px 0;}'
            . '.section{margin-bottom:13px;}'
            . '.section-title{font-size:15px;font-weight:bold;color:#0A0F1C;margin:0 0 3px;}'
            . '.section-note{font-size:10.5px;color:#8899AA;margin:0 0 8px;}'
            . '.dual-table{width:100%;border-collapse:collapse;table-layout:fixed;}'
            . '.dual-table td{width:50%;vertical-align:top;}'
            . '.dual-start{padding-' . ($direction === 'rtl' ? 'left' : 'right') . ':9px;}'
            . '.dual-end{padding-' . ($direction === 'rtl' ? 'right' : 'left') . ':9px;}'
            . '.panel{border:1px solid #CFD8E3;border-radius:14px;background:#ffffff;padding:12px 15px;}'
            . '.panel-title{font-size:12.5px;font-weight:bold;color:#0A0F1C;margin-bottom:5px;}'
            . '.info-table{width:100%;border-collapse:collapse;}'
            . '.info-table tr+tr td{border-top:1px solid #EDF2F7;}'
            . '.info-label{width:36%;padding:6px 0;font-size:10.5px;color:#8899AA;vertical-align:top;}'
            . '.info-value{padding:6px 0;font-size:12.2px;font-weight:bold;color:#0A0F1C;line-height:1.55;vertical-align:top;font-variant-numeric:tabular-nums lining-nums;}'
            . '.muted-copy{padding:6px 0;font-size:11px;color:#445566;line-height:1.55;vertical-align:top;}'
            . '.items-table{width:100%;border-collapse:collapse;margin-top:2px;}'
            . '.items-table th,.items-table td{border:1px solid #CFD8E3;padding:9px 12px;vertical-align:top;text-align:' . $textAlign . ';line-height:1.6;font-size:11.5px;}'
            . '.items-table th{background:' . $accentSoft . ';color:' . $accentDark . ';font-size:10.5px;font-weight:bold;}'
            . '.amount-cell{text-align:' . $oppositeAlign . ';white-space:nowrap;font-weight:bold;font-variant-numeric:tabular-nums lining-nums;}'
            . '.ltr{direction:ltr;unicode-bidi:isolate;text-align:left;}'
            . '.summary-table{width:100%;border-collapse:collapse;}'
            . '.summary-table td{border:1px solid #CFD8E3;padding:11px 13px;line-height:1.8;}'
            . '.summary-label{text-align:' . $textAlign . ';font-size:11px;color:#445566;}'
            . '.summary-value{text-align:' . $oppositeAlign . ';font-size:12.4px;font-weight:bold;color:#0A0F1C;white-space:nowrap;font-variant-numeric:tabular-nums lining-nums;}'
            . '.summary-total td{background:' . $accent . ';border-color:' . $accent . ';font-weight:bold;padding:11px 13px;}'
            . '.summary-total .summary-label,.summary-total .summary-value{color:#ffffff;font-size:15px;}'
            . '.official-box{background:' . $accentDark . ';border-radius:14px;padding:14px 18px;color:#ffffff;}'
            . '.official-table{width:100%;border-collapse:collapse;}'
            . '.official-note{font-size:10px;opacity:0.82;}'
            . '.official-name{font-size:12.5px;font-weight:bold;margin-top:2px;}'
            . '.official-end{text-align:' . $oppositeAlign . ';font-size:10px;opacity:0.82;vertical-align:middle;}'
            . '</style></head><body><div class="sheet">';

        $html .= '<table class="brand-row"><tr>'
            . '<td class="logo-cell">' . $logoHtml . '</td>'
            . '<td><div class="brand-name">' . $this->isolateBidiValue($brandName, $direction) . '</div></td>'
            . '<td style="text-align:' . $oppositeAlign . ';"><div class="kicker">' . $this->escapePdfHtml(__('Official billing document')) . '</div></td>'
            . '</tr></table>';

        $html .= '<div class="title">' . $this->escapePdfHtml(__('Invoice')) . '</div>'
            . '<div class="note">' . $this->escapePdfHtml(__('A simplified invoice prepared for accounting review, printing, and PDF download.')) . '</div>';

        $html .= '<div class="price-box">'
            . '<div class="price-label">' . $this->escapePdfHtml(__('Total')) . '</div>'
            . '<div class="price-value ltr">' . $this->isolateBidiValue((string) ($summary['total'] ?? '0.00'), $direction) . '</div>'
            . '<div class="price-note">' . $this->escapePdfHtml(__('Includes tax, if applicable')) . '</div>'
            . '</div>';

        $html .= '<table class="chips-table"><tr>'
            . '<td class="chip-start"><div class="chip chip-status"><div class="chip-label">' . $this->escapePdfHtml(__('Status')) . '</div><div class="chip-value">' . $this->escapePdfHtml($statusLabel) . '</div></div></td>'
            . '<td class="chip-mid"><div class="chip"><div class="chip-label">' . $this->escapePdfHtml(__('Invoice no.')) . '</div><div class="chip-value ltr">' . $this->isolateBidiValue($documentNumber, $direction) . '</div></div></td>'
            . '<td class="chip-end"><div class="chip"><div class="chip-label">' . $this->escapePdfHtml(__('Issued date')) . '</div><div class="chip-value ltr">' . $this->isolateBidiValue($issuedAt, $direction) . '</div></div></td>'
            . '</tr></table>';

        $html .= '<table class="chips-table"><tr>'
            . '<td class="chip-start"><div class="chip"><div class="chip-label">' . $this->escapePdfHtml(__('Subscription plan')) . '</div><div class="chip-value">' . $this->isolateBidiValue($planName, $direction) . '</div></div></td>'
            . '<td class="chip-mid"><div class="chip"><div class="chip-label">' . $this->escapePdfHtml(__('Billing period')) . '</div><div class="chip-value">' . $this->isolateBidiValue($billingPeriod, $direction) . '</div></div></td>'
            . '<td class="chip-end"><div class="chip"><div class="chip-label">' . $this->escapePdfHtml(__('Payment method')) . '</div><div class="chip-value">' . $this->isolateBidiValue($paymentMethodLabel, $direction) . '</div></div></td>'
            . '</tr></table>';

        $html .= '<div class="divider"></div>';

        $html .= '<div class="section"><div class="section-title">' . $this->escapePdfHtml(__('Billing parties')) . '</div>'
            . '<div class="section-note">' . $this->escapePdfHtml(__('Essential vendor and customer details required to validate this invoice.')) . '</div>'
            . '<table class="dual-table"><tr>'
            . '<td class="dual-start"><div class="panel">'
            . '<div class="panel-title">' . $this->escapePdfHtml(__('Vendor')) . '</div>'
            . '<table class="info-table">'
            . '<tr><td class="info-label">' . $this->escapePdfHtml(__('Name')) . '</td><td class="info-value">' . $this->isolateBidiValue((string) ($vendor['name'] ?? __('Not set')), $direction) . '</td></tr>'
            . '<tr><td class="info-label">' . $this->escapePdfHtml(__('Tax ID')) . '</td><td class="info-value ltr">' . $this->isolateBidiValue($vendorTaxId, $direction) . '</td></tr>'
            . '<tr><td class="info-label">' . $this->escapePdfHtml(__('Contact')) . '</td><td class="info-value ltr">' . $this->isolateBidiValue((string) $vendorPhones, $direction) . '</td></tr>'
            . '<tr><td class="info-label">' . $this->escapePdfHtml(__('Address')) . '</td><td class="muted-copy">' . $vendorAddress . '</td></tr>'
            . '</table>'
            . '</div></td>'
            . '<td class="dual-end"><div class="panel">'
            . '<div class="panel-title">' . $this->escapePdfHtml(__('Customer')) . '</div>'
            . '<table class="info-table">'
            . '<tr><td class="info-label">' . $this->escapePdfHtml(__('Organization')) . '</td><td class="info-value">' . $this->isolateBidiValue((string) ($customer['name'] ?? __('Not set')), $direction) . '</td></tr>'
            . '<tr><td class="info-label">' . $this->escapePdfHtml(__('Owner')) . '</td><td class="info-value">' . $this->isolateBidiValue((string) ($customer['owner_name'] ?? __('Not set')), $direction) . '</td></tr>'
            . '<tr><td class="info-label">' . $this->escapePdfHtml(__('Email')) . '</td><td class="info-value ltr">' . $this->isolateBidiValue($customerEmail, $direction) . '</td></tr>'
            . '<tr><td class="info-label">' . $this->escapePdfHtml(__('Address')) . '</td><td class="muted-copy">' . $customerAddress . '</td></tr>'
            . '</table>'
            . '</div></td></tr></table></div>';

        $html .= '<div class="section"><div class="section-title">' . $this->escapePdfHtml(__('Invoice items')) . '</div>'
            . '<div class="section-note">' . $this->escapePdfHtml(__('Only the invoice lines needed for business review and accounting approval are shown below.')) . '</div>'
            . '<table class="items-table"><tr>'
            . '<th style="width:24%;">' . $this->escapePdfHtml(__('Item')) . '</th>'
            . '<th style="width:56%;">' . $this->escapePdfHtml(__('Description')) . '</th>'
            . '<th style="width:20%;">' . $this->escapePdfHtml(__('Amount')) . '</th>'
            . '</tr>';

        $itemRows = $items === [] ? [[
            'label' => __('Not set'),
            'description' => __('No invoice items available.'),
            'amount' => '0.00',
        ]] : $items;

        foreach ($itemRows as $item) {
            $html .= '<tr>'
                . '<td>' . $this->isolateBidiValue((string) ($item['label'] ?? __('Not set')), $direction) . '</td>'
                . '<td>' . $this->isolateBidiValue((string) ($item['description'] ?? '—'), $direction) . '</td>'
                . '<td class="amount-cell ltr">' . $this->isolateBidiValue((string) ($item['amount'] ?? '0.00'), $direction) . '</td>'
                . '</tr>';
        }

        $html .= '</table></div>';
        $html .= '<div class="section"><table class="dual-table"><tr>'
            . '<td class="dual-start"><div class="panel">'
            . '<div class="panel-title">' . $this->escapePdfHtml(__('Payment details')) . '</div>'
            . '<table class="info-table">'
            . '<tr><td class="info-label">' . $this->escapePdfHtml(__('Payment method')) . '</td><td class="info-value">' . $this->isolateBidiValue($paymentMethodLabel, $direction) . '</td></tr>'
            . '<tr><td class="info-label">' . $this->escapePdfHtml(__('Reference')) . '</td><td class="info-value ltr">' . $this->isolateBidiValue($paymentReference, $direction) . '</td></tr>'
            . '<tr><td class="info-label">' . $this->escapePdfHtml(__('Paid at')) . '</td><td class="info-value ltr">' . $this->isolateBidiValue($paidAt, $direction) . '</td></tr>'
            . '<tr><td class="info-label">' . $this->escapePdfHtml(__('Billing period')) . '</td><td class="info-value">' . $this->isolateBidiValue($billingPeriod, $direction) . '</td></tr>'
            . '<tr><td class="info-label">' . $this->escapePdfHtml(__('Subscription plan')) . '</td><td class="info-value">' . $this->isolateBidiValue($planName, $direction) . '</td></tr>'
            . '</table>'
            . '</div></td>'
            . '<td class="dual-end"><div class="panel">'
            . '<div class="panel-title">' . $this->escapePdfHtml(__('Invoice summary')) . '</div>'
            . '<table class="summary-table">';

        foreach ($summaryRows as $row) {
            $html .= '<tr' . (!empty($row['total']) ? ' class="summary-total"' : '') . '>'
                . '<td class="summary-label">' . $this->escapePdfHtml((string) ($row['label'] ?? '')) . '</td>'
                . '<td class="summary-value ltr">' . $this->isolateBidiValue((string) ($row['value'] ?? '0.00'), $direction) . '</td>'
                . '</tr>';
        }

        $html .= '</table></div></td></tr></table></div>';

        $html .= '<div class="official-box"><table class="official-table"><tr>'
            . '<td><div class="official-note">' . $this->escapePdfHtml(__('Secure document generated from the subscription billing system.')) . '</div>'
            . '<div class="official-name">' . $this->isolateBidiValue($brandName, $direction) . '</div></td>'
            . '<td class="official-end">' . $this->escapePdfHtml(__('Invoice')) . ' ' . $this->isolateBidiValue($documentNumber, $direction) . '</td>'
            . '</tr></table></div>';
        $html .= '</div></body></html>';

        return $html;
    }

    /**
     * Root-cause fix for mixed-direction invoice values (phone numbers, dates, reference
     * codes, English plan/payment labels) getting visually scrambled by mPDF's bidi
     * reordering when they sit inside an RTL document. CSS `direction:ltr` alone is not
     * enough — verified empirically that only the HTML `dir="ltr"` attribute reliably
     * isolates a run from the surrounding RTL paragraph in mPDF, including inside nested
     * table cells. Only wraps values that contain NO Arabic script, so genuinely-Arabic
     * text (customer/vendor names, translated plan names, item descriptions) is left to
     * flow naturally in the document's own direction rather than being force-misaligned.
     */
    private function isolateBidiValue(?string $value, string $direction): string
    {
        $value = (string) $value;
        $escaped = $this->escapePdfHtml($value);

        if ($direction !== 'rtl' || $value === '' || preg_match('/\p{Arabic}/u', $value)) {
            return $escaped;
        }

        return '<span dir="ltr" style="unicode-bidi:isolate;direction:ltr;">' . $escaped . '</span>';
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
