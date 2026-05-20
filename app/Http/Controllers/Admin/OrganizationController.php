<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreOrganization;
use App\Models\SubscriptionPlan;
use App\Services\BillingInvoiceService;
use App\Services\OrganizationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OrganizationController extends BaseController
{
    private $organizationService;
    private $role;

    /**
     * OrganizationController constructor.
     *
     * @param UserService $organizationService
     */
    public function __construct()
    {
        $this->organizationService = new OrganizationService();
    }

    /**
     * Display a listing of organizations.
     *
     * @param Request $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $indexRequest = clone $request;
        $resolvedType = $request->query('organization_type', 'main');
        $indexRequest->merge([
            'organization_type' => $resolvedType,
        ]);

        return Inertia::render('Admin/Organization/Index', [
            'title' => __('Organizations'),
            'allowCreate' => true,
            'rows' => $this->organizationService->get($indexRequest), 
            'summary' => $this->organizationService->getDirectorySummary(),
            'plans' => SubscriptionPlan::query()
                ->whereNull('deleted_at')
                ->where('status', 'active')
                ->get(['uuid', 'name', 'metadata'])
                ->sort(function (SubscriptionPlan $left, SubscriptionPlan $right) {
                    $rankComparison = $left->tierRank() <=> $right->tierRank();

                    if ($rankComparison !== 0) {
                        return $rankComparison;
                    }

                    return strcasecmp((string) $left->name, (string) $right->name);
                })
                ->values()
                ->map(fn (SubscriptionPlan $plan) => [
                    'uuid' => $plan->uuid,
                    'name' => $plan->name,
                ]),
            'filters' => array_merge($request->all(), [
                'organization_type' => $resolvedType,
            ])
        ]);
    }

    /**
     * Display the specified organization.
     *
     * @param string $uuid
     * @return \Inertia\Response
     */
    public function show(Request $request, $uuid = NULL, $mode = NULL)
    {
        $res = $this->organizationService->getByUuid($request, $uuid);
        return Inertia::render('Admin/Organization/Show', [
            'title' => __('Organization'),
            'organization' => $res['organization'], 
            'users' => $res['users'],
            'plans' => $res['plans'], 
            'parentOrganizations' => $res['parentOrganizations'],
            'invoices' => $res['invoiceRows'],
            'billingActivity' => $res['billing'],
            'profileSummary' => $res['profileSummary'],
            'teamSummary' => $res['teamSummary'],
            'usageSummary' => $res['usageSummary'],
            'billingSummary' => $res['billingSummary'],
            'branches' => $res['branches'],
            'mode' => $mode,
            'filters' => $request->all()
        ]);
    }

    /**
     * Display Form
     *
     * @param $request
     */
    public function create(Request $request)
    {
        $catalog = $this->organizationService->adminProvisioningPayload();

        return Inertia::render('Admin/Organization/Create', [
            'title' => __('Create organization'),
            'plans' => $catalog['plans'],
            'parentOrganizations' => $catalog['parentOrganizations'],
            'filters' => $request->all(),
        ]);
    }

    /**
     * Store a newly created organization.
     *
     * @param Request $request
     */
    public function store(StoreOrganization $request)
    {
        $organization = $this->organizationService->store($request);

        return redirect('/admin/organizations/'.$organization->uuid)->with(
            'status', [
                'type' => 'success', 
                'message' => __('Organization created successfully!')
            ]
        );
    }

    /**
     * Update the specified organization.
     *
     * @param Request $request
     */
    public function update(StoreOrganization $request, $uuid)
    {
        $this->organizationService->update($request, $uuid);

        return redirect('/admin/organizations/'.$uuid)->with(
            'status', [
                'type' => 'success', 
                'message' => __('Organization updated successfully!')
            ]
        );
    }

    /**
     * Remove the specified organization.
     *
     * @param String $uuid
     */
    public function destroy($uuid)
    {
        try {
            $query = $this->organizationService->destroy($uuid);
        } catch (ValidationException $exception) {
            return back()->with(
                'status',
                [
                    'type' => 'error',
                    'message' => collect($exception->errors())->flatten()->first() ?? __('This organization could not be deleted.'),
                ]
            );
        }

        return back()->with(
            'status', [
                'type' => $query ? 'success' : 'error', 
                'message' => $query ? __('Organization deleted successfully!') : __('This organization does not exist!')
            ]
        );
    }

    public function showInvoice(Request $request, string $organizationUuid, string $invoiceUuid)
    {
        $document = app(BillingInvoiceService::class)->documentForOrganizationUuid($organizationUuid, $invoiceUuid);

        return Inertia::render('Admin/Billing/InvoiceShow', [
            'title' => __('Invoice'),
            'invoice' => $document,
            'backUrl' => route('organizations.show', ['organization' => $organizationUuid, 'tab' => 'billing']),
            'backLabel' => __('Back to organization billing'),
            'printUrl' => route('organizations.invoices.print', ['organization' => $organizationUuid, 'invoice' => $invoiceUuid]),
            'downloadUrl' => route('organizations.invoices.download', ['organization' => $organizationUuid, 'invoice' => $invoiceUuid]),
        ]);
    }

    public function previewInvoice(Request $request, string $organizationUuid, string $invoiceUuid)
    {
        $document = app(BillingInvoiceService::class)->documentForOrganizationUuid($organizationUuid, $invoiceUuid);

        return view('billing.invoice-document', [
            'invoice' => $document,
            'printMode' => false,
            'downloadMode' => false,
            'embeddedMode' => true,
            'backUrl' => null,
            'printUrl' => route('organizations.invoices.print', ['organization' => $organizationUuid, 'invoice' => $invoiceUuid]),
            'downloadUrl' => route('organizations.invoices.download', ['organization' => $organizationUuid, 'invoice' => $invoiceUuid]),
            'title' => __('Invoice'),
        ]);
    }

    public function printInvoice(Request $request, string $organizationUuid, string $invoiceUuid)
    {
        $document = app(BillingInvoiceService::class)->documentForOrganizationUuid($organizationUuid, $invoiceUuid);

        return app(BillingInvoiceService::class)->inlineResponse([
            'invoice' => $document,
            'printMode' => true,
            'downloadMode' => false,
            'backUrl' => route('organizations.invoices.show', ['organization' => $organizationUuid, 'invoice' => $invoiceUuid]),
            'printUrl' => route('organizations.invoices.print', ['organization' => $organizationUuid, 'invoice' => $invoiceUuid]),
            'downloadUrl' => route('organizations.invoices.download', ['organization' => $organizationUuid, 'invoice' => $invoiceUuid]),
            'title' => __('Invoice'),
        ]);
    }

    public function downloadInvoice(Request $request, string $organizationUuid, string $invoiceUuid)
    {
        $document = app(BillingInvoiceService::class)->documentForOrganizationUuid($organizationUuid, $invoiceUuid);

        return app(BillingInvoiceService::class)->downloadResponse([
            'invoice' => $document,
            'printMode' => false,
            'downloadMode' => true,
            'backUrl' => route('organizations.invoices.show', ['organization' => $organizationUuid, 'invoice' => $invoiceUuid]),
            'printUrl' => route('organizations.invoices.print', ['organization' => $organizationUuid, 'invoice' => $invoiceUuid]),
            'downloadUrl' => route('organizations.invoices.download', ['organization' => $organizationUuid, 'invoice' => $invoiceUuid]),
            'title' => __('Invoice'),
        ]);
    }
}
