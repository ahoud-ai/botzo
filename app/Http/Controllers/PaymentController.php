<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller as BaseController;
use App\Models\BillingCheckoutIntent;
use App\Resolvers\PaymentPlatformResolver;
use App\Services\BillingCheckoutIntentService;
use App\Services\OrganizationHierarchyService;
use App\Support\SupportedPaymentProcessors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends BaseController
{
    public function __construct()
    {
        $this->paymentPlatformResolver = new PaymentPlatformResolver();
    }

    public function processPayment(Request $request, string $processor = 'moyasar')
    {
        $transactionId = null;

        try {
            $paymentPlatform = $this->paymentPlatformResolver->resolveService($processor);
            session()->put('paymentPlatform', SupportedPaymentProcessors::normalizeName((string) $processor));

            $transactionId = $request->input('transaction_id')
                ?? $request->input('id')
                ?? $request->input('payment_id')
                ?? $request->input('invoice_id')
                ?? $request->input('data.id')
                ?? $request->input('data.invoice_id')
                ?? $request->input('data.invoice.id');

            if (!$transactionId && strtolower((string) $processor) === 'moyasar') {
                $transactionId = session()->pull('moyasar_last_reference');
            }

            if (!$transactionId && strtolower((string) $processor) === 'moyasar' && auth('user')->check()) {
                $organizationId = session()->get('current_organization');
                $billingOwnerId = app(OrganizationHierarchyService::class)->billingOwnerId((int) $organizationId) ?? (int) $organizationId;

                $transactionId = BillingCheckoutIntent::query()
                    ->where('processor', 'moyasar')
                    ->whereIn('status', ['pending', 'processing'])
                    ->where('user_id', auth()->id())
                    ->where('billing_organization_id', $billingOwnerId)
                    ->whereNotNull('external_reference')
                    ->orderByDesc('id')
                    ->value('external_reference');
            }

            if (!$transactionId) {
                return redirect('/billing')->with('status', [
                    'type' => 'error',
                    'message' => __('Missing transaction reference.'),
                ]);
            }

            $completionMethod = method_exists($paymentPlatform, 'completePayment')
                ? 'completePayment'
                : 'updateSubscription';

            if (!method_exists($paymentPlatform, $completionMethod)) {
                return redirect('/billing')->with('status', [
                    'type' => 'error',
                    'message' => __('Unsupported payment callback for selected processor.'),
                ]);
            }

            $res = $paymentPlatform->{$completionMethod}($transactionId);
            $isSuccess = is_object($res) && isset($res->success) && $res->success === true;

            if (!$isSuccess && $transactionId) {
                app(BillingCheckoutIntentService::class)->failIntentByExternalReference(
                    (string) $processor,
                    (string) $transactionId,
                    is_object($res) && isset($res->message)
                        ? (string) $res->message
                        : __('Payment Unsuccessful!')
                );
            }

            return redirect('/billing')->with('status', [
                'type' => $isSuccess ? 'success' : 'error',
                'message' => $isSuccess
                    ? (is_object($res) && isset($res->message) ? $res->message : __('Payment Successful!'))
                    : __('Payment Unsuccessful!'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Payment callback failed', [
                'processor' => $processor,
                'error' => $e->getMessage(),
            ]);

            if ($transactionId) {
                app(BillingCheckoutIntentService::class)->failIntentByExternalReference(
                    (string) $processor,
                    (string) $transactionId,
                    __('Payment processing failed. Please try again.')
                );
            }

            return redirect('/billing')->with('status', [
                'type' => 'error',
                'message' => __('Payment processing failed. Please try again.'),
            ]);
        }
    }

    public function processWebhook(Request $request, string $processor = 'moyasar')
    {
        $normalizedProcessor = SupportedPaymentProcessors::normalizeName($processor);

        if ($normalizedProcessor !== 'moyasar') {
            return response()->json([
                'status' => 'error',
                'message' => __('Unsupported payment processor webhook.'),
            ], 404);
        }

        try {
            $paymentPlatform = $this->paymentPlatformResolver->resolveService($normalizedProcessor);

            if (!method_exists($paymentPlatform, 'handleWebhook')) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Unsupported payment callback for selected processor.'),
                ], 404);
            }

            return $paymentPlatform->handleWebhook($request);
        } catch (\Throwable $e) {
            Log::error('Payment webhook failed', [
                'processor' => $processor,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => __('Payment webhook processing failed.'),
            ], 500);
        }
    }
}
