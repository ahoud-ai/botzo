<?php

namespace Tests\Feature;

use App\Models\BillingCheckoutIntent;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\PaymentGateway;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use App\Services\MoyasarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SystemSignupBillingAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_billing_audit_command_reports_onboarding_and_moyasar_incident_categories(): void
    {
        $owner = $this->createUser();
        $plan = $this->createPlan();
        $organization = $this->createOrganization($owner);
        $this->attachOwner($owner, $organization);

        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subMinute(),
            'valid_until' => now()->addMonth(),
        ]);

        BillingCheckoutIntent::create([
            'organization_id' => $organization->id,
            'billing_organization_id' => $organization->id,
            'user_id' => $owner->id,
            'type' => 'subscription_purchase',
            'status' => 'processing',
            'target_plan_id' => $plan->id,
            'processor' => 'moyasar',
            'currency' => 'SAR',
            'amount_due' => 399,
            'net_total' => 399,
            'external_reference' => 'inv_stuck_001',
            'snapshot_json' => ['selected_plan_id' => $plan->id],
            'expires_at' => now()->addHour(),
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        PaymentGateway::updateOrCreate(
            ['name' => 'Moyasar'],
            [
                'is_active' => 1,
                'metadata' => json_encode([
                    'active_mode' => 'test',
                    'secret_key' => 'test_secret',
                ]),
            ]
        );

        $exitCode = Artisan::call('system:signup-billing-audit', [
            '--format' => 'json',
        ]);

        $this->assertSame(0, $exitCode);
        $payload = json_decode((string) Artisan::output(), true);

        $this->assertSame('review_required', $payload['status'] ?? null);
        $this->assertSame(1, data_get($payload, 'before.categories.auto_provisioned_unpaid_no_usage.count'));
        $this->assertSame(0, data_get($payload, 'before.categories.auto_provisioned_unpaid_with_usage.count'));
        $this->assertSame(1, data_get($payload, 'before.categories.stuck_moyasar_processing_intents.count'));
        $this->assertSame(1, data_get($payload, 'before.categories.gateway_configuration_warnings.count'));
        $this->assertSame('google', data_get($payload, 'before.categories.auto_provisioned_unpaid_no_usage.samples.0.source_hint'));
    }

    public function test_signup_billing_audit_command_applies_safe_fixes_for_auto_subscriptions_and_stuck_intents(): void
    {
        $owner = $this->createUser();
        $plan = $this->createPlan();
        $organization = $this->createOrganization($owner);
        $this->attachOwner($owner, $organization);

        $subscription = Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subMinute(),
            'valid_until' => now()->addMonth(),
        ]);

        $intent = BillingCheckoutIntent::create([
            'organization_id' => $organization->id,
            'billing_organization_id' => $organization->id,
            'user_id' => $owner->id,
            'type' => 'subscription_purchase',
            'status' => 'processing',
            'target_plan_id' => $plan->id,
            'processor' => 'moyasar',
            'currency' => 'SAR',
            'amount_due' => 399,
            'net_total' => 399,
            'external_reference' => 'inv_stuck_fix_001',
            'snapshot_json' => ['selected_plan_id' => $plan->id],
            'expires_at' => now()->addHour(),
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        app()->instance(MoyasarService::class, new class extends MoyasarService
        {
            public function __construct()
            {
            }

            public function completePayment($referenceId)
            {
                $intent = BillingCheckoutIntent::query()
                    ->where('external_reference', $referenceId)
                    ->first();

                if ($intent) {
                    $intent->forceFill([
                        'status' => 'completed',
                        'completed_at' => now(),
                    ])->save();
                }

                return (object) ['success' => true];
            }
        });

        $exitCode = Artisan::call('system:signup-billing-audit', [
            '--format' => 'json',
            '--apply-safe-fixes' => true,
            '--strict' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseMissing('subscriptions', ['id' => $subscription->id]);

        $intent->refresh();
        $this->assertSame('completed', $intent->status);

        $payload = json_decode((string) Artisan::output(), true);
        $this->assertSame(1, data_get($payload, 'applied_fixes.removed_auto_provisioned_subscriptions.applied'));
        $this->assertSame(1, data_get($payload, 'applied_fixes.reconciled_stuck_moyasar_intents.applied'));
        $this->assertSame('clean', $payload['status'] ?? null);
    }

    private function createUser(): User
    {
        $user = new User();
        $user->first_name = 'Audit';
        $user->last_name = 'Owner';
        $user->email = 'audit+'.Str::random(6).'@example.com';
        $user->password = Hash::make('password123');
        $user->role = 'user';
        $user->status = 1;
        $user->google_id = 'google-'.Str::random(10);
        $user->language = 'en';
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function createOrganization(User $owner): Organization
    {
        return Organization::create([
            'name' => 'Audit Org '.Str::random(4),
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => $owner->id,
            'organization_type' => 'main',
            'parent_organization_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'metadata' => json_encode(['addons' => []]),
        ]);
    }

    private function createPlan(): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Audit Plan '.Str::random(4),
            'name_ar' => 'خطة تدقيق '.Str::random(4),
            'name_en' => 'Audit Plan '.Str::random(4),
            'price' => 399.00,
            'period' => 'monthly',
            'status' => 'active',
            'metadata' => json_encode([
                'tier_rank' => 1,
                'addons' => [],
            ]),
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
                'description' => 'Owner role for onboarding audit tests',
                'permissions' => ['*'],
            ]
        );
    }
}
