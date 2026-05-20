<?php

namespace Tests\Feature;

use App\Models\BillingCheckoutIntent;
use App\Models\BillingPayment;
use App\Models\BillingTransaction;
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

class MoyasarInvoiceMetadataFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_moyasar_payment_without_payment_metadata_falls_back_to_invoice_metadata(): void
    {
        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $this->attachOwner($owner, $organization);
        $plan = $this->createPlan(399.00, 'monthly', 2);

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
            'moyasar',
            null
        );

        Http::fake([
            'https://api.moyasar.com/v1/invoices/pay_test_missing_meta' => Http::response([], 404),
            'https://api.moyasar.com/v1/payments/pay_test_missing_meta' => Http::response([
                'id' => 'pay_test_missing_meta',
                'status' => 'paid',
                'amount' => 39900,
                'invoice_id' => 'inv_test_123',
            ], 200),
            'https://api.moyasar.com/v1/invoices/inv_test_123' => Http::response([
                'id' => 'inv_test_123',
                'status' => 'paid',
                'amount' => 39900,
                'metadata' => [
                    'organization_id' => (string) $organization->id,
                    'plan_id' => (string) $plan->id,
                    'user_id' => (string) $owner->id,
                    'reference' => (string) Str::uuid(),
                    'intent_uuid' => $intent->uuid,
                ],
                'payments' => [
                    [
                        'id' => 'pay_test_missing_meta',
                        'status' => 'paid',
                        'amount' => 39900,
                    ],
                ],
            ], 200),
        ]);

        $result = app(MoyasarService::class)->completePayment('pay_test_missing_meta');

        $this->assertTrue($result->success);
        $this->assertDatabaseHas('billing_payments', [
            'organization_id' => $organization->id,
            'processor' => 'moyasar',
            'details' => 'pay_test_missing_meta',
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

    public function test_previous_moyasar_completion_does_not_duplicate_payment_on_replay(): void
    {
        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $this->attachOwner($owner, $organization);

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

        Http::fake([
            'https://api.moyasar.com/v1/invoices/pay_test_previous_replay' => Http::response([], 404),
            'https://api.moyasar.com/v1/payments/pay_test_previous_replay' => Http::response([
                'id' => 'pay_test_previous_replay',
                'status' => 'paid',
                'amount' => 25900,
                'metadata' => [
                    'organization_id' => (string) $organization->id,
                    'user_id' => (string) $owner->id,
                ],
            ], 200),
        ]);

        $first = app(MoyasarService::class)->completePayment('pay_test_previous_replay');
        $second = app(MoyasarService::class)->completePayment('pay_test_previous_replay');

        $this->assertTrue($first->success);
        $this->assertTrue($second->success);
        $this->assertSame(1, BillingPayment::query()
            ->where('processor', 'moyasar')
            ->where('details', 'pay_test_previous_replay')
            ->count());

        $payment = BillingPayment::query()
            ->where('processor', 'moyasar')
            ->where('details', 'pay_test_previous_replay')
            ->firstOrFail();

        $this->assertSame(1, BillingTransaction::query()
            ->where('entity_type', 'payment')
            ->where('entity_id', $payment->id)
            ->count());
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

    private function createOrganization(User $creator): Organization
    {
        return Organization::create([
            'name' => 'Organization '.Str::random(4),
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => $creator->id,
            'organization_type' => 'main',
            'parent_organization_id' => null,
            'metadata' => json_encode([
                'addons' => [],
            ]),
        ]);
    }

    private function createPlan(float $price, string $period, int $tierRank): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Plan '.Str::random(4),
            'name_ar' => 'خطة '.Str::random(4),
            'name_en' => 'Plan '.Str::random(4),
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
                'description' => 'Owner role for Moyasar fallback tests',
                'permissions' => ['*'],
            ]
        );
    }
}
