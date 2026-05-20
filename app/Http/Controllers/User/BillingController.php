<?php

namespace App\Http\Controllers\User;

use DB;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\PaymentRequest;
use App\Models\Addon;
use App\Models\BillingPayment;
use App\Models\Organization;
use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Resolvers\PaymentPlatformResolver;
use App\Services\BillingInvoiceService;
use App\Services\BillingService;
use App\Services\BillingCheckoutIntentService;
use App\Services\OrganizationHierarchyService;
use App\Services\PaymentProcessorAvailabilityService;
use App\Services\OrganizationUsageSummaryService;
use App\Services\SubscriptionPlanLimitService;
use App\Services\SubscriptionService;
use App\Support\SupportedPaymentProcessors;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Redirect;

class BillingController extends BaseController
{
    protected $billingService;
    protected $subscriptionService;
    protected $paymentPlatformResolver;
    protected $organizationHierarchyService;
    protected $billingInvoiceService;
    protected $organizationUsageSummaryService;
    protected $subscriptionPlanLimitService;

    public function __construct()
    {
        $this->billingService = new BillingService();
        $this->subscriptionService = new SubscriptionService();
        $this->paymentPlatformResolver = new PaymentPlatformResolver();
        $this->organizationHierarchyService = new OrganizationHierarchyService();
        $this->billingInvoiceService = new BillingInvoiceService();
        $this->organizationUsageSummaryService = app(OrganizationUsageSummaryService::class);
        $this->subscriptionPlanLimitService = app(SubscriptionPlanLimitService::class);
    }
    
    public function index(Request $request){
        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.billing_subscription', $organizationId);
        
        $organization = Organization::where('id', $organizationId)->first();
        $billingOwner = $this->organizationHierarchyService->billingOwner((int) $organizationId);
        $billingOwnerUuid = $billingOwner?->uuid ?? $organization->uuid;
        $data['subscription'] = $this->subscriptionPlanLimitService->subscriptionForOrganization((int) $organizationId);
        $data['subscriptionIsActive'] = SubscriptionService::isSubscriptionActive($organizationId);
        $data['invoiceRows'] = $this->billingInvoiceService->list($request, $billingOwnerUuid);
        $data['billingActivity'] = $this->billingService->getTransactions($request, $billingOwnerUuid);
        $data['filters'] = $request->all();
        $data['methods'] = $this->paymentMethods();
        $data['subscriptionDetails'] = SubscriptionService::calculateSubscriptionBillingDetails($organizationId, $data['subscription']?->plan_id);
        $data['title'] = __('Billing');
        $data['isPaymentLoading'] = false;
        $data['pusherSettings'] = Setting::whereIn('key', [
            'pusher_app_id',
            'pusher_app_key',
            'pusher_app_secret',
            'pusher_app_cluster',
        ])->pluck('value', 'key')->toArray();
        $data['setting'] = Setting::whereIn('key', ['enable_custom_payment'])->pluck('value', 'key')->toArray();
        $data['organizationId'] = $organizationId;
        $data['billingOwner'] = $billingOwner;
        $data['subscriptionManagedByParent'] = $this->organizationHierarchyService->isBranch((int) $organizationId);
        $data['scheduledPlanChange'] = SubscriptionService::scheduledPlanChangePayload($data['subscription']);
        $data['subscriptionDisplayState'] = SubscriptionService::billingDisplayState((int) $organizationId, $data['subscription']);

        if($request->has('paymentId') && $request->has('token')){
            //Check if payment id exists in DB
            $payment = BillingPayment::where('details', $request->paymentId)->first();
            if(!$payment){
                $data['isPaymentLoading'] = true;
            } else {
                return redirect('/billing')->with(
                    'status', [
                        'type' => 'success', 
                        'message' => __('Payment processed successfully!')
                    ]
                );
            }
        } else if($request->has('hostedpage')){
            return redirect('/billing')->with(
                'status', [
                    'type' => 'error',
                    'message' => __('Unsupported payment callback for selected processor.'),
                ]
            );
        }

        return Inertia::render('User/Billing/Index', $data);
    }

    public function usage(Request $request)
    {
        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.billing_subscription', $organizationId);

        $billingOwner = $this->organizationHierarchyService->billingOwner((int) $organizationId);
        $familyOrganizationIds = $this->organizationHierarchyService->familyOrganizationIds((int) $organizationId);
        $subscription = $this->subscriptionPlanLimitService->subscriptionForOrganization((int) $organizationId);

        return Inertia::render('User/Billing/Usage', [
            'title' => __('Usage and limits'),
            'subscription' => $subscription,
            'billingOwner' => $billingOwner,
            'subscriptionManagedByParent' => $this->organizationHierarchyService->isBranch((int) $organizationId),
            'usageSummary' => $this->organizationUsageSummaryService->build((int) $organizationId, $familyOrganizationIds),
            'scheduledPlanChange' => SubscriptionService::scheduledPlanChangePayload($subscription),
        ]);
    }

    public function showInvoice(Request $request, string $invoiceUuid)
    {
        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.billing_subscription', $organizationId);

        $document = $this->billingInvoiceService->documentForViewerOrganization($invoiceUuid, (int) $organizationId);

        return Inertia::render('User/Billing/InvoiceShow', [
            'title' => __('Invoice'),
            'invoice' => $document,
            'backUrl' => route('user.billing.index'),
            'backLabel' => __('Back to billing'),
            'printUrl' => route('user.billing.invoices.print', ['invoice' => $invoiceUuid]),
            'downloadUrl' => route('user.billing.invoices.download', ['invoice' => $invoiceUuid]),
        ]);
    }

    public function previewInvoice(Request $request, string $invoiceUuid)
    {
        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.billing_subscription', $organizationId);

        $document = $this->billingInvoiceService->documentForViewerOrganization($invoiceUuid, (int) $organizationId);

        return view('billing.invoice-document', [
            'invoice' => $document,
            'printMode' => false,
            'downloadMode' => false,
            'embeddedMode' => true,
            'backUrl' => null,
            'printUrl' => route('user.billing.invoices.print', ['invoice' => $invoiceUuid]),
            'downloadUrl' => route('user.billing.invoices.download', ['invoice' => $invoiceUuid]),
            'title' => __('Invoice'),
        ]);
    }

    public function printInvoice(Request $request, string $invoiceUuid)
    {
        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.billing_subscription', $organizationId);

        $document = $this->billingInvoiceService->documentForViewerOrganization($invoiceUuid, (int) $organizationId);

        return $this->billingInvoiceService->inlineResponse([
            'invoice' => $document,
            'printMode' => true,
            'downloadMode' => false,
            'backUrl' => route('user.billing.invoices.show', ['invoice' => $invoiceUuid]),
            'printUrl' => route('user.billing.invoices.print', ['invoice' => $invoiceUuid]),
            'downloadUrl' => route('user.billing.invoices.download', ['invoice' => $invoiceUuid]),
            'title' => __('Invoice'),
        ]);
    }

    public function downloadInvoice(Request $request, string $invoiceUuid)
    {
        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.billing_subscription', $organizationId);

        $document = $this->billingInvoiceService->documentForViewerOrganization($invoiceUuid, (int) $organizationId);

        return $this->billingInvoiceService->downloadResponse([
            'invoice' => $document,
            'printMode' => false,
            'downloadMode' => true,
            'backUrl' => route('user.billing.invoices.show', ['invoice' => $invoiceUuid]),
            'printUrl' => route('user.billing.invoices.print', ['invoice' => $invoiceUuid]),
            'downloadUrl' => route('user.billing.invoices.download', ['invoice' => $invoiceUuid]),
            'title' => __('Invoice'),
        ]);
    }

    public function pay(PaymentRequest $request){
        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.billing_subscription', $organizationId);
        $this->abortIfBranchSubscriptionManagedByParent((int) $organizationId);

        try {
            $intent = app(BillingCheckoutIntentService::class)->createTopUpIntent(
                (int) $organizationId,
                (float) $request->amount,
                (int) auth()->id(),
                (string) $request->method
            );
            session()->put('paymentPlatform', SupportedPaymentProcessors::normalizeName($request->method));
            $response = app(BillingCheckoutIntentService::class)->beginGatewayCheckout($intent, (string) $request->method);

            if ($response->success === true) {
                return inertia::location($response->data);
            }
        } catch (\Throwable $e) {
            return redirect('/billing')->with(
                'status', [
                    'type' => 'error', 
                    'message' => $e instanceof \InvalidArgumentException
                        ? $e->getMessage()
                        : __('Could not process your payment successfully!')
                ]
            );
        }

        return redirect('/billing')->with(
            'status', [
                'type' => 'error',
                'message' => __('Could not process your payment successfully!')
            ]
        );
    }

    private function abortIfBranchSubscriptionManagedByParent(int $organizationId): void
    {
        if (!$this->organizationHierarchyService->isBranch($organizationId)) {
            return;
        }

        abort(403, __('Subscription and billing are managed from the parent organization.'));
    }

    private function paymentMethods(){
        return app(PaymentProcessorAvailabilityService::class)->availableMethodRows();
    }
}
