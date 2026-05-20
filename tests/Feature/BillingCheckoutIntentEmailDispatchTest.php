<?php

namespace Tests\Feature;

use App\Mail\CustomEmail;
use App\Models\BillingInvoice;
use App\Models\BillingTransaction;
use App\Models\EmailTemplate;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use App\Services\BillingCheckoutIntentService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingCheckoutIntentEmailDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::updateOrCreate(['key' => 'smtp_email_active'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'company_name'], ['value' => 'Botzo']);

        $this->storeTemplate('Subscription Plan Purchase', 'Plan {{plan}} activated', '<p>Hello {{FirstName}}, {{plan}}</p>');
        $this->storeTemplate('Subscription Renewal', 'Renewed {{plan}}', '<p>Hello {{FirstName}}, renewed</p>');
        $this->storeTemplate('Payment Success', 'Payment received for {{FirstName}}', '<p>Paid</p>');
        $this->storeTemplate('Payment Failed', 'Payment failed for {{FirstName}}', '<p>Failed</p>');
    }

    public function test_paid_subscription_purchase_completion_queues_subscription_purchase_email(): void
    {
        Mail::fake();

        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $this->attachOwner($owner, $organization);
        $plan = $this->createPlan(100.00, 'monthly', 2, 'Growth');

        $service = app(BillingCheckoutIntentService::class);
        $intent = $service->createSubscriptionPurchaseIntent($organization->id, $plan->id, $owner->id, 'moyasar');

        $service->completeIntentByUuid(
            $intent->uuid,
            'moyasar',
            'pay_success_1',
            100.00,
            ['source' => ['type' => 'card']],
            'Visa'
        );

        Mail::assertQueued(CustomEmail::class, function (CustomEmail $mail) {
            return $mail->subject === 'Plan Growth activated';
        });
    }

    public function test_zero_amount_subscription_purchase_completion_queues_subscription_purchase_email(): void
    {
        Mail::fake();

        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $this->attachOwner($owner, $organization);
        $plan = $this->createPlan(100.00, 'monthly', 2, 'Starter');

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
        $service->completeWithoutGateway($intent);

        Mail::assertQueued(CustomEmail::class, function (CustomEmail $mail) {
            return $mail->subject === 'Plan Starter activated';
        });
    }

    public function test_topup_completion_queues_payment_success_email(): void
    {
        Mail::fake();

        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $this->attachOwner($owner, $organization);

        $service = app(BillingCheckoutIntentService::class);
        $intent = $service->createTopUpIntent($organization->id, 50.00, $owner->id, 'moyasar');

        $service->completeIntentByUuid(
            $intent->uuid,
            'moyasar',
            'moyasar_success_1',
            50.00,
            ['payment_type' => 'card'],
            'Card'
        );

        Mail::assertQueued(CustomEmail::class, function (CustomEmail $mail) {
            return $mail->subject === 'Payment received for Checkout';
        });
    }

    public function test_failing_checkout_intent_queues_payment_failed_email_once(): void
    {
        Mail::fake();

        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $this->attachOwner($owner, $organization);

        $service = app(BillingCheckoutIntentService::class);
        $intent = $service->createTopUpIntent($organization->id, 25.00, $owner->id, 'moyasar');
        $service->markExternalReference($intent, 'invoice_fail_1');

        $service->failIntentByExternalReference('moyasar', 'invoice_fail_1', 'Gateway rejected payment');
        $service->failIntentByExternalReference('moyasar', 'invoice_fail_1', 'Gateway rejected payment');

        Mail::assertQueued(CustomEmail::class, function (CustomEmail $mail) {
            return $mail->subject === 'Payment failed for Checkout';
        });
        Mail::assertQueued(CustomEmail::class, 1);
    }

    public function test_subscription_renewal_with_credits_queues_renewal_email(): void
    {
        Mail::fake();

        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);
        $this->attachOwner($owner, $organization);
        $plan = $this->createPlan(100.00, 'monthly', 2, 'Renewal');

        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subMonths(2),
            'valid_until' => now()->subDay(),
        ]);

        BillingInvoice::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'subtotal' => 100,
            'tax' => 0,
            'tax_type' => 'exclusive',
            'total' => 100,
        ]);

        BillingTransaction::create([
            'organization_id' => $organization->id,
            'entity_type' => 'credit',
            'entity_id' => 2,
            'description' => 'Renewal credit',
            'amount' => 100.00,
            'created_by' => $owner->id,
        ]);

        SubscriptionService::activateSubscriptionIfInactiveAndExpiredWithCredits($organization->id, $owner->id);

        Mail::assertQueued(CustomEmail::class, function (CustomEmail $mail) {
            return $mail->subject === 'Renewed Renewal';
        });
    }

    private function storeTemplate(string $name, string $subject, string $body): void
    {
        EmailTemplate::updateOrCreate(
            ['name' => $name],
            [
                'subject' => $subject,
                'body' => $body,
                'updated_by' => 1,
                'updated_at' => now(),
            ]
        );
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

    private function createPlan(float $price, string $period, int $tierRank, string $name): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => $name,
            'name_ar' => $name.' AR',
            'name_en' => $name,
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
            ['organization_id' => null, 'name' => 'Owner'],
            ['description' => 'Owner', 'permissions' => ['*']]
        );
    }
}
