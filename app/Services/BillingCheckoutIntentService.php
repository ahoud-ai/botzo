<?php

namespace App\Services;

use App\Events\NewPaymentEvent;
use App\Helpers\Email;
use App\Models\BillingCheckoutIntent;
use App\Models\BillingCredit;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\BillingTaxRate;
use App\Models\BillingTransaction;
use App\Models\Coupon;
use App\Models\OrganizationRole;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use App\Resolvers\PaymentPlatformResolver;
use App\Support\BillingPaymentMethodResolver;
use App\Support\SupportedPaymentProcessors;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BillingCheckoutIntentService
{
    public function __construct(
        private readonly OrganizationHierarchyService $organizationHierarchyService,
        private readonly PaymentPlatformResolver $paymentPlatformResolver
    ) {
    }

    public function createSubscriptionPurchaseIntent(
        int $organizationId,
        int $planId,
        int $userId,
        ?string $processor = null,
        ?string $couponCode = null
    ): BillingCheckoutIntent {
        $billingOrganizationId = $this->resolveBillingOrganizationId($organizationId);
        $plan = SubscriptionPlan::findOrFail($planId);
        $snapshot = SubscriptionService::planSelectionPreview($organizationId, $planId, $couponCode);
        $coupon = $this->resolveCouponFromSnapshot($snapshot);

        return BillingCheckoutIntent::create([
            'organization_id' => $organizationId,
            'billing_organization_id' => $billingOrganizationId,
            'user_id' => $userId,
            'type' => 'subscription_purchase',
            'status' => $this->toDecimal($snapshot['amountDue'] ?? 0) > 0 ? 'pending' : 'processing',
            'target_plan_id' => $plan->id,
            'processor' => $processor ? SupportedPaymentProcessors::normalizeName($processor) : null,
            'currency' => strtoupper((string) app(SettingValueService::class)->getString('currency', 'SAR')),
            'base_price' => $this->toDecimal($snapshot['basePrice'] ?? 0),
            'gross_amount' => $this->toDecimal($snapshot['grossAmount'] ?? 0),
            'tax_total' => $this->toDecimal($snapshot['totalTaxAmount'] ?? 0),
            'net_total' => $this->toDecimal($snapshot['netAmount'] ?? 0),
            'balance_applied' => $this->resolvedBalanceApplied($snapshot),
            'amount_due' => $this->toDecimal($snapshot['amountDue'] ?? 0),
            'coupon_id' => $coupon?->id,
            'coupon_code' => $coupon?->code,
            'coupon_amount' => $this->toDecimal(data_get($snapshot, 'coupon.discount', 0)),
            'snapshot_json' => array_merge($snapshot, [
                'selected_plan_id' => $plan->id,
                'selected_plan_name' => $plan->localizedName(app()->getLocale()) ?? $plan->name,
            ]),
            'expires_at' => now()->addHours(2),
        ]);
    }

    public function createTopUpIntent(
        int $organizationId,
        float $amount,
        int $userId,
        string $processor
    ): BillingCheckoutIntent {
        $billingOrganizationId = $this->resolveBillingOrganizationId($organizationId);
        $normalizedProcessor = SupportedPaymentProcessors::normalizeName($processor);
        $amount = round($amount, 2);

        return BillingCheckoutIntent::create([
            'organization_id' => $organizationId,
            'billing_organization_id' => $billingOrganizationId,
            'user_id' => $userId,
            'type' => 'account_topup',
            'status' => 'pending',
            'processor' => $normalizedProcessor,
            'currency' => strtoupper((string) app(SettingValueService::class)->getString('currency', 'SAR')),
            'amount_due' => $amount,
            'net_total' => $amount,
            'snapshot_json' => [
                'topup_amount' => number_format($amount, 2, '.', ''),
            ],
            'expires_at' => now()->addHours(2),
        ]);
    }

    public function beginGatewayCheckout(BillingCheckoutIntent $intent, string $processor): object
    {
        $processor = SupportedPaymentProcessors::normalizeName($processor);
        $intent->forceFill([
            'processor' => $processor,
            'status' => 'processing',
            'last_error' => null,
            'expires_at' => now()->addHours(2),
        ])->save();

        $gateway = $this->paymentPlatformResolver->resolveService($processor);
        $planId = $intent->type === 'subscription_purchase' ? $intent->target_plan_id : null;

        $response = $gateway->handlePayment((float) $intent->amount_due, $planId, $intent);

        if (!is_object($response) || !isset($response->success) || $response->success !== true) {
            $this->markIntentAsFailed(
                $intent,
                is_object($response) && isset($response->error)
                    ? (string) $response->error
                    : __('Could not start payment checkout.')
            );
        } elseif (isset($response->reference) && is_string($response->reference) && trim($response->reference) !== '') {
            $this->markExternalReference($intent, trim($response->reference));
        }

        return $response;
    }

    public function completeIntentByUuid(
        string $intentUuid,
        string $processor,
        string $externalId,
        float $paidAmount,
        array $paymentPayload = [],
        ?string $resolvedPaymentMethod = null,
        ?string $paidCurrency = null
    ): object {
        $intent = BillingCheckoutIntent::query()->where('uuid', $intentUuid)->first();

        if (!$intent) {
            Log::warning('Billing checkout intent was not found during payment completion.', [
                'intent_uuid' => $intentUuid,
                'processor' => $processor,
                'external_id' => $externalId,
            ]);

            return (object) [
                'success' => false,
                'message' => __('Payment confirmation could not be matched to a checkout request.'),
            ];
        }

        return $this->completeIntent(
            $intent,
            $processor,
            $externalId,
            $paidAmount,
            $paymentPayload,
            $resolvedPaymentMethod,
            $paidCurrency
        );
    }

    public function completeIntent(
        BillingCheckoutIntent $intent,
        string $processor,
        string $externalId,
        float $paidAmount,
        array $paymentPayload = [],
        ?string $resolvedPaymentMethod = null,
        ?string $paidCurrency = null
    ): object {
        return DB::transaction(function () use ($intent, $processor, $externalId, $paidAmount, $paymentPayload, $resolvedPaymentMethod, $paidCurrency) {
            /** @var BillingCheckoutIntent $lockedIntent */
            $lockedIntent = BillingCheckoutIntent::query()
                ->whereKey($intent->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedIntent->status === 'completed') {
                return (object) [
                    'success' => true,
                    'message' => __('Payment was already processed successfully.'),
                    'intent' => $lockedIntent->fresh(),
                ];
            }

            if (in_array($lockedIntent->status, ['canceled', 'expired'], true)) {
                return (object) [
                    'success' => false,
                    'message' => __('This checkout request is no longer active.'),
                ];
            }

            $normalizedProcessor = SupportedPaymentProcessors::normalizeName($processor);
            if (!$this->processorMatchesIntent($lockedIntent, $normalizedProcessor)) {
                $this->markIntentAsFailed(
                    $lockedIntent,
                    __('The confirmed payment processor does not match the checkout request.')
                );

                Log::warning('Billing checkout intent processor mismatch detected.', [
                    'intent_uuid' => $lockedIntent->uuid,
                    'expected_processor' => $lockedIntent->processor,
                    'actual_processor' => $normalizedProcessor,
                    'external_id' => $externalId,
                ]);

                return (object) [
                    'success' => false,
                    'message' => __('The confirmed payment processor does not match the checkout request.'),
                ];
            }

            $normalizedPaidCurrency = $this->normalizeCurrency($paidCurrency);
            if (!$this->currencyMatchesIntent($lockedIntent, $normalizedPaidCurrency)) {
                $this->markIntentAsFailed(
                    $lockedIntent,
                    __('The confirmed payment currency does not match the checkout request.')
                );

                Log::warning('Billing checkout intent currency mismatch detected.', [
                    'intent_uuid' => $lockedIntent->uuid,
                    'processor' => $normalizedProcessor,
                    'external_id' => $externalId,
                    'expected_currency' => $lockedIntent->currency,
                    'actual_currency' => $normalizedPaidCurrency,
                ]);

                return (object) [
                    'success' => false,
                    'message' => __('The confirmed payment currency does not match the checkout request.'),
                ];
            }

            $expectedAmount = round((float) $lockedIntent->amount_due, 2);
            $actualAmount = round($paidAmount, 2);

            if (!$this->amountsMatch($expectedAmount, $actualAmount)) {
                $this->markIntentAsFailed(
                    $lockedIntent,
                    __('The confirmed payment amount does not match the checkout total.')
                );

                Log::warning('Billing checkout intent amount mismatch detected.', [
                    'intent_uuid' => $lockedIntent->uuid,
                    'processor' => $processor,
                    'external_id' => $externalId,
                    'expected_amount' => $expectedAmount,
                    'actual_amount' => $actualAmount,
                ]);

                return (object) [
                    'success' => false,
                    'message' => __('The confirmed payment amount does not match the checkout total.'),
                ];
            }

            $couponReserved = $this->redeemCouponForIntent($lockedIntent);
            if (!$couponReserved->success) {
                $this->markIntentAsFailed($lockedIntent, $couponReserved->message);

                Log::warning('Billing checkout intent coupon redemption failed.', [
                    'intent_uuid' => $lockedIntent->uuid,
                    'coupon_id' => $lockedIntent->coupon_id,
                    'coupon_code' => $lockedIntent->coupon_code,
                    'processor' => $normalizedProcessor,
                    'external_id' => $externalId,
                ]);

                return $couponReserved;
            }

            $payment = $this->resolveOrCreatePayment(
                $lockedIntent,
                $normalizedProcessor,
                $externalId,
                $actualAmount,
                $paymentPayload,
                $resolvedPaymentMethod
            );

            $invoice = null;

            if ($lockedIntent->type === 'subscription_purchase') {
                $invoice = $this->resolveOrCreateSubscriptionInvoice($lockedIntent, $payment);
            } else {
                $transaction = $this->resolveOrCreatePaymentTransaction($lockedIntent, $payment, $paymentPayload, $resolvedPaymentMethod);
                SubscriptionService::activateSubscriptionIfInactiveAndExpiredWithCredits(
                    $lockedIntent->billing_organization_id,
                    (int) ($lockedIntent->user_id ?? 0)
                );
                $this->sendGenericTemplateToIntent('Payment Success', $lockedIntent);
                event(new NewPaymentEvent($transaction, $lockedIntent->billing_organization_id));
            }

            $lockedIntent->forceFill([
                'processor' => $normalizedProcessor,
                'external_reference' => $externalId,
                'status' => 'completed',
                'completed_payment_id' => $payment?->id,
                'completed_invoice_id' => $invoice?->id,
                'completed_at' => now(),
                'last_error' => null,
            ])->save();

            return (object) [
                'success' => true,
                'message' => $lockedIntent->type === 'subscription_purchase'
                    ? __('Payment Successful!')
                    : __('Payment processed successfully!'),
                'intent' => $lockedIntent->fresh(),
                'invoice' => $invoice,
                'payment' => $payment,
            ];
        });
    }

    public function completeWithoutGateway(BillingCheckoutIntent $intent): object
    {
        return DB::transaction(function () use ($intent) {
            /** @var BillingCheckoutIntent $lockedIntent */
            $lockedIntent = BillingCheckoutIntent::query()
                ->whereKey($intent->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedIntent->status === 'completed') {
                return (object) [
                    'success' => true,
                    'intent' => $lockedIntent->fresh(),
                    'invoice' => $lockedIntent->invoice,
                ];
            }

            if ($lockedIntent->type !== 'subscription_purchase') {
                throw ValidationException::withMessages([
                    'checkout' => __('Only subscription checkout can be completed without a payment gateway.'),
                ]);
            }

            $couponReserved = $this->redeemCouponForIntent($lockedIntent);
            if (!$couponReserved->success) {
                $this->markIntentAsFailed($lockedIntent, $couponReserved->message);

                return $couponReserved;
            }

            $invoice = $this->resolveOrCreateSubscriptionInvoice($lockedIntent, null);

            $lockedIntent->forceFill([
                'status' => 'completed',
                'completed_invoice_id' => $invoice?->id,
                'completed_at' => now(),
                'last_error' => null,
            ])->save();

            return (object) [
                'success' => true,
                'intent' => $lockedIntent->fresh(),
                'invoice' => $invoice,
            ];
        });
    }

    public function markExternalReference(BillingCheckoutIntent $intent, string $reference): void
    {
        $snapshot = is_array($intent->snapshot_json) ? $intent->snapshot_json : [];
        $snapshot['external_reference'] = $reference;

        $intent->forceFill([
            'external_reference' => $reference,
            'snapshot_json' => $snapshot,
        ])->save();
    }

    public function failIntentByExternalReference(string $processor, string $reference, string $message): ?BillingCheckoutIntent
    {
        $intent = BillingCheckoutIntent::query()
            ->where('processor', SupportedPaymentProcessors::normalizeName($processor))
            ->whereIn('status', ['pending', 'processing'])
            ->where(function ($query) use ($reference) {
                $query->where('external_reference', $reference)
                    ->orWhere('uuid', $reference);
            })
            ->latest('id')
            ->first();

        if (!$intent) {
            return null;
        }

        $this->markIntentAsFailed($intent, $message);

        return $intent->fresh();
    }

    private function resolveOrCreatePayment(
        BillingCheckoutIntent $intent,
        string $processor,
        string $externalId,
        float $paidAmount,
        array $paymentPayload = [],
        ?string $resolvedPaymentMethod = null
    ): BillingPayment {
        $normalizedProcessor = SupportedPaymentProcessors::normalizeName($processor);
        $existing = BillingPayment::query()
            ->where('processor', $normalizedProcessor)
            ->where('details', $externalId)
            ->first();

        if ($existing) {
            return $existing;
        }

        return BillingPayment::create([
            'organization_id' => $intent->billing_organization_id,
            'processor' => $normalizedProcessor,
            'payment_method' => $resolvedPaymentMethod
                ?: BillingPaymentMethodResolver::fromProcessor($normalizedProcessor, $paymentPayload),
            'details' => $externalId,
            'amount' => $paidAmount,
        ]);
    }

    private function resolveOrCreatePaymentTransaction(
        BillingCheckoutIntent $intent,
        BillingPayment $payment,
        array $paymentPayload = [],
        ?string $resolvedPaymentMethod = null
    ): BillingTransaction {
        $existing = BillingTransaction::query()
            ->where('entity_type', 'payment')
            ->where('entity_id', $payment->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return BillingTransaction::create([
            'organization_id' => $intent->billing_organization_id,
            'entity_type' => 'payment',
            'entity_id' => $payment->id,
            'description' => __('Payment via :method', [
                'method' => BillingPaymentMethodResolver::displayLabel(
                    $resolvedPaymentMethod ?: $payment->payment_method,
                    $payment->processor
                ),
            ]),
            'amount' => (float) $payment->amount,
            'created_by' => (int) ($intent->user_id ?? 0),
        ]);
    }

    private function resolveOrCreateSubscriptionInvoice(
        BillingCheckoutIntent $intent,
        ?BillingPayment $payment
    ): BillingInvoice {
        if ($intent->completed_invoice_id) {
            $invoice = BillingInvoice::find($intent->completed_invoice_id);

            if ($invoice) {
                if ($payment && BillingPayment::hasColumn('invoice_id') && !$payment->invoice_id) {
                    $payment->update(['invoice_id' => $invoice->id]);
                }

                return $invoice;
            }
        }

        if ($payment && BillingPayment::hasColumn('invoice_id') && $payment->invoice_id) {
            $invoice = BillingInvoice::find($payment->invoice_id);

            if ($invoice) {
                return $invoice;
            }
        }

        $snapshot = is_array($intent->snapshot_json) ? $intent->snapshot_json : [];
        $couponAmount = $this->toDecimal($intent->coupon_amount);
        $invoiceTotal = max(0, round($this->toDecimal($snapshot['netAmount'] ?? $intent->net_total) - $couponAmount, 2));
        $grossAmount = $this->toDecimal($snapshot['grossAmount'] ?? $intent->gross_amount);
        $taxTotal = $this->toDecimal($snapshot['totalTaxAmount'] ?? $intent->tax_total);
        $newCreditAmount = $this->toDecimal(data_get($snapshot, 'credit.new', 0));

        $invoice = BillingInvoice::create([
            'organization_id' => $intent->billing_organization_id,
            'plan_id' => $intent->target_plan_id,
            'subtotal' => $grossAmount,
            'coupon_id' => $intent->coupon_id,
            'coupon_amount' => $couponAmount,
            'tax' => $taxTotal,
            'tax_type' => data_get($snapshot, 'isTaxInclusive', false) === true ? 'inclusive' : 'exclusive',
            'total' => $invoiceTotal,
        ]);

        foreach ((array) ($snapshot['taxRates'] ?? []) as $taxRate) {
            BillingTaxRate::create([
                'invoice_id' => $invoice->id,
                'rate' => $this->toDecimal($taxRate['amount'] ?? 0),
                'amount' => $this->toDecimal($taxRate['percentage'] ?? 0),
            ]);
        }

        BillingTransaction::firstOrCreate([
            'entity_type' => 'invoice',
            'entity_id' => $invoice->id,
        ], [
            'organization_id' => $intent->billing_organization_id,
            'description' => __('Invoice'),
            'amount' => -$invoiceTotal,
            'created_by' => (int) ($intent->user_id ?? 0),
        ]);

        if ($newCreditAmount > 0) {
            $credit = BillingCredit::create([
                'organization_id' => $intent->billing_organization_id,
                'description' => __('Credit memo'),
                'amount' => abs($newCreditAmount),
            ]);

            BillingTransaction::firstOrCreate([
                'entity_type' => 'credit',
                'entity_id' => $credit->id,
            ], [
                'organization_id' => $intent->billing_organization_id,
                'description' => __('Credit memo'),
                'amount' => abs($newCreditAmount),
                'created_by' => (int) ($intent->user_id ?? 0),
            ]);
        }

        if ($payment) {
            $transaction = $this->resolveOrCreatePaymentTransaction($intent, $payment);

            if (BillingPayment::hasColumn('invoice_id') && !$payment->invoice_id) {
                $payment->update(['invoice_id' => $invoice->id]);
            }

            event(new NewPaymentEvent($transaction, $intent->billing_organization_id));
        }

        $this->updateLocalSubscriptionFromIntent($intent);
        $this->sendSubscriptionPurchaseTemplate($intent);

        return $invoice;
    }

    private function updateLocalSubscriptionFromIntent(BillingCheckoutIntent $intent): void
    {
        if (!$intent->target_plan_id) {
            return;
        }

        $plan = SubscriptionPlan::find($intent->target_plan_id);
        if (!$plan) {
            throw ValidationException::withMessages([
                'plan' => __('The selected plan could not be found.'),
            ]);
        }

        $validUntil = $plan->period === 'yearly'
            ? now()->addYear()
            : now()->addMonth();

        $subscription = app(SubscriptionPlanLimitService::class)
            ->subscriptionForOrganization((int) $intent->billing_organization_id);

        if ($subscription) {
            $subscription->update([
                'plan_id' => $plan->id,
                'scheduled_plan_id' => null,
                'start_date' => now(),
                'valid_until' => $validUntil,
                'scheduled_plan_change_at' => null,
                'status' => 'active',
            ]);

            return;
        }

        Subscription::create([
            'organization_id' => $intent->billing_organization_id,
            'plan_id' => $plan->id,
            'scheduled_plan_id' => null,
            'status' => 'active',
            'start_date' => now(),
            'valid_until' => $validUntil,
            'scheduled_plan_change_at' => null,
        ]);
    }

    private function redeemCouponForIntent(BillingCheckoutIntent $intent): object
    {
        if (!$intent->coupon_id || $this->toDecimal($intent->coupon_amount) <= 0) {
            return (object) [
                'success' => true,
            ];
        }

        $coupon = Coupon::query()
            ->where('id', $intent->coupon_id)
            ->lockForUpdate()
            ->first();

        if (!$coupon || $coupon->status !== 'active' || $coupon->deleted_at !== null) {
            return (object) [
                'success' => false,
                'message' => __('The coupon code is no longer available.'),
            ];
        }

        $quantity = $coupon->quantity;
        $redeemed = (int) ($coupon->quantity_redeemed ?? 0);

        if ($quantity !== null && $redeemed >= (int) $quantity) {
            return (object) [
                'success' => false,
                'message' => __('The coupon has expired!'),
            ];
        }

        $coupon->forceFill([
            'quantity_redeemed' => $redeemed + 1,
        ])->save();

        return (object) [
            'success' => true,
        ];
    }

    private function resolveCouponFromSnapshot(array $snapshot): ?Coupon
    {
        $code = trim((string) data_get($snapshot, 'coupon.code', ''));
        if ($code === '') {
            return null;
        }

        return Coupon::query()
            ->where('code', $code)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();
    }

    private function resolvedBalanceApplied(array $snapshot): float
    {
        $availableCredit = $this->toDecimal(data_get($snapshot, 'credit.available', 0));
        $newCredit = $this->toDecimal(data_get($snapshot, 'credit.new', 0));

        return round($availableCredit + $newCredit, 2);
    }

    private function markIntentAsFailed(BillingCheckoutIntent $intent, string $message): void
    {
        $wasFailed = $intent->status === 'failed';

        $intent->forceFill([
            'status' => 'failed',
            'last_error' => $message,
        ])->save();

        if (!$wasFailed) {
            $this->sendGenericTemplateToIntent('Payment Failed', $intent);
        }
    }

    private function sendSubscriptionPurchaseTemplate(BillingCheckoutIntent $intent): void
    {
        $recipient = $this->resolveTemplateRecipient($intent);
        if (!$recipient) {
            return;
        }

        $plan = SubscriptionPlan::find($intent->target_plan_id);
        if (!$plan) {
            $plan = (object) [
                'name' => (string) data_get($intent->snapshot_json, 'selected_plan_name', __('Subscription plan')),
            ];
        }

        Email::sendSubscriptionEmail('Subscription Plan Purchase', $recipient, $plan);
    }

    private function sendGenericTemplateToIntent(string $template, BillingCheckoutIntent $intent): void
    {
        $recipient = $this->resolveTemplateRecipient($intent);
        if (!$recipient) {
            return;
        }

        Email::send($template, $recipient);
    }

    private function resolveTemplateRecipient(BillingCheckoutIntent $intent): ?User
    {
        $directUser = $intent->user_id ? User::find($intent->user_id) : null;
        if ($directUser && filled($directUser->email)) {
            return $directUser;
        }

        $ownerRole = OrganizationRole::query()
            ->whereNull('organization_id')
            ->where('name', 'Owner')
            ->first();

        if (!$ownerRole) {
            return null;
        }

        $ownerTeam = Team::query()
            ->where('organization_id', $intent->billing_organization_id)
            ->where('organization_role_id', $ownerRole->id)
            ->first();

        if (!$ownerTeam) {
            return null;
        }

        $owner = User::find($ownerTeam->user_id);

        return $owner && filled($owner->email) ? $owner : null;
    }

    private function resolveBillingOrganizationId(int $organizationId): int
    {
        return $this->organizationHierarchyService->billingOwnerId($organizationId) ?? $organizationId;
    }

    private function toDecimal(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '', $value);
        }

        return round((float) $value, 2);
    }

    private function processorMatchesIntent(BillingCheckoutIntent $intent, string $processor): bool
    {
        $expected = SupportedPaymentProcessors::normalizeName((string) ($intent->processor ?? ''));

        return $expected === '' || $expected === $processor;
    }

    private function currencyMatchesIntent(BillingCheckoutIntent $intent, ?string $paidCurrency): bool
    {
        if ($paidCurrency === null) {
            return true;
        }

        $expected = $this->normalizeCurrency($intent->currency);

        return $expected === null || $expected === $paidCurrency;
    }

    private function normalizeCurrency(?string $currency): ?string
    {
        $normalized = strtoupper(trim((string) $currency));

        return $normalized !== '' ? $normalized : null;
    }

    private function amountsMatch(float $expectedAmount, float $actualAmount): bool
    {
        return abs($expectedAmount - $actualAmount) < 0.01;
    }
}
