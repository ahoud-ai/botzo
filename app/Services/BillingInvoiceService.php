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
            ? '<img src="' . $this->escapePdfHtml($logoSrc) . '" alt="' . $this->escapePdfHtml($brandName) . '" style="width:44px;height:44px;display:block;border-radius:10px;object-fit:cover;">'
            : '<div style="width:44px;height:44px;border-radius:10px;background:#034737;text-align:center;line-height:44px;font-size:20px;font-weight:bold;color:#ffffff;">' . $this->escapePdfHtml($brandInitial !== '' ? $brandInitial : 'B') . '</div>';

        // Colors matching InvoiceDetailsShell.vue (Tailwind slate palette)
        $c = [
            'primary'      => '#034737',
            'primaryLight' => '#EAF2F0',
            'primaryBorder'=> '#C4D9D5',
            'ink'          => '#020617',
            'medium'       => '#475569',
            'muted'        => '#64748B',
            'border'       => '#E2E8F0',
            'softBg'       => '#F8FAFC',
            'white'        => '#ffffff',
            'greenBg'      => '#F0FDF4',
            'greenBorder'  => '#BBF7D0',
            'greenText'    => '#15803D',
        ];

        $gap = ($direction === 'rtl' ? 'left' : 'right');
        $endGap = ($direction === 'rtl' ? 'right' : 'left');

        $label = 'font-size:9px;font-weight:bold;text-transform:uppercase;letter-spacing:0.12em;color:' . $c['muted'] . ';';
        $val   = 'font-size:12px;font-weight:bold;color:' . $c['ink'] . ';margin-top:3px;line-height:1.8;';

        $html = '<html lang="' . $this->escapePdfHtml($locale) . '" dir="' . $this->escapePdfHtml($direction) . '"><head><meta charset="utf-8"><title>'
            . $this->escapePdfHtml($title) . ' - ' . $this->escapePdfHtml($documentNumber)
            . '</title><style>'
            . 'body{font-family:' . $bodyFont . ';font-size:11px;line-height:1.8;color:' . $c['ink'] . ';margin:0;padding:16px;background:' . $c['softBg'] . ';}'
            . '.card{background:' . $c['white'] . ';border:1px solid ' . $c['border'] . ';border-radius:14px;margin-bottom:14px;overflow:hidden;}'
            . '.card-body{padding:18px 20px;}'
            . '.t{width:100%;border-collapse:collapse;table-layout:fixed;}'
            . '.t td,.t th{vertical-align:top;}'
            . '.label{' . $label . '}'
            . '.val{' . $val . '}'
            . '.val-primary{' . $val . 'color:' . $c['primary'] . ';font-size:14px;}'
            . '.sec-title{font-size:13px;font-weight:bold;color:' . $c['ink'] . ';margin:0 0 3px;}'
            . '.sec-sub{font-size:10px;color:' . $c['muted'] . ';margin:0 0 12px;}'
            . '.fact-cell{background:' . $c['softBg'] . ';border:1px solid ' . $c['border'] . ';border-radius:10px;padding:11px 13px;}'
            . '.gap-' . $gap . '{padding-' . $gap . ':7px;}'
            . '.gap-' . $endGap . '{padding-' . $endGap . ':7px;}'
            . '.items-th{background:' . $c['softBg'] . ';font-size:9px;font-weight:bold;text-transform:uppercase;letter-spacing:0.12em;color:' . $c['muted'] . ';padding:10px 12px;text-align:' . $textAlign . ';border-bottom:1px solid ' . $c['border'] . ';}'
            . '.items-td{padding:11px 12px;font-size:11px;color:' . $c['medium'] . ';border-bottom:1px solid ' . $c['border'] . ';text-align:' . $textAlign . ';}'
            . '.items-td-bold{padding:11px 12px;font-size:11px;font-weight:bold;color:' . $c['ink'] . ';border-bottom:1px solid ' . $c['border'] . ';text-align:' . $textAlign . ';}'
            . '.amount-td{text-align:' . $oppositeAlign . ';direction:ltr;}'
            . '.row-item{background:' . $c['softBg'] . ';border:1px solid ' . $c['border'] . ';border-radius:9px;padding:10px 12px;margin-bottom:7px;}'
            . '.row-total{background:' . $c['primaryLight'] . ';border:1px solid ' . $c['primaryBorder'] . ';border-radius:9px;padding:10px 12px;}'
            . '.row-item-table{width:100%;border-collapse:collapse;}'
            . '.row-item-table td{vertical-align:middle;}'
            . '.ltr{direction:ltr;}'
            . '.status-badge{display:inline-block;background:' . $c['greenBg'] . ';border:1px solid ' . $c['greenBorder'] . ';color:' . $c['greenText'] . ';font-size:9.5px;font-weight:bold;padding:4px 10px;border-radius:999px;}'
            . '.party-row{background:' . $c['white'] . ';border:1px solid ' . $c['border'] . ';border-radius:8px;padding:9px 11px;margin-bottom:6px;}'
            . '.footer-bar{height:3px;background:linear-gradient(90deg,#25D366,#00E5FF,#1877F2,#7C3AED);border-radius:2px;margin-bottom:10px;}'
            . '.footer-txt{font-size:9.5px;color:' . $c['muted'] . ';}'
            . '</style></head><body>';

        // ── Header card ──────────────────────────────────────────
        $html .= '<div class="card"><div class="card-body">';
        $html .= '<table class="t"><tr>'
            . '<td style="width:52px;padding-' . $gap . ':12px;">' . $logoHtml . '</td>'
            . '<td style="vertical-align:middle;">'
            .   '<div style="font-size:11px;font-weight:bold;color:' . $c['ink'] . ';letter-spacing:0.05em;">' . $this->escapePdfHtml(mb_strtoupper($brandName)) . '</div>'
            .   '<div style="font-size:22px;font-weight:bold;color:' . $c['ink'] . ';line-height:1.3;">' . $this->escapePdfHtml(__('Invoice')) . '</div>'
            .   '<div style="font-size:10px;color:' . $c['muted'] . ';margin-top:2px;" class="ltr">' . $this->escapePdfHtml($documentNumber) . '</div>'
            . '</td>'
            . '<td style="text-align:' . $oppositeAlign . ';vertical-align:top;">'
            .   '<div class="status-badge">' . $this->escapePdfHtml($statusLabel) . '</div>'
            . '</td>'
            . '</tr></table>';

        // 4-col key facts
        $html .= '<table class="t" style="margin-top:14px;"><tr>'
            . '<td style="width:25%;padding-' . $gap . ':7px;"><div class="fact-cell"><div class="label">' . $this->escapePdfHtml(__('Invoice no.')) . '</div><div class="val ltr">' . $this->escapePdfHtml($documentNumber) . '</div></div></td>'
            . '<td style="width:25%;padding-left:7px;padding-right:7px;"><div class="fact-cell"><div class="label">' . $this->escapePdfHtml(__('Issued date')) . '</div><div class="val ltr">' . $this->escapePdfHtml($issuedAt) . '</div></div></td>'
            . '<td style="width:25%;padding-left:7px;padding-right:7px;"><div class="fact-cell"><div class="label">' . $this->escapePdfHtml(__('Plan')) . '</div><div class="val">' . $this->escapePdfHtml($planName) . '</div></div></td>'
            . '<td style="width:25%;padding-' . $endGap . ':7px;"><div class="fact-cell"><div class="label">' . $this->escapePdfHtml(__('Total')) . '</div><div class="val-primary ltr">' . $this->escapePdfHtml((string) ($summary['total'] ?? '0.00')) . '</div></div></td>'
            . '</tr></table>';
        $html .= '</div></div>';

        // ── Invoice items card ────────────────────────────────────
        $html .= '<div class="card"><div class="card-body">';
        $html .= '<div class="sec-title">' . $this->escapePdfHtml(__('Invoice items')) . '</div>';
        $html .= '<div class="sec-sub">' . $this->escapePdfHtml(__('Only the billed lines required for review are listed here.')) . '</div>';

        $itemRows = $items === [] ? [[
            'label'       => __('Not set'),
            'description' => __('No invoice items available.'),
            'amount'      => '0.00',
        ]] : $items;

        $html .= '<table class="t" style="border:1px solid ' . $c['border'] . ';border-radius:10px;overflow:hidden;">'
            . '<tr>'
            . '<th class="items-th" style="width:22%;">' . $this->escapePdfHtml(__('Item')) . '</th>'
            . '<th class="items-th" style="width:58%;">' . $this->escapePdfHtml(__('Description')) . '</th>'
            . '<th class="items-th amount-td" style="width:20%;">' . $this->escapePdfHtml(__('Amount')) . '</th>'
            . '</tr>';
        foreach ($itemRows as $item) {
            $html .= '<tr>'
                . '<td class="items-td-bold">' . $this->escapePdfHtml((string) ($item['label'] ?? '—')) . '</td>'
                . '<td class="items-td">' . $this->escapePdfHtml((string) ($item['description'] ?? '—')) . '</td>'
                . '<td class="items-td-bold amount-td">' . $this->escapePdfHtml((string) ($item['amount'] ?? '0.00')) . '</td>'
                . '</tr>';
        }
        $html .= '</table>';
        $html .= '</div></div>';

        // ── Payment summary + Invoice totals ──────────────────────
        $html .= '<table class="t" style="margin-bottom:14px;"><tr>'
            . '<td style="width:55%;padding-' . $gap . ':0;vertical-align:top;">'
            .   '<div class="card" style="margin-bottom:0;">'
            .   '<div class="card-body">'
            .   '<div class="sec-title">' . $this->escapePdfHtml(__('Payment summary')) . '</div>'
            .   '<div class="sec-sub" style="margin-bottom:10px;">' . $this->escapePdfHtml(__('Payment and plan information.')) . '</div>';

        $paymentRows = [
            [__('Payment method'), $paymentMethodLabel, false],
            [__('Reference'),       $paymentReference,   true],
            [__('Paid at'),         $paidAt,             true],
            [__('Billing period'),  $billingPeriod,      false],
            [__('Plan'),            $planName,           false],
        ];
        foreach ($paymentRows as [$lbl, $v, $ltrVal]) {
            $html .= '<div class="party-row"><div class="label">' . $this->escapePdfHtml($lbl) . '</div>'
                . '<div class="val' . ($ltrVal ? ' ltr' : '') . '">' . $this->escapePdfHtml($v) . '</div></div>';
        }

        $html .= '</div></div></td>'
            . '<td style="width:7%;"></td>'
            . '<td style="width:38%;padding-' . $endGap . ':0;vertical-align:top;">'
            .   '<div class="card" style="margin-bottom:0;">'
            .   '<div class="card-body">'
            .   '<div class="sec-title">' . $this->escapePdfHtml(__('Invoice totals')) . '</div>'
            .   '<div class="sec-sub" style="margin-bottom:10px;">' . $this->escapePdfHtml(__('Totals for accounting review.')) . '</div>';

        foreach ($summaryRows as $row) {
            $isTotal = !empty($row['total']);
            $html .= '<div class="' . ($isTotal ? 'row-total' : 'row-item') . '">'
                . '<table class="row-item-table"><tr>'
                . '<td style="font-size:11px;' . ($isTotal ? 'font-weight:bold;color:' . $c['primary'] : 'color:' . $c['medium']) . ';">' . $this->escapePdfHtml((string) ($row['label'] ?? '')) . '</td>'
                . '<td style="text-align:' . $oppositeAlign . ';font-size:' . ($isTotal ? '13' : '11') . 'px;font-weight:bold;' . ($isTotal ? 'color:' . $c['primary'] : 'color:' . $c['ink']) . ';direction:ltr;" class="ltr">' . $this->escapePdfHtml((string) ($row['value'] ?? '0.00')) . '</td>'
                . '</tr></table>'
                . '</div>';
        }

        $html .= '</div></div></td></tr></table>';

        // ── Billing parties card ──────────────────────────────────
        $html .= '<div class="card"><div class="card-body">';
        $html .= '<div class="sec-title">' . $this->escapePdfHtml(__('Billing parties')) . '</div>';
        $html .= '<div class="sec-sub">' . $this->escapePdfHtml(__('Vendor and customer details for finance review.')) . '</div>';

        $html .= '<table class="t"><tr>'
            . '<td style="width:50%;padding-' . $gap . ':0;vertical-align:top;">'
            .   '<div style="background:' . $c['softBg'] . ';border:1px solid ' . $c['border'] . ';border-radius:10px;padding:14px 16px;">'
            .   '<div style="font-size:12px;font-weight:bold;color:' . $c['ink'] . ';margin-bottom:10px;">' . $this->escapePdfHtml(__('Vendor')) . '</div>';

        foreach ([
            [__('Name'),   $vendor['name'] ?? __('Not set'), false],
            [__('Tax ID'), $vendorTaxId,                     true],
            [__('Phone'),  $vendorPhones,                    true],
            [__('Address'),$vendorAddress,                   false, true],
        ] as $r) {
            $html .= '<div class="party-row"><div class="label">' . $this->escapePdfHtml($r[0]) . '</div>'
                . '<div class="val' . (!empty($r[2]) ? ' ltr' : '') . '">' . (!empty($r[3]) ? $r[1] : $this->escapePdfHtml($r[1])) . '</div></div>';
        }
        $html .= '</div></td>'
            . '<td style="width:4%;"></td>'
            . '<td style="width:46%;vertical-align:top;">'
            .   '<div style="background:' . $c['softBg'] . ';border:1px solid ' . $c['border'] . ';border-radius:10px;padding:14px 16px;">'
            .   '<div style="font-size:12px;font-weight:bold;color:' . $c['ink'] . ';margin-bottom:10px;">' . $this->escapePdfHtml(__('Customer')) . '</div>';

        foreach ([
            [__('Organization'), $customer['name'] ?? __('Not set'),       false],
            [__('Owner'),        $customer['owner_name'] ?? __('Not set'), false],
            [__('Email'),        $customerEmail,                            true],
            [__('Address'),      $customerAddress,                          false, true],
        ] as $r) {
            $html .= '<div class="party-row"><div class="label">' . $this->escapePdfHtml($r[0]) . '</div>'
                . '<div class="val' . (!empty($r[2]) ? ' ltr' : '') . '">' . (!empty($r[3]) ? $r[1] : $this->escapePdfHtml($r[1])) . '</div></div>';
        }
        $html .= '</div></td></tr></table>';
        $html .= '</div></div>';

        // ── Footer ────────────────────────────────────────────────
        $html .= '<div style="padding:0 4px;">'
            . '<div class="footer-bar"></div>'
            . '<table class="t"><tr>'
            . '<td class="footer-txt">' . $this->escapePdfHtml(__('Secure document generated from the subscription billing system.')) . '</td>'
            . '<td class="footer-txt" style="text-align:' . $oppositeAlign . ';font-weight:bold;color:' . $c['medium'] . ';">' . $this->escapePdfHtml($brandName) . '</td>'
            . '</tr></table>'
            . '</div>';

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
