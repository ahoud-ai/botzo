<?php

namespace App\Http\Controllers\User;

use DB;
use App\Http\Controllers\Controller as BaseController;
use App\Helpers\SubscriptionHelper;
use App\Http\Requests\CouponRequest;
use App\Http\Requests\StoreSubscriptionPurchaseRequest;
use App\Http\Resources\SubscriptionPlanResource;
use App\Models\Addon;
use App\Models\PaymentGateway;
use App\Models\SubscriptionPlan;
use App\Models\Setting;
use App\Models\TaxRate;
use App\Services\BillingService;
use App\Services\BillingCheckoutIntentService;
use App\Services\OrganizationHierarchyService;
use App\Services\PaymentProcessorAvailabilityService;
use App\Services\SubscriptionPlanLimitService;
use App\Services\SubscriptionService;
use App\Services\SubscriptionPlanService;
use App\Support\SaClientPlanProfile;
use App\Support\SupportedPaymentProcessors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class SubscriptionController extends BaseController
{
    protected $billingService;
    protected $subscriptionService;
    protected $subscriptionPlanService;
    protected $organizationHierarchyService;
    protected $subscriptionPlanLimitService;

    public function __construct()
    {
        $this->billingService = new BillingService();
        $this->subscriptionService = new SubscriptionService();
        $this->subscriptionPlanService = new SubscriptionPlanService();
        $this->organizationHierarchyService = new OrganizationHierarchyService();
        $this->subscriptionPlanLimitService = app(SubscriptionPlanLimitService::class);
    }

    public function index(Request $request){
        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.billing_subscription', $organizationId);

        $data['subscription'] = $this->subscriptionPlanLimitService->subscriptionForOrganization((int) $organizationId);
        $data['taxes'] = TaxRate::where('status', 'active')->where('deleted_at', NULL)->get();
        $planQuery = SubscriptionPlan::query()
            ->whereNull('deleted_at')
            ->where(function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->query('search'). '%');
            })
            ->where('status', 'active');
        $periodCounts = [
            'monthly' => (clone $planQuery)->where('period', 'monthly')->count(),
            'yearly' => (clone $planQuery)->where('period', 'yearly')->count(),
        ];
        $selectedPeriod = $this->resolveSelectedPeriod(
            $request->query('period'),
            $periodCounts,
            $data['subscription']?->plan?->period
        );
        $data['plans'] = SubscriptionPlanResource::collection(
            (clone $planQuery)
                ->where('period', $selectedPeriod)
                ->latest()
                ->paginate(10)
        );
        $data['methods'] = $this->paymentMethods();
        $data['subscriptionDetails'] = SubscriptionService::planSelectionPreview($organizationId, $data['subscription']?->plan_id);
        $data['title'] = __('Billing');
        $data['addons'] = $this->planRestrictedAddonCatalog();
        $data['enable_ai_billing'] = Setting::where('key', 'enable_ai_billing')->value('value') ?? 0;
        $data['billingOwner'] = $this->organizationHierarchyService->billingOwner((int) $organizationId);
        $data['subscriptionManagedByParent'] = $this->organizationHierarchyService->isBranch((int) $organizationId);
        $data['scheduledPlanChange'] = SubscriptionService::scheduledPlanChangePayload($data['subscription']);
        $data['subscriptionDisplayState'] = SubscriptionService::billingDisplayState((int) $organizationId, $data['subscription']);
        $data['selectedPeriod'] = $selectedPeriod;
        $data['periodCounts'] = $periodCounts;

        return Inertia::render('User/Billing/Plan', $data);
    }

    public function store(StoreSubscriptionPurchaseRequest $request){
        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.billing_subscription', $organizationId);
        $this->abortIfBranchSubscriptionManagedByParent((int) $organizationId);
        
        $userId = auth()->user()->id;
        $planId = $request->plan;
        $decision = SubscriptionService::determinePlanChangeAction($organizationId, $planId);

        if ($decision['action'] === 'invalid_plan') {
            return Redirect::back()->with('status', [
                'type' => 'error',
                'message' => __('The selected plan could not be found.'),
            ]);
        }

        if ($decision['action'] === 'current_plan') {
            return Redirect::back()->with('status', [
                'type' => 'info',
                'message' => __('You are already subscribed to this plan.'),
            ]);
        }

        if ($decision['action'] === 'scheduled_downgrade') {
            return Redirect::back()->with('status', [
                'type' => 'info',
                'message' => __('This downgrade is already scheduled for the next renewal date.'),
            ]);
        }

        if ($decision['action'] === 'downgrade_at_renewal') {
            SubscriptionService::schedulePlanChangeAtRenewal($organizationId, $planId);

            return Redirect::route('subscription.index')->with('status', [
                'type' => 'success',
                'message' => __('Your downgrade has been scheduled for the next renewal date.'),
            ]);
        }

        $couponCode = trim((string) $request->input('coupon', ''));
        $preview = SubscriptionService::planSelectionPreview($organizationId, $planId, $couponCode);
        $amountDue = (float) str_replace(',', '', (string) ($preview['amountDue'] ?? '0'));
        $checkoutIntentService = app(BillingCheckoutIntentService::class);

        if ($amountDue > 0 && !$request->filled('method')) {
            return Redirect::back()->withErrors([
                'method' => __('Select a payment method to continue.'),
            ]);
        }

        if ($amountDue > 0 && $this->paymentMethods() === []) {
            return Redirect::back()->with(
                'status',
                [
                    'type' => 'error',
                    'message' => __('No payment methods are available right now. Please contact support.'),
                ]
            );
        }

        if ($amountDue <= 0) {
            $intent = $checkoutIntentService->createSubscriptionPurchaseIntent(
                (int) $organizationId,
                (int) $planId,
                (int) $userId,
                null,
                $couponCode
            );
            $result = $checkoutIntentService->completeWithoutGateway($intent);

            if (!($result->success ?? false)) {
                return Redirect::back()->with(
                    'status',
                    [
                        'type' => 'error',
                        'message' => $result->message ?? __('Could not update your subscription. Please try again.'),
                    ]
                );
            }

            return Redirect::route('user.billing.index')->with(
                'status', [
                    'type' => 'success', 
                    'message' => __('Your subscription has been updated successfully!')
                ]
            );
        }

        $intent = $checkoutIntentService->createSubscriptionPurchaseIntent(
            (int) $organizationId,
            (int) $planId,
            (int) $userId,
            $request->method,
            $couponCode
        );
        $response = $checkoutIntentService->beginGatewayCheckout($intent, (string) $request->method);

        if (is_object($response) && ($response->success ?? false) === true) {
            return inertia::location($response->data);
        }

        return Redirect::back()->with(
            'status', [
                'type' => 'error',
                'message' => $response->error ?? __('Could not process the selected payment method.'),
            ]
        );
    }

    public function show(Request $request, $id)
    {
        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.billing_subscription', $organizationId);
        $this->abortIfBranchSubscriptionManagedByParent((int) $organizationId);

        return Redirect::back()->with('response_data', [
            'data' => SubscriptionService::planSelectionPreview(
                $organizationId,
                $id,
                trim((string) $request->query('coupon', ''))
            ),
        ]);
    }

    public function applyCoupon(CouponRequest $request, $id)
    {
        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.billing_subscription', $organizationId);
        $this->abortIfBranchSubscriptionManagedByParent((int) $organizationId);

        return Redirect::back()->with('response_data', [
            'data' => SubscriptionService::planSelectionPreview(
                $organizationId,
                $id,
                trim((string) $request->input('coupon', ''))
            ),
        ]);
    }

    public function removeCoupon(Request $request, $id)
    {
        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.billing_subscription', $organizationId);
        $this->abortIfBranchSubscriptionManagedByParent((int) $organizationId);

        return Redirect::back()->with('response_data', [
            'data' => SubscriptionService::planSelectionPreview($organizationId, $id, null),
        ]);
    }

    public function destroy($id)
    {
        // Your logic for deleting a specific resource
    }

    public function cancelScheduledChange(Request $request)
    {
        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.billing_subscription', $organizationId);
        $this->abortIfBranchSubscriptionManagedByParent((int) $organizationId);

        SubscriptionService::cancelScheduledPlanChange($organizationId);

        return Redirect::back()->with('status', [
            'type' => 'success',
            'message' => __('The scheduled plan change has been canceled.'),
        ]);
    }

    private function paymentMethods(){
        return app(PaymentProcessorAvailabilityService::class)->availableMethodRows();
    }

    private function abortIfBranchSubscriptionManagedByParent(int $organizationId): void
    {
        if (!$this->organizationHierarchyService->isBranch($organizationId)) {
            return;
        }

        abort(403, __('Subscription and billing are managed from the parent organization.'));
    }

    private function resolveSelectedPeriod(?string $requestedPeriod, array $periodCounts, ?string $currentPlanPeriod): string
    {
        $allowedPeriods = ['monthly', 'yearly'];
        $fallbackPeriod = in_array($currentPlanPeriod, $allowedPeriods, true) ? $currentPlanPeriod : 'monthly';

        if (in_array($requestedPeriod, $allowedPeriods, true) && ($periodCounts[$requestedPeriod] ?? 0) > 0) {
            return $requestedPeriod;
        }

        if (($periodCounts[$fallbackPeriod] ?? 0) > 0) {
            return $fallbackPeriod;
        }

        foreach ($allowedPeriods as $period) {
            if (($periodCounts[$period] ?? 0) > 0) {
                return $period;
            }
        }

        return 'monthly';
    }

    private function planRestrictedAddonCatalog(): array
    {
        return Addon::query()
            ->where('status', 1)
            ->where('is_plan_restricted', 1)
            ->whereIn('name', SaClientPlanProfile::planAddonNames())
            ->get(['name'])
            ->mapWithKeys(static fn (Addon $addon) => [$addon->name => 1])
            ->all();
    }
}
