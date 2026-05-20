<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayContract;
use App\Services\MoyasarService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class MoyasarPaymentCallbackFallbackTest extends TestCase
{
    use DatabaseTransactions;

    public function test_payment_callback_uses_session_reference_for_moyasar_when_query_reference_missing(): void
    {
        $fakeGateway = new FakeMoyasarGatewayForCallback();
        app()->instance(MoyasarService::class, $fakeGateway);

        $response = $this->withSession([
            'moyasar_last_reference' => 'inv_test_123',
        ])->get('/payment/moyasar');

        $response->assertRedirect('/billing');
        $response->assertSessionHas('status', function ($status): bool {
            return is_array($status)
                && ($status['type'] ?? null) === 'success';
        });
        $response->assertSessionMissing('moyasar_last_reference');

        $this->assertSame('inv_test_123', $fakeGateway->receivedReference);
    }

    public function test_payment_callback_fails_safely_when_reference_is_missing(): void
    {
        $fakeGateway = new FakeMoyasarGatewayForCallback();
        app()->instance(MoyasarService::class, $fakeGateway);

        $response = $this->get('/payment/moyasar');

        $response->assertRedirect('/billing');
        $response->assertSessionHas('status', function ($status): bool {
            return is_array($status)
                && ($status['type'] ?? null) === 'error'
                && ($status['message'] ?? null) === __('Missing transaction reference.');
        });
    }

    public function test_payment_callback_does_not_depend_on_previous_coupon_session_state(): void
    {
        $fakeGateway = new FakeMoyasarGatewayForCallback();
        app()->instance(MoyasarService::class, $fakeGateway);

        $response = $this->withSession([
            'applied_coupon' => 'SAVE10',
            'moyasar_last_reference' => 'inv_test_456',
        ])->get('/payment/moyasar');

        $response->assertRedirect('/billing');
        $response->assertSessionHas('applied_coupon', 'SAVE10');
        $this->assertTrue($fakeGateway->usedCompletePayment);
        $this->assertSame('inv_test_456', $fakeGateway->receivedReference);
    }
}

final class FakeMoyasarGatewayForCallback implements PaymentGatewayContract
{
    public ?string $receivedReference = null;
    public bool $usedCompletePayment = false;

    public function handlePayment($amount, $planId = null)
    {
        return (object) ['success' => true, 'data' => 'https://example.test/checkout'];
    }

    public function handleWebhook(Request $request)
    {
        return response()->json(['status' => 'ok']);
    }

    public function completePayment($referenceId)
    {
        $this->usedCompletePayment = true;
        $this->receivedReference = is_string($referenceId) ? $referenceId : null;

        return (object) ['success' => true, 'message' => 'completed'];
    }

    public function updateSubscription($referenceId)
    {
        return $this->completePayment($referenceId);
    }
}
