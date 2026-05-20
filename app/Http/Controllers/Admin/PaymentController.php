<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller as BaseController;
use App\Services\BillingInvoiceService;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentController extends BaseController
{
    private $RoleService;

    /**
     * PaymentController constructor.
     *
     * @param BillingService $billingService
     */
    public function __construct(BillingService $billingService, BillingInvoiceService $billingInvoiceService)
    {
        $this->billingService = $billingService;
        $this->billingInvoiceService = $billingInvoiceService;
    }

    public function index(Request $request)
    {
        $activeView = in_array((string) $request->query('view', 'invoices'), ['invoices', 'activity'], true)
            ? (string) $request->query('view', 'invoices')
            : 'invoices';

        return Inertia::render('Admin/Payment/Index', [
            'title' => __('Invoices'),
            'activeView' => $activeView,
            'invoiceRows' => $activeView === 'invoices' ? $this->billingInvoiceService->list($request) : null,
            'billingActivity' => $activeView === 'activity' ? $this->billingService->getTransactions($request) : null,
            'filters' => [
                'view' => $activeView,
                'search' => (string) $request->query('search', ''),
                'organization_uuid' => (string) $request->query('organization_uuid', ''),
                'date_from' => (string) $request->query('date_from', ''),
                'date_to' => (string) $request->query('date_to', ''),
            ],
            'organizationOptions' => $this->billingService->organizationOptions(),
        ]);
    }

    public function showInvoice(Request $request, string $invoiceUuid)
    {
        $document = $this->billingInvoiceService->documentForAdmin($invoiceUuid);

        return Inertia::render('Admin/Billing/InvoiceShow', [
            'title' => __('Invoice'),
            'invoice' => $document,
            'backUrl' => route('payment-logs.index'),
            'backLabel' => __('Back to billing hub'),
            'printUrl' => route('payment-logs.invoices.print', ['invoice' => $invoiceUuid]),
            'downloadUrl' => route('payment-logs.invoices.download', ['invoice' => $invoiceUuid]),
        ]);
    }

    public function previewInvoice(Request $request, string $invoiceUuid)
    {
        $document = $this->billingInvoiceService->documentForAdmin($invoiceUuid);

        return view('billing.invoice-document', [
            'invoice' => $document,
            'printMode' => false,
            'downloadMode' => false,
            'embeddedMode' => true,
            'backUrl' => null,
            'printUrl' => route('payment-logs.invoices.print', ['invoice' => $invoiceUuid]),
            'downloadUrl' => route('payment-logs.invoices.download', ['invoice' => $invoiceUuid]),
            'title' => __('Invoice'),
        ]);
    }

    public function printInvoice(Request $request, string $invoiceUuid)
    {
        $document = $this->billingInvoiceService->documentForAdmin($invoiceUuid);

        return $this->billingInvoiceService->inlineResponse([
            'invoice' => $document,
            'printMode' => true,
            'downloadMode' => false,
            'backUrl' => route('payment-logs.invoices.show', ['invoice' => $invoiceUuid]),
            'printUrl' => route('payment-logs.invoices.print', ['invoice' => $invoiceUuid]),
            'downloadUrl' => route('payment-logs.invoices.download', ['invoice' => $invoiceUuid]),
            'title' => __('Invoice'),
        ]);
    }

    public function downloadInvoice(Request $request, string $invoiceUuid)
    {
        $document = $this->billingInvoiceService->documentForAdmin($invoiceUuid);

        return $this->billingInvoiceService->downloadResponse([
            'invoice' => $document,
            'printMode' => false,
            'downloadMode' => true,
            'backUrl' => route('payment-logs.invoices.show', ['invoice' => $invoiceUuid]),
            'printUrl' => route('payment-logs.invoices.print', ['invoice' => $invoiceUuid]),
            'downloadUrl' => route('payment-logs.invoices.download', ['invoice' => $invoiceUuid]),
            'title' => __('Invoice'),
        ]);
    }
}
