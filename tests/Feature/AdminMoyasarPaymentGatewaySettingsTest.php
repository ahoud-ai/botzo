<?php

namespace Tests\Feature;

use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminMoyasarPaymentGatewaySettingsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_view_moyasar_gateway_config_case_insensitively(): void
    {
        $admin = $this->createAdminUser();
        PaymentGateway::firstOrCreate(
            ['name' => 'Moyasar'],
            ['metadata' => null, 'is_active' => 0]
        );

        $response = $this->actingAs($admin, 'admin')
            ->getJson('/admin/payment-gateways/moyasar');

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'name' => 'Moyasar',
            ],
        ]);
    }

    public function test_admin_can_view_moyasar_gateway_settings_page(): void
    {
        $admin = $this->createAdminUser();
        PaymentGateway::updateOrCreate(
            ['name' => 'Moyasar'],
            [
                'metadata' => json_encode([
                    'active_mode' => 'live',
                    'mode' => 'live',
                    'test' => [
                        'publishable_key' => 'pk_test_123',
                        'secret_key' => 'sk_test_123',
                        'webhook_secret' => 'wh_test_123',
                    ],
                    'live' => [
                        'publishable_key' => 'pk_live_123',
                        'secret_key' => 'sk_live_123',
                        'webhook_secret' => 'wh_live_123',
                    ],
                ]),
                'is_active' => 1,
            ]
        );

        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/payment-gateways/moyasar');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Setting/PaymentGatewayMoyasar')
            ->where('gateway.name', 'Moyasar')
            ->where('gateway.active_mode', 'live')
            ->where('gateway.is_active', 1)
            ->where('gateway.test.publishable_key', 'pk_test_123')
            ->where('gateway.live.publishable_key', 'pk_live_123')
        );
    }

    public function test_admin_can_update_moyasar_gateway_settings_with_environment_specific_credentials(): void
    {
        $admin = $this->createAdminUser();
        PaymentGateway::firstOrCreate(
            ['name' => 'Moyasar'],
            ['metadata' => null, 'is_active' => 0]
        );

        $response = $this->actingAs($admin, 'admin')
            ->put('/admin/payment-gateways/moyasar', [
                'active_mode' => 'live',
                'test' => [
                    'publishable_key' => 'pk_test_123',
                    'secret_key' => 'sk_test_123',
                    'webhook_secret' => 'whsec_test_123',
                ],
                'live' => [
                    'publishable_key' => 'pk_live_123',
                    'secret_key' => 'sk_live_123',
                    'webhook_secret' => 'whsec_live_123',
                ],
                'status' => '1',
            ]);

        $response->assertStatus(302);
        $response->assertRedirect('/admin/payment-gateways/moyasar');
        $response->assertSessionHas('status', function ($status): bool {
            return is_array($status)
                && ($status['type'] ?? null) === 'success';
        });

        $gateway = PaymentGateway::where('name', 'Moyasar')->first();
        $this->assertNotNull($gateway);
        $this->assertSame(1, (int) $gateway->is_active);

        $metadata = json_decode((string) $gateway->metadata, true);
        $this->assertIsArray($metadata);
        $this->assertSame('live', $metadata['active_mode'] ?? null);
        $this->assertSame('live', $metadata['mode'] ?? null);
        $this->assertSame('pk_test_123', $metadata['test']['publishable_key'] ?? null);
        $this->assertSame('sk_test_123', $metadata['test']['secret_key'] ?? null);
        $this->assertSame('whsec_test_123', $metadata['test']['webhook_secret'] ?? null);
        $this->assertSame('pk_live_123', $metadata['live']['publishable_key'] ?? null);
        $this->assertSame('sk_live_123', $metadata['live']['secret_key'] ?? null);
        $this->assertSame('whsec_live_123', $metadata['live']['webhook_secret'] ?? null);
        $this->assertSame('pk_live_123', $metadata['publishable_key'] ?? null);
        $this->assertSame('sk_live_123', $metadata['secret_key'] ?? null);
        $this->assertSame('whsec_live_123', $metadata['webhook_secret'] ?? null);
    }

    public function test_previous_flat_moyasar_payload_is_still_supported(): void
    {
        $admin = $this->createAdminUser();
        PaymentGateway::firstOrCreate(
            ['name' => 'Moyasar'],
            ['metadata' => null, 'is_active' => 0]
        );

        $response = $this->actingAs($admin, 'admin')
            ->put('/admin/payment-gateways/moyasar', [
                'publishable_key' => 'pk_test_previous',
                'secret_key' => 'sk_test_previous',
                'webhook_secret' => 'whsec_test_previous',
                'mode' => 'test',
                'status' => '1',
            ]);

        $response->assertStatus(302);
        $response->assertRedirect('/admin/payment-gateways/moyasar');

        $gateway = PaymentGateway::where('name', 'Moyasar')->first();
        $this->assertNotNull($gateway);

        $metadata = json_decode((string) $gateway->metadata, true);
        $this->assertSame('test', $metadata['active_mode'] ?? null);
        $this->assertSame('test', $metadata['mode'] ?? null);
        $this->assertSame('pk_test_previous', $metadata['test']['publishable_key'] ?? null);
        $this->assertSame('sk_test_previous', $metadata['test']['secret_key'] ?? null);
        $this->assertSame('whsec_test_previous', $metadata['test']['webhook_secret'] ?? null);
        $this->assertSame('pk_test_previous', $metadata['publishable_key'] ?? null);
        $this->assertSame('sk_test_previous', $metadata['secret_key'] ?? null);
        $this->assertSame('whsec_test_previous', $metadata['webhook_secret'] ?? null);
    }

    public function test_partial_structured_update_preserves_the_other_environment_credentials(): void
    {
        $admin = $this->createAdminUser();
        PaymentGateway::updateOrCreate(
            ['name' => 'Moyasar'],
            [
                'metadata' => json_encode([
                    'active_mode' => 'test',
                    'mode' => 'test',
                    'test' => [
                        'publishable_key' => 'pk_test_old',
                        'secret_key' => 'sk_test_old',
                        'webhook_secret' => 'wh_test_old',
                    ],
                    'live' => [
                        'publishable_key' => 'pk_live_old',
                        'secret_key' => 'sk_live_old',
                        'webhook_secret' => 'wh_live_old',
                    ],
                    'publishable_key' => 'pk_test_old',
                    'secret_key' => 'sk_test_old',
                    'webhook_secret' => 'wh_test_old',
                ]),
                'is_active' => 1,
            ]
        );

        $response = $this->actingAs($admin, 'admin')
            ->put('/admin/payment-gateways/moyasar', [
                'active_mode' => 'live',
                'live' => [
                    'publishable_key' => 'pk_live_new',
                    'secret_key' => 'sk_live_new',
                    'webhook_secret' => 'wh_live_new',
                ],
                'status' => '1',
            ]);

        $response->assertRedirect('/admin/payment-gateways/moyasar');

        $gateway = PaymentGateway::where('name', 'Moyasar')->first();
        $this->assertNotNull($gateway);

        $metadata = json_decode((string) $gateway->metadata, true);
        $this->assertSame('pk_test_old', $metadata['test']['publishable_key'] ?? null);
        $this->assertSame('sk_test_old', $metadata['test']['secret_key'] ?? null);
        $this->assertSame('pk_live_new', $metadata['live']['publishable_key'] ?? null);
        $this->assertSame('sk_live_new', $metadata['live']['secret_key'] ?? null);
        $this->assertSame('pk_live_new', $metadata['publishable_key'] ?? null);
        $this->assertSame('sk_live_new', $metadata['secret_key'] ?? null);
    }

    public function test_non_moyasar_gateway_show_page_redirects_back_to_the_table(): void
    {
        $admin = $this->createAdminUser();
        PaymentGateway::firstOrCreate(
            ['name' => 'Previous Gateway'],
            ['metadata' => json_encode([]), 'is_active' => 0]
        );

        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/payment-gateways/previous-gateway');

        $response->assertRedirect('/admin/payment-gateways');
        $response->assertSessionHas('status', function ($status): bool {
            return is_array($status)
                && ($status['type'] ?? null) === 'error'
                && ($status['message'] ?? null) === __('The selected payment processor is not supported.');
        });
    }

    private function createAdminUser(): User
    {
        return User::create([
            'first_name' => 'Admin',
            'last_name' => 'Tester',
            'email' => 'admin+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'email_verified_at' => now(),
        ]);
    }
}
