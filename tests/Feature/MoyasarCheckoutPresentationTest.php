<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\PaymentGateway;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use App\Services\BillingCheckoutIntentService;
use App\Services\MoyasarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MoyasarCheckoutPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_moyasar_subscription_checkout_uses_plan_specific_copy_and_clean_metadata(): void
    {
        $owner = $this->createUser();
        $organization = $this->createOrganization($owner, 'Acme Workspace');
        $this->attachOwner($owner, $organization);
        $plan = $this->createPlan('Growth', 'باقة النمو', 399.00, 'monthly', 2);

        PaymentGateway::updateOrCreate([
            'name' => 'Moyasar',
        ], [
            'name' => 'Moyasar',
            'is_active' => 1,
            'metadata' => json_encode([
                'active_mode' => 'test',
                'mode' => 'test',
                'test' => [
                    'secret_key' => 'test_secret_key',
                    'webhook_secret' => 'test_webhook_secret',
                ],
                'secret_key' => 'test_secret_key',
                'webhook_secret' => 'test_webhook_secret',
            ]),
        ]);

        $intent = app(BillingCheckoutIntentService::class)->createSubscriptionPurchaseIntent(
            $organization->id,
            $plan->id,
            $owner->id,
            'moyasar'
        );

        $capturedPayload = null;

        Http::fake(function ($request) use (&$capturedPayload) {
            $capturedPayload = json_decode($request->body(), true);

            return Http::response([
                'id' => 'inv_test_123',
                'url' => 'https://checkout.moyasar.com/invoices/inv_test_123',
            ], 200);
        });

        $this->actingAs($owner, 'user');
        session(['current_organization' => $organization->id]);

        $response = app(MoyasarService::class)->handlePayment(399.00, $plan->id, $intent);

        $this->assertTrue($response->success);
        $this->assertIsArray($capturedPayload);
        $this->assertSame('Subscription to Growth (Monthly billing)', $capturedPayload['description'] ?? null);
        $this->assertSame('Subscription purchase', data_get($capturedPayload, 'metadata.transaction_type'));
        $this->assertSame('Growth', data_get($capturedPayload, 'metadata.subscription_plan'));
        $this->assertSame('Monthly', data_get($capturedPayload, 'metadata.billing_cycle'));
        $this->assertSame('Acme Workspace', data_get($capturedPayload, 'metadata.organization_name'));
        $this->assertSame($owner->full_name, data_get($capturedPayload, 'metadata.customer_name'));
        $this->assertSame($owner->email, data_get($capturedPayload, 'metadata.customer_email'));
        $this->assertSame((string) $intent->uuid, data_get($capturedPayload, 'metadata.botzo_checkout_id'));
        $this->assertNotEmpty(data_get($capturedPayload, 'metadata.botzo_reference'));
        $this->assertArrayNotHasKey('organization_id', $capturedPayload['metadata']);
        $this->assertArrayNotHasKey('plan_id', $capturedPayload['metadata']);
        $this->assertArrayNotHasKey('user_id', $capturedPayload['metadata']);
        $this->assertArrayNotHasKey('intent_uuid', $capturedPayload['metadata']);
        $this->assertArrayNotHasKey('reference', $capturedPayload['metadata']);
    }

    public function test_moyasar_completion_accepts_professional_checkout_metadata_when_invoice_metadata_is_used(): void
    {
        $owner = $this->createUser();
        $organization = $this->createOrganization($owner, 'Acme Workspace');
        $this->attachOwner($owner, $organization);
        $plan = $this->createPlan('Growth', 'باقة النمو', 399.00, 'monthly', 2);

        PaymentGateway::updateOrCreate([
            'name' => 'Moyasar',
        ], [
            'name' => 'Moyasar',
            'is_active' => 1,
            'metadata' => json_encode([
                'active_mode' => 'test',
                'mode' => 'test',
                'test' => [
                    'secret_key' => 'test_secret_key',
                    'webhook_secret' => 'test_webhook_secret',
                ],
                'secret_key' => 'test_secret_key',
                'webhook_secret' => 'test_webhook_secret',
            ]),
        ]);

        $intent = app(BillingCheckoutIntentService::class)->createSubscriptionPurchaseIntent(
            $organization->id,
            $plan->id,
            $owner->id,
            'moyasar'
        );

        Http::fake([
            'https://api.moyasar.com/v1/invoices/pay_test_presentational_meta' => Http::response([], 404),
            'https://api.moyasar.com/v1/payments/pay_test_presentational_meta' => Http::response([
                'id' => 'pay_test_presentational_meta',
                'status' => 'paid',
                'amount' => 39900,
                'invoice_id' => 'inv_test_123',
            ], 200),
            'https://api.moyasar.com/v1/invoices/inv_test_123' => Http::response([
                'id' => 'inv_test_123',
                'status' => 'paid',
                'amount' => 39900,
                'metadata' => [
                    'transaction_type' => 'Subscription purchase',
                    'subscription_plan' => 'Growth',
                    'billing_cycle' => 'Monthly',
                    'organization_name' => 'Acme Workspace',
                    'customer_name' => $owner->full_name,
                    'customer_email' => $owner->email,
                    'botzo_reference' => (string) Str::uuid(),
                    'botzo_checkout_id' => $intent->uuid,
                ],
                'payments' => [
                    [
                        'id' => 'pay_test_presentational_meta',
                        'status' => 'paid',
                        'amount' => 39900,
                    ],
                ],
            ], 200),
        ]);

        $result = app(MoyasarService::class)->completePayment('pay_test_presentational_meta');

        $this->assertTrue($result->success);
        $this->assertDatabaseHas('billing_payments', [
            'organization_id' => $organization->id,
            'processor' => 'moyasar',
            'details' => 'pay_test_presentational_meta',
            'amount' => 399.00,
        ]);

        $intent->refresh();
        $this->assertSame('completed', $intent->status);
        $this->assertNotNull($intent->completed_payment_id);
        $this->assertNotNull($intent->completed_invoice_id);

        $subscription = Subscription::query()->where('organization_id', $organization->id)->first();
        $this->assertNotNull($subscription);
        $this->assertSame($plan->id, (int) $subscription->plan_id);
        $this->assertSame('active', $subscription->status);
    }

    private function createUser(): User
    {
        return User::create([
            'first_name' => 'Moyasar',
            'last_name' => 'Owner',
            'email' => 'moyasar+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
            'status' => 1,
            'language' => 'en',
        ]);
    }

    private function createOrganization(User $creator, string $name): Organization
    {
        return Organization::create([
            'name' => $name,
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => $creator->id,
            'organization_type' => 'main',
            'parent_organization_id' => null,
            'metadata' => json_encode([
                'addons' => [],
            ]),
        ]);
    }

    private function createPlan(string $nameEn, string $nameAr, float $price, string $period, int $tierRank): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => $nameEn,
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'price' => $price,
            'period' => $period,
            'metadata' => json_encode([
                'tier_rank' => $tierRank,
                'campaign_limit' => 10,
                'message_limit' => 100,
                'contacts_limit' => 100,
                'canned_replies_limit' => 10,
                'team_limit' => 5,
                'branches_limit' => 2,
                'addons' => [],
            ]),
            'status' => 'active',
        ]);
    }

    private function attachOwner(User $user, Organization $organization): void
    {
        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'organization_role_id' => $this->ownerRole()->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);
    }

    private function ownerRole(): OrganizationRole
    {
        return OrganizationRole::firstOrCreate(
            [
                'organization_id' => null,
                'name' => 'Owner',
            ],
            [
                'description' => 'Owner role for Moyasar checkout presentation tests',
                'permissions' => ['*'],
            ]
        );
    }
}
