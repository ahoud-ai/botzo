<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckSubscriptionStatus;
use App\Models\PaymentGateway;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class PaymentProcessorAllowListTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_billing_payment_request_rejects_unsupported_processor(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/pay', [
                'amount' => 10,
                'method' => 'previous_card',
            ]);

        $response->assertSessionHasErrors('method');
    }

    public function test_billing_payment_request_accepts_moyasar_processor(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);
        $this->activateGateway('Moyasar', [
            'secret_key' => 'test_secret_key',
            'webhook_secret' => 'test_webhook_secret',
        ]);

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/pay', [
                'amount' => 10,
                'method' => 'Moyasar',
            ]);

        $response->assertSessionDoesntHaveErrors('method');
    }

    public function test_billing_payment_request_rejects_inactive_supported_processor(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);

        PaymentGateway::create([
            'name' => 'Moyasar',
            'is_active' => 0,
            'metadata' => json_encode([
                'secret_key' => 'inactive_secret_key',
                'webhook_secret' => 'inactive_webhook_secret',
            ]),
        ]);

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/pay', [
                'amount' => 10,
                'method' => 'Moyasar',
            ]);

        $response->assertSessionHasErrors('method');
    }

    public function test_unsupported_payment_webhook_is_rejected_safely(): void
    {
        $response = $this->postJson('/payment/previous-card/webhook', []);

        $response->assertStatus(404);
    }

    public function test_unsupported_payment_callback_route_is_not_registered(): void
    {
        $response = $this->get('/payment/previous-card');

        $response->assertStatus(404);
    }

    public function test_billing_payment_request_rejects_moyasar_when_webhook_secret_is_missing(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);
        $this->activateGateway('Moyasar', [
            'secret_key' => 'test_secret_key',
        ]);

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/pay', [
                'amount' => 10,
                'method' => 'Moyasar',
            ]);

        $response->assertSessionHasErrors('method');
    }

    public function test_moyasar_webhook_rejects_missing_webhook_secret(): void
    {
        $this->activateGateway('Moyasar', [
            'secret_key' => 'test_secret_key',
        ]);

        $response = $this->postJson('/payment/moyasar/webhook', [
            'id' => 'inv_test_123',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'status' => 'error',
        ]);
    }

    public function test_billing_payment_request_rejects_moyasar_test_mode_on_production_by_default(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);
        $this->activateGateway('Moyasar', [
            'active_mode' => 'test',
            'test' => [
                'secret_key' => 'test_secret_key',
                'webhook_secret' => 'test_webhook_secret',
            ],
        ]);

        Config::set('app.env', 'production');

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/pay', [
                'amount' => 10,
                'method' => 'Moyasar',
            ]);

        $response->assertSessionHasErrors('method');
    }

    public function test_billing_payment_request_accepts_moyasar_test_mode_on_production_when_override_is_enabled(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);
        $this->activateGateway('Moyasar', [
            'active_mode' => 'test',
            'test' => [
                'secret_key' => 'test_secret_key',
                'webhook_secret' => 'test_webhook_secret',
            ],
        ]);

        Config::set('app.env', 'production');
        Config::set('app.allow_test_payment_mode_on_production', true);

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/pay', [
                'amount' => 10,
                'method' => 'Moyasar',
            ]);

        $response->assertSessionDoesntHaveErrors('method');
    }

    private function activateGateway(string $name, array $metadata = []): PaymentGateway
    {
        return PaymentGateway::updateOrCreate(
            ['name' => $name],
            [
                'is_active' => 1,
                'metadata' => json_encode($metadata),
            ]
        );
    }
}
