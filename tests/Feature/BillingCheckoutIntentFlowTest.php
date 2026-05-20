<?php

namespace Tests\Feature;

use App\Models\BillingCheckoutIntent;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\BillingTransaction;
use App\Models\Coupon;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use App\Services\BillingCheckoutIntentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingCheckoutIntentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_completion_rejects_payment_currency_mismatch(): void
    {
        Setting::updateOrCreate(['key' => 'currency'], ['value' => 'SAR']);

        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $this->attachOwner($owner, $organization);

        $plan = $this->createPlan(100.00, 'monthly', 2);

        $service = app(BillingCheckoutIntentService::class);
        $intent = $service->createSubscriptionPurchaseIntent($organization->id, $plan->id, $owner->id, 'moyasar');

        $completion = $service->completeIntentByUuid(
            $intent->uuid,
            'moyasar',
            'pay_wrong_currency',
            100.00,
            ['currency' => 'USD'],
            'Visa',
            'USD'
        );

        $this->assertFalse($completion->success);
        $this->assertDatabaseCount('billing_invoices', 0);
        $this->assertDatabaseCount('billing_payments', 0);
        $this->assertDatabaseMissing('subscriptions', [
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
        ]);

        $intent->refresh();
        $this->assertSame('failed', $intent->status);
    }

    public function test_completion_rejects_payment_processor_mismatch(): void
    {
        Setting::updateOrCreate(['key' => 'currency'], ['value' => 'SAR']);

        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $this->attachOwner($owner, $organization);

        $plan = $this->createPlan(100.00, 'monthly', 2);

        $service = app(BillingCheckoutIntentService::class);
        $intent = $service->createSubscriptionPurchaseIntent($organization->id, $plan->id, $owner->id, 'moyasar');

        $completion = $service->completeIntentByUuid(
            $intent->uuid,
            'previous_card',
            'pay_wrong_processor',
            100.00,
            ['currency' => 'SAR'],
            'Card',
            'SAR'
        );

        $this->assertFalse($completion->success);
        $this->assertDatabaseCount('billing_invoices', 0);
        $this->assertDatabaseCount('billing_payments', 0);
        $this->assertDatabaseMissing('subscriptions', [
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
        ]);

        $intent->refresh();
        $this->assertSame('failed', $intent->status);
    }

    public function test_last_coupon_can_only_be_redeemed_by_one_checkout_intent(): void
    {
        Setting::updateOrCreate(['key' => 'currency'], ['value' => 'SAR']);

        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $this->attachOwner($owner, $organization);

        $plan = $this->createPlan(100.00, 'monthly', 2);

        $coupon = Coupon::create([
            'name' => 'LAST10',
            'code' => 'LAST10',
            'percentage' => 10,
            'quantity' => 1,
            'quantity_redeemed' => 0,
            'status' => 'active',
        ]);

        $service = app(BillingCheckoutIntentService::class);
        $firstIntent = $service->createSubscriptionPurchaseIntent($organization->id, $plan->id, $owner->id, 'moyasar', 'LAST10');
        $secondIntent = $service->createSubscriptionPurchaseIntent($organization->id, $plan->id, $owner->id, 'moyasar', 'LAST10');

        $firstCompletion = $service->completeIntentByUuid(
            $firstIntent->uuid,
            'moyasar',
            'pay_coupon_first',
            90.00,
            ['currency' => 'SAR'],
            'Visa',
            'SAR'
        );

        $secondCompletion = $service->completeIntentByUuid(
            $secondIntent->uuid,
            'moyasar',
            'pay_coupon_second',
            90.00,
            ['currency' => 'SAR'],
            'Visa',
            'SAR'
        );

        $this->assertTrue($firstCompletion->success);
        $this->assertFalse($secondCompletion->success);
        $this->assertSame(__('The coupon has expired!'), $secondCompletion->message);
        $this->assertDatabaseCount('billing_invoices', 1);
        $this->assertDatabaseCount('billing_payments', 1);

        $coupon->refresh();
        $this->assertSame(1, (int) $coupon->quantity_redeemed);

        $secondIntent->refresh();
        $this->assertSame('failed', $secondIntent->status);
    }

    public function test_subscription_purchase_intent_persists_coupon_to_invoice_and_redeems_once(): void
    {
        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $this->attachOwner($owner, $organization);

        $plan = $this->createPlan(100.00, 'monthly', 2);

        $coupon = Coupon::create([
            'name' => 'SAVE10',
            'code' => 'SAVE10',
            'percentage' => 10,
            'quantity' => 10,
            'quantity_redeemed' => 0,
            'status' => 'active',
        ]);

        $service = app(BillingCheckoutIntentService::class);
        $intent = $service->createSubscriptionPurchaseIntent($organization->id, $plan->id, $owner->id, 'moyasar', 'SAVE10');

        $this->assertSame('SAVE10', $intent->coupon_code);
        $this->assertSame(10.00, (float) $intent->coupon_amount);
        $this->assertSame(90.00, (float) $intent->amount_due);

        $firstCompletion = $service->completeIntentByUuid(
            $intent->uuid,
            'moyasar',
            'pay_test_1',
            90.00,
            ['source' => ['type' => 'card']],
            'Visa'
        );

        $this->assertTrue($firstCompletion->success);
        $this->assertDatabaseCount('billing_checkout_intents', 1);
        $this->assertDatabaseCount('billing_invoices', 1);
        $this->assertDatabaseCount('billing_payments', 1);
        $this->assertDatabaseHas('billing_invoices', [
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'coupon_id' => $coupon->id,
            'coupon_amount' => 10.00,
            'total' => 90.00,
        ]);
        $this->assertDatabaseHas('billing_payments', [
            'organization_id' => $organization->id,
            'processor' => 'moyasar',
            'details' => 'pay_test_1',
            'amount' => 90.00,
        ]);
        $this->assertDatabaseHas('billing_transactions', [
            'organization_id' => $organization->id,
            'entity_type' => 'invoice',
            'amount' => -90.00,
        ]);
        $this->assertDatabaseHas('billing_transactions', [
            'organization_id' => $organization->id,
            'entity_type' => 'payment',
            'amount' => 90.00,
        ]);

        $subscription = Subscription::query()->where('organization_id', $organization->id)->first();
        $this->assertNotNull($subscription);
        $this->assertSame($plan->id, (int) $subscription->plan_id);
        $this->assertSame('active', $subscription->status);

        $coupon->refresh();
        $this->assertSame(1, (int) $coupon->quantity_redeemed);

        $secondCompletion = $service->completeIntentByUuid(
            $intent->uuid,
            'moyasar',
            'pay_test_1',
            90.00,
            ['source' => ['type' => 'card']],
            'Visa'
        );

        $this->assertTrue($secondCompletion->success);
        $this->assertDatabaseCount('billing_invoices', 1);
        $this->assertDatabaseCount('billing_payments', 1);
        $this->assertDatabaseCount('billing_transactions', 2);

        $coupon->refresh();
        $this->assertSame(1, (int) $coupon->quantity_redeemed);
    }

    public function test_zero_amount_subscription_purchase_completes_locally_without_gateway(): void
    {
        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $this->attachOwner($owner, $organization);

        $plan = $this->createPlan(100.00, 'monthly', 2);

        BillingTransaction::create([
            'organization_id' => $organization->id,
            'entity_type' => 'credit',
            'entity_id' => 1,
            'description' => 'Starting credit',
            'amount' => 100.00,
            'created_by' => $owner->id,
        ]);

        $service = app(BillingCheckoutIntentService::class);
        $intent = $service->createSubscriptionPurchaseIntent($organization->id, $plan->id, $owner->id);

        $this->assertSame(0.00, (float) $intent->amount_due);

        $result = $service->completeWithoutGateway($intent);

        $this->assertTrue($result->success);
        $this->assertDatabaseCount('billing_invoices', 1);
        $this->assertDatabaseCount('billing_payments', 0);
        $this->assertDatabaseHas('billing_transactions', [
            'organization_id' => $organization->id,
            'entity_type' => 'invoice',
            'amount' => -100.00,
        ]);

        $invoice = BillingInvoice::query()->first();
        $this->assertSame(100.00, (float) $invoice->total);

        $subscription = Subscription::query()->where('organization_id', $organization->id)->first();
        $this->assertNotNull($subscription);
        $this->assertSame($plan->id, (int) $subscription->plan_id);
    }

    public function test_topup_intent_completion_is_idempotent_and_creates_single_payment_record(): void
    {
        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $this->attachOwner($owner, $organization);

        $service = app(BillingCheckoutIntentService::class);
        $intent = $service->createTopUpIntent($organization->id, 50.00, $owner->id, 'moyasar');

        $firstCompletion = $service->completeIntentByUuid(
            $intent->uuid,
            'moyasar',
            'moyasar_ref_test_1',
            50.00,
            ['payment_type' => 'card'],
            'Card'
        );

        $this->assertTrue($firstCompletion->success);
        $this->assertDatabaseCount('billing_payments', 1);
        $this->assertDatabaseCount('billing_transactions', 1);
        $this->assertDatabaseCount('billing_invoices', 0);

        $secondCompletion = $service->completeIntentByUuid(
            $intent->uuid,
            'moyasar',
            'moyasar_ref_test_1',
            50.00,
            ['payment_type' => 'card'],
            'Card'
        );

        $this->assertTrue($secondCompletion->success);
        $this->assertDatabaseCount('billing_payments', 1);
        $this->assertDatabaseCount('billing_transactions', 1);

        $payment = BillingPayment::query()->first();
        $this->assertSame(50.00, (float) $payment->amount);

        $savedIntent = BillingCheckoutIntent::query()->first();
        $this->assertSame('completed', $savedIntent->status);
        $this->assertSame($payment->id, $savedIntent->completed_payment_id);
    }

    private function createUser(): User
    {
        return User::create([
            'first_name' => 'Checkout',
            'last_name' => 'Owner',
            'email' => 'checkout+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
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
                'description' => 'Owner role for checkout intent tests',
                'permissions' => ['*'],
            ]
        );
    }
}
