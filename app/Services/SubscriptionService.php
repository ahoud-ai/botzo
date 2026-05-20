<?php

namespace App\Services;

use App\Helpers\Email;
use App\Http\Resources\SubscriptionPlanResource;
use App\Models\AutoReply;
use App\Models\BillingCredit;
use App\Models\BillingInvoice;
use App\Models\BillingTaxRate;
use App\Models\BillingTransaction;
use App\Models\Campaign;
use App\Models\Chat;
use App\Models\Contact;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TaxRate;
use App\Models\Team;
use App\Models\User;
use App\Resolvers\PaymentPlatformResolver;
use App\Support\SupportedPaymentProcessors;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use DB;

class SubscriptionService
{
    public static function isSubscriptionActive(string $organizationId)
    {
        $subscription = app(SubscriptionPlanLimitService::class)
            ->subscriptionForOrganization((int) $organizationId);

        return app(SubscriptionPlanLimitService::class)->isOperationallyActive($subscription);
    }

    public static function billingDisplayState(int|string $organizationId, ?Subscription $subscription = null): array
    {
        $organizationId = (int) $organizationId;
        $planLimitService = app(SubscriptionPlanLimitService::class);
        $resolvedSubscription = $subscription ?: $planLimitService->subscriptionForOrganization($organizationId);
        $operationalStatus = $planLimitService->operationalStatus($resolvedSubscription);
        $rawStatus = $resolvedSubscription?->status;
        $managedByParent = app(OrganizationHierarchyService::class)->isBranch($organizationId);

        $variant = match (true) {
            $managedByParent => 'inherited',
            $resolvedSubscription === null => 'billing_pending',
            $operationalStatus === 'trial' => 'trial_active',
            $operationalStatus === 'active' => 'active',
            $operationalStatus === 'expired' && $rawStatus === 'trial' => 'trial_expired',
            $operationalStatus === 'expired' => 'payment_required',
            default => 'inactive',
        };

        return [
            'operational_status' => $operationalStatus,
            'raw_status' => $rawStatus,
            'variant' => $variant,
            'is_active' => in_array($operationalStatus, ['trial', 'active'], true),
            'managed_by_parent' => $managedByParent,
            'label' => match ($variant) {
                'inherited' => __('Managed from parent subscription'),
                'billing_pending' => __('Billing setup required'),
                'trial_active' => __('Trial period'),
                'active' => __('Active'),
                'trial_expired' => __('Trial expired'),
                'payment_required' => __('Subscription renewal required'),
                default => __('No active subscription'),
            },
        ];
    }

    public static function scheduledPlanChangePayload(?Subscription $subscription): ?array
    {
        if (!$subscription || !$subscription->scheduled_plan_id || !$subscription->scheduledPlan) {
            return null;
        }

        return [
            'plan_id' => (int) $subscription->scheduled_plan_id,
            'plan_name' => $subscription->scheduledPlan->localizedName(app()->getLocale()) ?? $subscription->scheduledPlan->name,
            'period' => $subscription->scheduledPlan->period,
            'effective_at' => $subscription->scheduled_plan_change_at,
        ];
    }

    public static function planSelectionPreview($organizationId, $selectedPlanId, ?string $couponCode = null): array
    {
        if (!$selectedPlanId) {
            return [
                'change_action' => 'subscribe_now',
                'taxRates' => [],
                'credit' => [
                    'available' => number_format(0, 2),
                    'new' => number_format(0, 2),
                    'total' => number_format(0, 2),
                ],
                'debit' => [
                    'available' => number_format(0, 2),
                    'total' => number_format(0, 2),
                ],
                'coupon' => [],
                'basePrice' => number_format(0, 2),
                'grossAmount' => number_format(0, 2),
                'totalTaxAmount' => number_format(0, 2),
                'netAmount' => number_format(0, 2),
                'accountBalance' => number_format(0, 2),
                'amountDue' => number_format(0, 2),
                'renewalAmount' => number_format(0, 2),
                'effectiveAt' => null,
                'scheduledPlanChange' => null,
            ];
        }

        $decision = self::determinePlanChangeAction($organizationId, $selectedPlanId);

        if (in_array($decision['action'], ['current_plan', 'downgrade_at_renewal', 'scheduled_downgrade'], true)) {
            return self::buildScheduledChangePreview($decision);
        }

        $billingDetails = self::calculateSubscriptionBillingDetails($organizationId, $selectedPlanId, $couponCode);
        $billingDetails['change_action'] = $decision['action'];
        $billingDetails['effectiveAt'] = $decision['effective_at'];
        $billingDetails['renewalAmount'] = $billingDetails['netAmount'];
        $billingDetails['scheduledPlanChange'] = $decision['scheduled_plan_change'];

        return $billingDetails;
    }

    public static function determinePlanChangeAction($organizationId, $selectedPlanId): array
    {
        $organizationId = self::resolveBillingOrganizationId($organizationId);
        $subscription = self::resolveActiveSubscription($organizationId);
        $selectedPlan = SubscriptionPlan::find($selectedPlanId);

        if (!$selectedPlan) {
            return [
                'action' => 'invalid_plan',
                'selected_plan' => null,
                'current_plan' => $subscription?->plan,
                'effective_at' => null,
                'scheduled_plan_change' => self::scheduledPlanChangePayload($subscription),
            ];
        }

        if (!$subscription || !$subscription->plan) {
            return [
                'action' => 'subscribe_now',
                'selected_plan' => $selectedPlan,
                'current_plan' => null,
                'effective_at' => null,
                'scheduled_plan_change' => null,
            ];
        }

        if (!self::isSubscriptionCurrentlyBillable($subscription)) {
            return [
                'action' => 'subscribe_now',
                'selected_plan' => $selectedPlan,
                'current_plan' => $subscription->plan,
                'effective_at' => null,
                'scheduled_plan_change' => self::scheduledPlanChangePayload($subscription),
            ];
        }

        if ((int) $subscription->plan_id === (int) $selectedPlan->id) {
            return [
                'action' => 'current_plan',
                'selected_plan' => $selectedPlan,
                'current_plan' => $subscription->plan,
                'effective_at' => null,
                'scheduled_plan_change' => self::scheduledPlanChangePayload($subscription),
            ];
        }

        if ((int) $subscription->scheduled_plan_id === (int) $selectedPlan->id) {
            return [
                'action' => 'scheduled_downgrade',
                'selected_plan' => $selectedPlan,
                'current_plan' => $subscription->plan,
                'effective_at' => $subscription->scheduled_plan_change_at,
                'scheduled_plan_change' => self::scheduledPlanChangePayload($subscription),
            ];
        }

        $currentPlan = $subscription->plan;
        $selectedRank = self::resolvePlanRank($selectedPlan);
        $currentRank = self::resolvePlanRank($currentPlan);

        if ($selectedRank > $currentRank) {
            return [
                'action' => 'upgrade_now',
                'selected_plan' => $selectedPlan,
                'current_plan' => $currentPlan,
                'effective_at' => null,
                'scheduled_plan_change' => self::scheduledPlanChangePayload($subscription),
            ];
        }

        if ($selectedRank < $currentRank) {
            return [
                'action' => 'downgrade_at_renewal',
                'selected_plan' => $selectedPlan,
                'current_plan' => $currentPlan,
                'effective_at' => $subscription->valid_until,
                'scheduled_plan_change' => self::scheduledPlanChangePayload($subscription),
            ];
        }

        if ($currentPlan->period === $selectedPlan->period) {
            $action = self::planAnnualizedAmount($selectedPlan) >= self::planAnnualizedAmount($currentPlan)
                ? 'upgrade_now'
                : 'downgrade_at_renewal';

            return [
                'action' => $action,
                'selected_plan' => $selectedPlan,
                'current_plan' => $currentPlan,
                'effective_at' => $action === 'downgrade_at_renewal' ? $subscription->valid_until : null,
                'scheduled_plan_change' => self::scheduledPlanChangePayload($subscription),
            ];
        }

        if ($currentPlan->period === 'monthly' && $selectedPlan->period === 'yearly') {
            return [
                'action' => 'upgrade_now',
                'selected_plan' => $selectedPlan,
                'current_plan' => $currentPlan,
                'effective_at' => null,
                'scheduled_plan_change' => self::scheduledPlanChangePayload($subscription),
            ];
        }

        return [
            'action' => 'downgrade_at_renewal',
            'selected_plan' => $selectedPlan,
            'current_plan' => $currentPlan,
            'effective_at' => $subscription->valid_until,
            'scheduled_plan_change' => self::scheduledPlanChangePayload($subscription),
        ];
    }

    public static function schedulePlanChangeAtRenewal($organizationId, $planId): void
    {
        $organizationId = self::resolveBillingOrganizationId($organizationId);
        $subscription = self::resolveActiveSubscription($organizationId);

        if (!$subscription) {
            return;
        }

        $subscription->update([
            'scheduled_plan_id' => $planId,
            'scheduled_plan_change_at' => $subscription->valid_until,
        ]);
    }

    public static function cancelScheduledPlanChange($organizationId): void
    {
        $organizationId = self::resolveBillingOrganizationId($organizationId);
        self::clearScheduledPlanChange($organizationId);
    }

    public static function store($request, $organizationId, $planId, $userId)
    {
        $billingDetails = self::calculateSubscriptionBillingDetails($organizationId, $planId);

        $response = false;

        if($billingDetails['amountDue'] == 0){
            self::createBillingInvoice($billingDetails, $organizationId, $planId, $userId);
        } else {
            try {
                $paymentPlatform = (new PaymentPlatformResolver())->resolveService($request->method);
                session()->put('paymentPlatform', SupportedPaymentProcessors::normalizeName($request->method));

                $amountDue = str_replace(',', '', $billingDetails['amountDue']);
                $amountDue = (float)$amountDue;
                $response = $paymentPlatform->handlePayment($amountDue, $request->plan);

                return $response;
            } catch (\Throwable $e) {
                $response = new \stdClass();
                $response->success = false;
                $response->error = $e instanceof \InvalidArgumentException
                    ? $e->getMessage()
                    : __('Could not process the selected payment method.');

                return $response;
            }
        }
    }

    public static function activateSubscriptionIfInactiveAndExpiredWithCredits($organizationId, $userId = 0)
    {
        $organizationId = self::resolveBillingOrganizationId($organizationId);
        $subscription = self::resolveActiveSubscription($organizationId);

        if (!$subscription) {
            return false;
        }

        if($subscription->valid_until < now()){
            $planId = self::renewalPlanId($subscription);

                if($planId){
                $billingDetails = self::calculateSubscriptionBillingDetails($organizationId, $planId);

                if($billingDetails['amountDue'] == 0){
                    $invoice = self::createBillingInvoice($billingDetails, $organizationId, $planId, $userId);

                    $ownerRole = \App\Models\OrganizationRole::where('name', 'Owner')
                        ->whereNull('organization_id')
                        ->first();
                    $team = Team::where('organization_id', $organizationId)
                        ->where('organization_role_id', $ownerRole->id)
                        ->first();
                    $user = User::where('id', $team->user_id)->first();
                    $plan = SubscriptionPlan::where('id', $planId)->first();

                    //Send subscription email
                    Email::sendSubscriptionEmail('Subscription Renewal', $user, $plan);

                    return $invoice;
                }
            } 
        }

        return false;
    }

    public static function updateSubscriptionPlan($organizationId, $planId, $userId)
    {
        $organizationId = self::resolveBillingOrganizationId($organizationId);
        $plan = SubscriptionPlan::where('id', $planId)->first();

        if($plan){
            $billingDetails = self::calculateSubscriptionBillingDetails($organizationId, $planId);

            if($billingDetails['amountDue'] == 0){
                $invoice = self::createBillingInvoice($billingDetails, $organizationId, $planId, $userId);

                $ownerRole = \App\Models\OrganizationRole::where('name', 'Owner')
                    ->whereNull('organization_id')
                    ->first();
                $team = $ownerRole
                    ? Team::where('organization_id', $organizationId)->where('organization_role_id', $ownerRole->id)->first()
                    : null;
                $user = $team ? User::where('id', $team->user_id)->first() : null;
                $plan = SubscriptionPlan::where('id', $planId)->first();

                //Send subscription email
                if ($user && $plan) {
                    Email::sendSubscriptionEmail('Subscription Plan Purchase', $user, $plan);
                }

                return $invoice;
            }
        }

        return false;
    }

    public static function createBillingInvoice($billingDetails, $organizationId, $planId, $userId)
    {
        $organizationId = self::resolveBillingOrganizationId($organizationId);

        return DB::transaction(function () use ($billingDetails, $organizationId, $planId, $userId) {
            $netAmount = str_replace(',', '', $billingDetails['netAmount']);
            $netAmount = (float)$netAmount;
            $grossAmount = isset($billingDetails['grossAmount'])
                ? (float) str_replace(',', '', (string) $billingDetails['grossAmount'])
                : max(0, $netAmount - (float) str_replace(',', '', (string) $billingDetails['totalTaxAmount']));
            $totalTaxAmount = str_replace(',', '', $billingDetails['totalTaxAmount']);
            $totalTaxAmount = (float)$totalTaxAmount;

            $invoice = BillingInvoice::create([
                'organization_id' => $organizationId,
                'plan_id' => $planId,
                'subtotal' => $grossAmount,
                'tax' => $totalTaxAmount,
                'tax_type' => $billingDetails['isTaxInclusive'] === true ? 'inclusive' : 'exclusive',
                'total' => $netAmount,
            ]);

            foreach($billingDetails['taxRates'] as $taxRate){
                $taxRateAmount = str_replace(',', '', $taxRate['amount']);
                $taxrate = BillingTaxRate::create([
                    'invoice_id' => $invoice->id,
                    'rate' => $taxRateAmount,
                    'amount' => $taxRate['percentage'],
                ]);
            }

            $invoiceBillingTransaction = BillingTransaction::create([
                'organization_id' => $organizationId,
                'entity_type' => 'invoice',
                'entity_id' => $invoice->id,
                'description' => 'Invoice',
                'amount' => -$netAmount,
                'created_by' => $userId,
            ]);

            if(abs($billingDetails['credit']['new']) > 0){
                BillingCredit::create([
                    'organization_id' => $organizationId,
                    'description' => __('Credit memo'),
                    'amount' => abs($billingDetails['credit']['new'])
                ]);

                $creditBillingTransaction = BillingTransaction::create([
                    'organization_id' => $organizationId,
                    'entity_type' => 'credit',
                    'entity_id' => $invoice->id,
                    'description' => __('Credit memo'),
                    'amount' => $billingDetails['credit']['new'],
                    'created_by' => $userId,
                ]);
            }

            //Update subscription
            $plan = SubscriptionPlan::where('id', $planId)->first();

            self::upsertOperationalSubscription($organizationId, [
                'plan_id' => $planId,
                'scheduled_plan_id' => null,
                'start_date' => now(),
                'valid_until' => date('Y-m-d H:i:s', strtotime('+1 ' . ($plan->period === 'monthly' ? 'month' : 'year'))),
                'scheduled_plan_change_at' => null,
                'status' => 'active',
            ]);

            return $invoice;
        });
    }

    public static function calculateSubscriptionBillingDetails($organizationId, $selectedPlanId, ?string $couponCode = null)
    {
        $organizationId = self::resolveBillingOrganizationId($organizationId);
        $currentSubscription = self::resolveActiveSubscription($organizationId);
        $subscriptionStatus = $currentSubscription?->status ?? 'trial';

        $selectedSubscriptionPlan = SubscriptionPlan::where('id', $selectedPlanId)->first();
        $isTaxInclusive = app(SettingValueService::class)->getBool('is_tax_inclusive', false);

        $totalTaxPercentage = self::calculateTotalTaxPercentage();

        if($selectedSubscriptionPlan){
            $basePrice = ($subscriptionStatus == 'trial') ? $selectedSubscriptionPlan->price : $selectedSubscriptionPlan->price;
        } else {
            $basePrice = 0;
        }

        $grossAmount = $isTaxInclusive ? $basePrice - ($basePrice * $totalTaxPercentage / (100 + $totalTaxPercentage)) : $basePrice;

        $proratedCreditAmount = 0;

        if ($subscriptionStatus != 'trial') {
            // Calculate the unused amount for the current invoiced period as a credit to the user's account
            $lastInvoice = BillingInvoice::where('organization_id', $organizationId)->orderBy('id', 'desc')->first();
            $lastInvoiceTotal = $lastInvoice ? $lastInvoice->total : 0;
            $proratedAmount = self::calculateProratedAmount($organizationId, $lastInvoiceTotal);

            //Calculate unutilized amount for current invoiced period
            $proratedCreditAmount = $proratedAmount;
        }

        //Get user's account credits and debits
        $accountBalance = BillingTransaction::where('organization_id', $organizationId)->sum('amount');
        $availableCredits = max(0, $accountBalance);
        $availableDebits = min(0, $accountBalance);

        // Calculate tax rates
        $taxCalculationResult = self::calculateTaxRates($grossAmount);

        // Calculate net amount after considering taxes
        $netAmount = $grossAmount + $taxCalculationResult['totalTaxAmount'];

        // Calculate amount due considering credits, debits, and taxes
        $amountDue = $grossAmount + $taxCalculationResult['totalTaxAmount'] - $proratedCreditAmount - $accountBalance;

        // Ensure that amount due is not negative
        $amountDue = max(0, $amountDue);

        //Apply coupon is amount due > 0
        $coupon = self::resolveCouponPreview($amountDue, $couponCode);
        $amountDue = max(0, $amountDue - self::couponDiscountAmount($coupon));

        $response = [
            'isTaxInclusive' => $isTaxInclusive,
            'basePrice' => number_format($basePrice, 2),
            'grossAmount' => number_format($grossAmount, 2),
            'taxRates' => $taxCalculationResult['taxRatesDetails'],
            'totalTaxAmount' => $taxCalculationResult['totalTaxAmount'],
            'netAmount' => number_format($netAmount, 2),
            'accountBalance' => number_format($accountBalance, 2),
            'credit' => [
                'available' => number_format($availableCredits, 2),
                'new' => number_format($proratedCreditAmount, 2),
                'total' => number_format($availableCredits + $proratedCreditAmount, 2)
            ],
            'debit' => [
                'available' => number_format($availableDebits, 2),
                'total' => number_format($availableDebits, 2)
            ],
            'coupon' => $coupon,
            'amountDue' => number_format($amountDue, 2)
        ];

        return $response;
    }

    public static function resolveCouponPreview(float $amountDue, ?string $couponCode = null): array
    {
        $couponCode = trim((string) ($couponCode ?? ''));

        if ($amountDue <= 0 || $couponCode === '') {
            return [];
        }

        $couponData = \App\Models\Coupon::where('code', $couponCode)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();

        if (!$couponData) {
            return [];
        }

        $quantity = $couponData->quantity;
        $quantityRedeemed = (int) ($couponData->quantity_redeemed ?? 0);

        if ($quantity !== null && $quantityRedeemed >= (int) $quantity) {
            return [];
        }

        $discount = ($amountDue * (float) $couponData->percentage) / 100;
        $discount = min($discount, $amountDue);

        return [
            'id' => $couponData->id,
            'code' => $couponData->code,
            'type' => 'percentage',
            'amount' => (float) $couponData->percentage,
            'discount' => number_format($discount, 2),
        ];
    }

    public static function couponDiscountAmount(array $coupon): float
    {
        if ($coupon === []) {
            return 0.0;
        }

        return (float) str_replace(',', '', (string) ($coupon['discount'] ?? 0));
    }

    private static function calculateTotalTaxPercentage()
    {
        $activeTaxRates = TaxRate::where('status', 'active')->whereNull('deleted_at')->get();
        $totalTaxPercent = 0;

        foreach($activeTaxRates as $taxRate){
            $totalTaxPercent += $taxRate->percentage;
        }

        return $totalTaxPercent;
    }

    private static function calculateTaxRates($grossAmount)
    {
        $activeTaxRates = TaxRate::where('status', 'active')->whereNull('deleted_at')->get();
        $taxRatesDetails = [];
        $totalTaxAmount = 0;

        foreach($activeTaxRates as $taxRate){
            $taxAmount = $taxRate->percentage * $grossAmount / 100;
            $taxRatesDetails[] = array(
                'name' => $taxRate->name,
                'percentage' => $taxRate->percentage,
                'amount' => number_format($taxAmount, 2),
            );
            $totalTaxAmount += $taxAmount;
        }

        $response['taxRatesDetails'] = $taxRatesDetails;
        $response['totalTaxAmount'] = $totalTaxAmount;

        return $response;
    }

    private static function calculateProratedAmount($organizationId, $amount)
    {
        $organizationId = self::resolveBillingOrganizationId($organizationId);

        // Calculate the prorated amount based on the remaining days
        $periodInDays = self::subscriptionPeriodInDays($organizationId);

        if($periodInDays > 0){
            $amountPerDay = $amount / $periodInDays;
            $proratedAmount = $amountPerDay * self::subscriptionPeriodRemainingDays($organizationId);

            return $proratedAmount;
        }

        return 0;
    }

    private static function subscriptionPeriodInDays($organizationId)
    {
        $organizationId = self::resolveBillingOrganizationId($organizationId);
        $subscription = self::resolveActiveSubscription($organizationId);
        if (!$subscription) {
            return 0;
        }

        $subscriptionStartDate = Carbon::parse($subscription->start_date);
        $subscriptionEndDate = Carbon::parse($subscription->valid_until);

        return $subscriptionStartDate->diffInDays($subscriptionEndDate);
    }

    private static function subscriptionPeriodRemainingDays($organizationId)
    {
        $organizationId = self::resolveBillingOrganizationId($organizationId);
        $subscription = self::resolveActiveSubscription($organizationId);

        if ($subscription) {
            $subscriptionEndDate = Carbon::parse($subscription->valid_until)->endOfDay();
            
            if ($subscriptionEndDate->isPast()) {
                return 0;
            }

            return now()->endOfDay()->diffInDays($subscriptionEndDate);
        }
    
        return 0;
    }

    public static function isSubscriptionFeatureLimitReached($organizationId, $feature)
    {
        return app(SubscriptionFeatureUsageService::class)->isReached((int) $organizationId, (string) $feature);
    }

    public static function featureUsageSnapshot(int|string $organizationId, string $feature): array
    {
        return app(SubscriptionFeatureUsageService::class)->snapshot((int) $organizationId, $feature);
    }

    public static function isSubscriptionLimitReachedForInboundMessages($organizationId)
    {
        $billingOrganizationId = self::resolveBillingOrganizationId($organizationId);
        $subscription = $billingOrganizationId
            ? self::resolveActiveSubscription($billingOrganizationId)
            : null;

        // If no subscription is found, assume the limit is reached
        if (!$subscription) {
            return true;
        }

        // If no subscription is found, assume the limit is reached
        if(isset($subscription->plan->metadata)){
            $subscriptionMetadata = json_decode($subscription->plan->metadata, true);
            
            // Check if receiving messages after expiration is allowed
            if(isset($subscriptionMetadata['receive_messages_after_expiration']) && $subscriptionMetadata['receive_messages_after_expiration']){
                return false;
            }
        }

        // Check if the subscription has expired
        if($subscription->valid_until < now()){
            return true;
        }

        return false;
    }

    private static function resolveBillingOrganizationId($organizationId): ?int
    {
        $organizationId = (int) $organizationId;

        return app(OrganizationHierarchyService::class)->billingOwnerId($organizationId) ?? $organizationId;
    }

    /**
     * @return array<int>
     */
    private static function resolveUsageOrganizationIds($organizationId): array
    {
        $organizationId = (int) $organizationId;
        $ids = app(OrganizationHierarchyService::class)->familyOrganizationIds($organizationId);

        return $ids !== [] ? $ids : [$organizationId];
    }

    private static function resolveActiveSubscription(int $organizationId): ?Subscription
    {
        return app(SubscriptionPlanLimitService::class)->subscriptionForOrganization($organizationId);
    }

    private static function buildScheduledChangePreview(array $decision): array
    {
        /** @var SubscriptionPlan|null $selectedPlan */
        $selectedPlan = $decision['selected_plan'] ?? null;
        $estimate = $selectedPlan ? self::calculateRenewalEstimate($selectedPlan) : [
            'basePrice' => number_format(0, 2),
            'grossAmount' => number_format(0, 2),
            'totalTaxAmount' => number_format(0, 2),
            'netAmount' => number_format(0, 2),
            'taxRates' => [],
        ];

        return [
            'change_action' => $decision['action'],
            'basePrice' => $estimate['basePrice'],
            'grossAmount' => $estimate['grossAmount'],
            'totalTaxAmount' => $estimate['totalTaxAmount'],
            'netAmount' => $estimate['netAmount'],
            'taxRates' => $estimate['taxRates'],
            'accountBalance' => number_format(0, 2),
            'credit' => [
                'available' => number_format(0, 2),
                'new' => number_format(0, 2),
                'total' => number_format(0, 2),
            ],
            'debit' => [
                'available' => number_format(0, 2),
                'total' => number_format(0, 2),
            ],
            'coupon' => [],
            'amountDue' => number_format(0, 2),
            'renewalAmount' => $estimate['netAmount'],
            'effectiveAt' => $decision['effective_at'],
            'scheduledPlanChange' => $decision['scheduled_plan_change'],
        ];
    }

    private static function isSubscriptionCurrentlyBillable(Subscription $subscription): bool
    {
        if ($subscription->status !== 'active') {
            return false;
        }

        return Carbon::parse($subscription->getRawOriginal('valid_until') ?? $subscription->valid_until)->isFuture();
    }

    private static function renewalPlanId(Subscription $subscription): ?int
    {
        if ($subscription->scheduled_plan_id) {
            return (int) $subscription->scheduled_plan_id;
        }

        return $subscription->plan_id ? (int) $subscription->plan_id : null;
    }

    private static function clearScheduledPlanChange(int $organizationId): void
    {
        $subscription = self::resolveActiveSubscription($organizationId);

        if (!$subscription) {
            return;
        }

        $subscription->update([
            'scheduled_plan_id' => null,
            'scheduled_plan_change_at' => null,
        ]);
    }

    private static function upsertOperationalSubscription(int $organizationId, array $attributes): Subscription
    {
        $subscription = self::resolveActiveSubscription($organizationId);

        if ($subscription) {
            $subscription->update($attributes);

            return $subscription->fresh(['plan', 'scheduledPlan']);
        }

        return Subscription::create(array_merge([
            'organization_id' => $organizationId,
        ], $attributes));
    }

    private static function calculateRenewalEstimate(SubscriptionPlan $plan): array
    {
        $isTaxInclusive = app(SettingValueService::class)->getBool('is_tax_inclusive', false);
        $basePrice = (float) $plan->price;
        $grossAmount = $isTaxInclusive
            ? $basePrice - ($basePrice * self::calculateTotalTaxPercentage() / (100 + self::calculateTotalTaxPercentage()))
            : $basePrice;
        $taxCalculationResult = self::calculateTaxRates($grossAmount);
        $netAmount = $grossAmount + $taxCalculationResult['totalTaxAmount'];

        return [
            'basePrice' => number_format($basePrice, 2),
            'grossAmount' => number_format($grossAmount, 2),
            'totalTaxAmount' => number_format($taxCalculationResult['totalTaxAmount'], 2),
            'netAmount' => number_format($netAmount, 2),
            'taxRates' => $taxCalculationResult['taxRatesDetails'],
        ];
    }

    private static function resolvePlanRank(SubscriptionPlan $plan): int
    {
        $rank = $plan->tierRank();

        if ($rank > 0) {
            return $rank;
        }

        return self::resolveFallbackPlanRank($plan);
    }

    private static function resolveFallbackPlanRank(SubscriptionPlan $plan): int
    {
        $orderedPlanIds = SubscriptionPlan::query()
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->where('period', $plan->period)
            ->orderBy('price')
            ->orderBy('id')
            ->pluck('id')
            ->values();

        $rank = $orderedPlanIds->search($plan->id);

        return $rank === false ? 1 : $rank + 1;
    }

    private static function planAnnualizedAmount(SubscriptionPlan $plan): float
    {
        $price = (float) $plan->price;

        return $plan->period === 'yearly' ? $price : ($price * 12);
    }
}
