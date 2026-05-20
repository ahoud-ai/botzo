<?php

namespace App\Services;

use App\Contracts\PaymentGatewayContract;
use App\Events\NewPaymentEvent;
use App\Models\BillingCheckoutIntent;
use App\Models\BillingPayment;
use App\Models\BillingTransaction;
use App\Models\Organization;
use App\Models\PaymentGateway;
use App\Models\SubscriptionPlan;
use App\Support\BillingPaymentMethodResolver;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MoyasarService implements PaymentGatewayContract
{
    private const BASE_URI = 'https://api.moyasar.com/v1';

    private SubscriptionService $subscriptionService;
    private array $config;
    private string $secretKey;
    private string $webhookSecret;

    public function __construct()
    {
        $this->subscriptionService = new SubscriptionService();

        $methodInfo = PaymentGateway::query()
            ->where('name', 'Moyasar')
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->first();

        if (!$methodInfo) {
            throw new \RuntimeException(__('Moyasar payment gateway is not configured.'));
        }

        $this->config = $this->decodeMetadata($methodInfo->metadata);
        $activeConfig = $this->resolveActiveConfig($this->config);
        $this->secretKey = trim((string) ($activeConfig['secret_key'] ?? $this->config['secret_key'] ?? ''));
        $this->webhookSecret = trim((string) ($activeConfig['webhook_secret'] ?? $this->config['webhook_secret'] ?? ''));

        if ($this->secretKey === '') {
            throw new \RuntimeException(__('Moyasar payment gateway is not configured.'));
        }
    }

    public function handlePayment($amount, $planId = null, $intent = null)
    {
        $organizationId = session()->get('current_organization');
        $userId = auth()->id();
        $user = User::where('id', $userId)->first();

        if (!$organizationId || !$userId || !$user) {
            return (object) [
                'success' => false,
                'error' => __('Moyasar payment failed. Please try again.'),
            ];
        }

        $amountInMinor = $this->convertToMinorUnit($amount);
        if ($amountInMinor <= 0) {
            return (object) [
                'success' => false,
                'error' => __('Moyasar payment failed. Please try again.'),
            ];
        }

        $checkoutContext = $this->buildCheckoutContext(
            (int) $organizationId,
            $user,
            $planId !== null && is_numeric($planId) ? (int) $planId : null,
            $intent instanceof BillingCheckoutIntent ? $intent : null
        );

        $payload = [
            'amount' => $amountInMinor,
            'currency' => $this->resolveCurrency(),
            'description' => $checkoutContext['description'],
            'success_url' => url('payment/moyasar'),
            'back_url' => url('billing'),
            'metadata' => $checkoutContext['metadata'],
        ];

        if ($this->webhookSecret !== '') {
            $payload['callback_url'] = url('payment/moyasar/webhook') . '?token=' . urlencode($this->webhookSecret);
        }

        $response = $this->httpClient()->post($this->endpoint('invoices'), $payload);

        if (!$response->successful()) {
            Log::warning('Moyasar invoice creation failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return (object) [
                'success' => false,
                'error' => __('Moyasar payment failed. Please try again.'),
            ];
        }

        $responseData = $response->json();
        $redirectUrl = data_get($responseData, 'url');
        $invoiceReference = data_get($responseData, 'id');

        if (!is_string($redirectUrl) || trim($redirectUrl) === '') {
            Log::warning('Moyasar invoice checkout URL missing', [
                'response' => $responseData,
            ]);

            return (object) [
                'success' => false,
                'error' => __('Moyasar payment failed. Please try again.'),
            ];
        }

        if (is_string($invoiceReference) && trim($invoiceReference) !== '') {
            session()->put('moyasar_last_reference', trim($invoiceReference));
        }

        return (object) [
            'success' => true,
            'data' => $redirectUrl,
            'reference' => $invoiceReference,
        ];
    }

    public function completePayment($referenceId)
    {
        $reference = $this->normalizeReferenceId($referenceId);
        if ($reference === null) {
            return (object) ['success' => false];
        }

        $invoice = $this->fetchInvoice($reference);
        if (is_array($invoice)) {
            return $this->completeUsingInvoice($invoice);
        }

        $payment = $this->fetchPayment($reference);
        if (is_array($payment)) {
            return $this->completeUsingPayment($payment);
        }

        return (object) ['success' => false];
    }

    public function updateSubscription($referenceId)
    {
        return $this->completePayment($referenceId);
    }

    public function handleWebhook(Request $request)
    {
        if (!$this->isValidWebhookSignature($request)) {
            return response()->json([
                'status' => 'error',
                'message' => __('Moyasar webhook signature is invalid.'),
            ], 401);
        }

        $referenceId = $this->extractReferenceId($request);
        if ($referenceId === null) {
            return response()->json([
                'status' => 'error',
                'message' => __('Moyasar webhook is missing payment reference.'),
            ], 422);
        }

        $result = $this->updateSubscription($referenceId);
        if ($result->success === true) {
            return response()->json([
                'status' => 'success',
            ], 200);
        }

        return response()->json([
            'status' => 'ignored',
        ], 202);
    }

    private function completeUsingInvoice(array $invoice): object
    {
        $status = strtolower((string) data_get($invoice, 'status', ''));
        if ($status !== 'paid') {
            return (object) ['success' => false];
        }

        $paidPayment = $this->selectPaidPayment((array) data_get($invoice, 'payments', []));
        $metadataSource = $paidPayment['metadata'] ?? data_get($invoice, 'metadata', []);
        $metadata = $this->normalizeMetadata($metadataSource);
        $externalId = (string) ($paidPayment['id'] ?? data_get($invoice, 'id', ''));
        $amountMinor = (int) ($paidPayment['amount'] ?? data_get($invoice, 'amount', 0));

        return $this->persistSuccessfulPayment($externalId, $metadata, $amountMinor, $paidPayment);
    }

    private function completeUsingPayment(array $payment): object
    {
        $status = strtolower((string) data_get($payment, 'status', ''));
        if ($status !== 'paid') {
            return (object) ['success' => false];
        }

        $metadata = $this->normalizeMetadata(data_get($payment, 'metadata', []));
        $invoiceId = $this->normalizeReferenceId(data_get($payment, 'invoice_id'));

        if ($metadata === [] && $invoiceId !== null) {
            $invoice = $this->fetchInvoice($invoiceId);

            if (is_array($invoice)) {
                Log::info('Moyasar payment completion fell back to invoice metadata.', [
                    'payment_id' => data_get($payment, 'id'),
                    'invoice_id' => $invoiceId,
                ]);

                return $this->completeUsingInvoice($invoice);
            }
        }

        $externalId = (string) data_get($payment, 'id', '');
        $amountMinor = (int) data_get($payment, 'amount', 0);

        return $this->persistSuccessfulPayment($externalId, $metadata, $amountMinor, $payment);
    }

    private function persistSuccessfulPayment(string $externalId, array $metadata, int $amountMinor, array $paymentPayload = []): object
    {
        $intentUuid = $this->firstFilledMetadataValue($metadata, [
            'botzo_checkout_id',
            'checkout_intent_uuid',
            'intent_uuid',
        ]);
        $amount = $this->convertFromMinorUnit($amountMinor);

        if ($intentUuid !== '') {
            return app(BillingCheckoutIntentService::class)->completeIntentByUuid(
                $intentUuid,
                'moyasar',
                $externalId,
                $amount,
                $paymentPayload,
                BillingPaymentMethodResolver::fromProcessor('moyasar', $paymentPayload),
                $this->currencyFromPayload($paymentPayload)
            );
        }

        $organizationId = $this->firstNumericMetadataValue($metadata, [
            'botzo_organization_id',
            'organization_id',
        ]);
        if ($organizationId <= 0 || $externalId === '') {
            Log::warning('Moyasar payment metadata is missing required fields', [
                'external_id' => $externalId,
                'metadata' => $metadata,
            ]);

            return (object) ['success' => false];
        }

        $userId = $this->firstNumericMetadataValue($metadata, [
            'botzo_user_id',
            'user_id',
        ]) ?: (int) (auth()->id() ?? 0);
        $planId = $this->firstNumericMetadataValue($metadata, [
            'subscription_plan_id',
            'plan_id',
        ]);

        return $this->withPreviousPaymentLock($externalId, function () use ($organizationId, $externalId, $amount, $userId, $planId, $paymentPayload) {
            DB::transaction(function () use ($organizationId, $externalId, $amount, $userId, $planId, $paymentPayload) {
                $exists = BillingPayment::where('processor', 'moyasar')
                    ->where('details', $externalId)
                    ->lockForUpdate()
                    ->first();

                if ($exists) {
                    return;
                }

                $payment = BillingPayment::create([
                    'organization_id' => $organizationId,
                    'processor' => 'moyasar',
                    'payment_method' => BillingPaymentMethodResolver::fromProcessor('moyasar', $paymentPayload),
                    'details' => $externalId,
                    'amount' => $amount,
                ]);

                $transaction = BillingTransaction::create([
                    'organization_id' => $organizationId,
                    'entity_type' => 'payment',
                    'entity_id' => $payment->id,
                    'description' => __('Payment via :method', [
                        'method' => BillingPaymentMethodResolver::displayLabel($payment->payment_method, $payment->processor),
                    ]),
                    'amount' => $amount,
                    'created_by' => $userId,
                ]);

                if ($planId === null) {
                    $invoice = $this->subscriptionService->activateSubscriptionIfInactiveAndExpiredWithCredits($organizationId, $userId);
                } else {
                    $invoice = $this->subscriptionService->updateSubscriptionPlan($organizationId, $planId, $userId);
                }

                if ($invoice) {
                    $payment->update([
                        'invoice_id' => $invoice->id,
                    ]);
                }

                event(new NewPaymentEvent($transaction, $organizationId));
            });

            return (object) ['success' => true];
        });
    }

    private function fetchInvoice(string $invoiceId): ?array
    {
        $response = $this->httpClient()->get($this->endpoint('invoices/' . $invoiceId));
        if ($response->successful()) {
            $data = $response->json();
            return is_array($data) ? $data : null;
        }

        if ($response->status() !== 404) {
            Log::warning('Moyasar invoice fetch failed', [
                'invoice_id' => $invoiceId,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
        }

        return null;
    }

    private function fetchPayment(string $paymentId): ?array
    {
        $response = $this->httpClient()->get($this->endpoint('payments/' . $paymentId));
        if ($response->successful()) {
            $data = $response->json();
            return is_array($data) ? $data : null;
        }

        if ($response->status() !== 404) {
            Log::warning('Moyasar payment fetch failed', [
                'payment_id' => $paymentId,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
        }

        return null;
    }

    private function selectPaidPayment(array $payments): ?array
    {
        foreach ($payments as $payment) {
            if (!is_array($payment)) {
                continue;
            }

            $status = strtolower((string) ($payment['status'] ?? ''));
            if ($status === 'paid') {
                return $payment;
            }
        }

        foreach ($payments as $payment) {
            if (is_array($payment)) {
                return $payment;
            }
        }

        return null;
    }

    private function normalizeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_object($metadata)) {
            return (array) $metadata;
        }

        return [];
    }

    /**
     * @return array{description:string,metadata:array<string,string>}
     */
    private function buildCheckoutContext(
        int $organizationId,
        User $user,
        ?int $planId,
        ?BillingCheckoutIntent $intent
    ): array {
        $organization = Organization::query()->find($organizationId);
        $plan = $planId ? SubscriptionPlan::query()->find($planId) : null;
        $snapshot = $intent && is_array($intent->snapshot_json) ? $intent->snapshot_json : [];
        $planName = trim((string) (
            $snapshot['selected_plan_name']
            ?? $plan?->localizedName(app()->getLocale())
            ?? $plan?->name
            ?? ''
        ));
        $periodLabel = $plan ? $this->planPeriodLabel($plan->period) : '';
        $checkoutType = $intent?->type === 'account_topup' ? 'account_topup' : 'subscription_purchase';
        $reference = (string) Str::uuid();

        $metadata = [
            'transaction_type' => $checkoutType === 'account_topup'
                ? __('Account balance top-up')
                : __('Subscription purchase'),
            'subscription_plan' => $checkoutType === 'subscription_purchase' && $planName !== '' ? $planName : null,
            'billing_cycle' => $checkoutType === 'subscription_purchase' && $periodLabel !== '' ? $periodLabel : null,
            'organization_name' => $organization?->name ? trim((string) $organization->name) : null,
            'customer_name' => trim((string) $user->full_name) !== '' ? trim((string) $user->full_name) : null,
            'customer_email' => trim((string) $user->email) !== '' ? trim((string) $user->email) : null,
            'botzo_reference' => $reference,
            'botzo_checkout_id' => $intent?->uuid ? (string) $intent->uuid : null,
        ];

        if (!$intent) {
            $metadata['botzo_organization_id'] = (string) $organizationId;
            $metadata['botzo_user_id'] = (string) $user->id;

            if ($planId !== null) {
                $metadata['subscription_plan_id'] = (string) $planId;
            }
        }

        return [
            'description' => $this->checkoutDescription($checkoutType, $planName, $periodLabel),
            'metadata' => array_filter(
                $metadata,
                static fn ($value) => is_string($value) && trim($value) !== ''
            ),
        ];
    }

    private function checkoutDescription(string $checkoutType, string $planName, string $periodLabel): string
    {
        if ($checkoutType === 'account_topup') {
            return __('Account balance top-up');
        }

        if ($planName !== '' && $periodLabel !== '') {
            return __('Subscription to :plan (:period billing)', [
                'plan' => $planName,
                'period' => $periodLabel,
            ]);
        }

        if ($planName !== '') {
            return __('Subscription to :plan', [
                'plan' => $planName,
            ]);
        }

        return __('Subscription payment');
    }

    private function planPeriodLabel(?string $period): string
    {
        return match ($period) {
            'monthly' => __('Monthly'),
            'yearly' => __('Yearly'),
            default => '',
        };
    }

    /**
     * @param array<int, string> $keys
     */
    private function firstFilledMetadataValue(array $metadata, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($metadata[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param array<int, string> $keys
     */
    private function firstNumericMetadataValue(array $metadata, array $keys): ?int
    {
        foreach ($keys as $key) {
            $value = $metadata[$key] ?? null;
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    private function isValidWebhookSignature(Request $request): bool
    {
        if ($this->webhookSecret === '') {
            Log::warning('Moyasar webhook rejected because webhook secret is missing.');

            return false;
        }

        $payloadSecret = (string) $request->input('secret_token', '');
        if ($payloadSecret !== '') {
            return hash_equals($this->webhookSecret, $payloadSecret);
        }

        $token = (string) $request->query('token', '');
        if ($token !== '') {
            return hash_equals($this->webhookSecret, $token);
        }

        return false;
    }

    private function withPreviousPaymentLock(string $externalId, callable $callback): object
    {
        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return $callback();
        }

        $lockName = 'moyasar-payment-' . sha1($externalId);
        $lockAcquired = false;

        try {
            $lockResult = DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);
            $lockAcquired = (int) ($lockResult->acquired ?? 0) === 1;
        } catch (\Throwable $exception) {
            Log::warning('Moyasar previous payment lock could not be acquired through the database driver.', [
                'reference' => $externalId,
                'error' => $exception->getMessage(),
            ]);
        }

        if (!$lockAcquired) {
            Log::warning('Moyasar previous payment completion skipped because the payment lock was not acquired.', [
                'reference' => $externalId,
            ]);

            return (object) ['success' => false];
        }

        try {
            return $callback();
        } finally {
            try {
                DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
            } catch (\Throwable $exception) {
                Log::warning('Moyasar previous payment lock release failed.', [
                    'reference' => $externalId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function decodeMetadata($metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (!is_string($metadata) || trim($metadata) === '') {
            return [];
        }

        $decoded = json_decode($metadata, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function resolveActiveConfig(array $metadata): array
    {
        $activeMode = strtolower((string) ($metadata['active_mode'] ?? $metadata['mode'] ?? 'test'));
        $activeMode = in_array($activeMode, ['test', 'live'], true) ? $activeMode : 'test';
        $activeConfig = $metadata[$activeMode] ?? null;

        return is_array($activeConfig) ? $activeConfig : [];
    }

    private function resolveCurrency(): string
    {
        $currency = app(SettingValueService::class)->getString('currency', 'SAR');
        $currency = strtoupper((string) $currency);

        return $currency !== '' ? $currency : 'SAR';
    }

    private function endpoint(string $path): string
    {
        return rtrim(self::BASE_URI, '/') . '/' . ltrim($path, '/');
    }

    private function httpClient()
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout(30)
            ->withBasicAuth($this->secretKey, '');
    }

    private function convertToMinorUnit($amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function convertFromMinorUnit(int $amount): float
    {
        return round($amount / 100, 2);
    }

    private function currencyFromPayload(array $payload): ?string
    {
        $currency = data_get($payload, 'currency')
            ?? data_get($payload, 'amount.currency')
            ?? data_get($payload, 'source.currency');

        return is_string($currency) && trim($currency) !== ''
            ? strtoupper(trim($currency))
            : null;
    }

    private function normalizeReferenceId(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);
        return $normalized !== '' ? $normalized : null;
    }

    private function extractReferenceId(Request $request): ?string
    {
        $candidates = [
            $request->input('id'),
            $request->input('invoice_id'),
            $request->input('payment_id'),
            $request->input('data.id'),
            $request->input('data.invoice_id'),
            $request->input('data.invoice.id'),
            $request->input('data.payment.id'),
            $request->input('payment.id'),
            $request->input('invoice.id'),
            $request->input('resource.id'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }
}
