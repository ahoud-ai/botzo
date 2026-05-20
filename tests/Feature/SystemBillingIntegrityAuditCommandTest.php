<?php

namespace Tests\Feature;

use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SystemBillingIntegrityAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_integrity_audit_command_reports_previous_anomalies(): void
    {
        $validPlan = $this->createPlan('Valid plan');
        $deletedPlan = $this->createPlan('Deleted plan');
        $deletedPlan->delete();

        $this->createOrganization('Main missing subscription');

        $branchParent = $this->createOrganization('Parent org');
        $this->attachActiveSubscription($branchParent, $validPlan);
        $branchWithStandaloneSubscription = $this->createOrganization('Branch child', 'branch', $branchParent->id);
        $this->attachActiveSubscription($branchWithStandaloneSubscription, $validPlan);

        $this->createOrganization('Orphan branch', 'branch');

        $missingCurrentPlanOrg = $this->createOrganization('Missing current plan org');
        Subscription::create([
            'organization_id' => $missingCurrentPlanOrg->id,
            'plan_id' => 999999,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $missingScheduledPlanOrg = $this->createOrganization('Missing scheduled plan org');
        Subscription::create([
            'organization_id' => $missingScheduledPlanOrg->id,
            'plan_id' => $validPlan->id,
            'scheduled_plan_id' => $deletedPlan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
            'scheduled_plan_change_at' => now()->addWeek(),
        ]);

        $expiredActiveOrg = $this->createOrganization('Expired active org');
        Subscription::create([
            'organization_id' => $expiredActiveOrg->id,
            'plan_id' => $validPlan->id,
            'status' => 'active',
            'start_date' => now()->subMonth(),
            'valid_until' => now()->subMinute(),
        ]);

        $paymentDupOrg = $this->createOrganization('Duplicate payment org');
        $this->attachActiveSubscription($paymentDupOrg, $validPlan);
        BillingPayment::create([
            'organization_id' => $paymentDupOrg->id,
            'processor' => 'moyasar',
            'details' => 'dup-ref',
            'amount' => 10,
        ]);
        BillingPayment::create([
            'organization_id' => $paymentDupOrg->id,
            'processor' => 'moyasar',
            'details' => 'dup-ref',
            'amount' => 10,
        ]);

        $foreignInvoiceOrg = $this->createOrganization('Foreign invoice org');
        $otherOrg = $this->createOrganization('Other org');
        $this->attachActiveSubscription($foreignInvoiceOrg, $validPlan);
        $this->attachActiveSubscription($otherOrg, $validPlan);
        $invoice = BillingInvoice::create([
            'organization_id' => $otherOrg->id,
            'plan_id' => $validPlan->id,
            'subtotal' => 25,
            'coupon_amount' => 0,
            'tax' => 0,
            'tax_type' => 'exclusive',
            'total' => 25,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        BillingPayment::create([
            'organization_id' => $foreignInvoiceOrg->id,
            'invoice_id' => $invoice->id,
            'processor' => 'moyasar',
            'payment_method' => 'card',
            'details' => 'foreign-ref',
            'amount' => 25,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $candidateOrg = $this->createOrganization('Candidate link org');
        $this->attachActiveSubscription($candidateOrg, $validPlan);
        $candidateInvoice = BillingInvoice::create([
            'organization_id' => $candidateOrg->id,
            'plan_id' => $validPlan->id,
            'subtotal' => 99,
            'coupon_amount' => 0,
            'tax' => 0,
            'tax_type' => 'exclusive',
            'total' => 99,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        BillingPayment::create([
            'organization_id' => $candidateOrg->id,
            'invoice_id' => null,
            'processor' => 'moyasar',
            'payment_method' => 'card',
            'details' => 'candidate-ref',
            'amount' => 99,
            'created_at' => now()->addMinute(),
            'updated_at' => now()->addMinute(),
        ]);

        $exitCode = Artisan::call('system:billing-integrity-audit', [
            '--format' => 'json',
        ]);

        $this->assertSame(0, $exitCode);
        $payload = json_decode((string) Artisan::output(), true);

        $this->assertSame('review_required', $payload['status'] ?? null);
        $this->assertSame(2, data_get($payload, 'before.categories.main_orgs_without_valid_plan.count'));
        $this->assertSame(1, data_get($payload, 'before.categories.branches_with_standalone_subscriptions.count'));
        $this->assertSame(1, data_get($payload, 'before.categories.orphan_branches.count'));
        $this->assertSame(1, data_get($payload, 'before.categories.subscriptions_with_missing_current_plan.count'));
        $this->assertSame(1, data_get($payload, 'before.categories.subscriptions_with_missing_scheduled_plan.count'));
        $this->assertSame(1, data_get($payload, 'before.categories.expired_subscriptions_still_marked_active.count'));
        $this->assertSame('info', data_get($payload, 'before.categories.expired_subscriptions_still_marked_active.severity'));
        $this->assertSame(1, data_get($payload, 'before.categories.duplicate_payment_groups.count'));
        $this->assertSame(1, data_get($payload, 'before.categories.payments_linked_to_foreign_invoice.count'));
        $this->assertSame(1, data_get($payload, 'before.categories.invoice_payment_link_candidates.count'));
        $this->assertSame($candidateInvoice->id, data_get($payload, 'before.categories.invoice_payment_link_candidates.samples.0.invoice_id'));
    }

    public function test_billing_integrity_audit_command_can_apply_safe_invoice_link_fix(): void
    {
        $plan = $this->createPlan('Remediation plan');
        $organization = $this->createOrganization('Healthy org');

        $this->attachActiveSubscription($organization, $plan);

        $invoice = BillingInvoice::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'subtotal' => 45,
            'coupon_amount' => 0,
            'tax' => 0,
            'tax_type' => 'exclusive',
            'total' => 45,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payment = BillingPayment::create([
            'organization_id' => $organization->id,
            'invoice_id' => null,
            'processor' => 'moyasar',
            'payment_method' => 'mada',
            'details' => 'safe-link-ref',
            'amount' => 45,
            'created_at' => now()->addMinute(),
            'updated_at' => now()->addMinute(),
        ]);

        $exitCode = Artisan::call('system:billing-integrity-audit', [
            '--format' => 'json',
            '--apply-safe-fixes' => true,
            '--strict' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $payment->refresh();
        $this->assertSame($invoice->id, $payment->invoice_id);

        $payload = json_decode((string) Artisan::output(), true);
        $this->assertSame(1, data_get($payload, 'applied_fixes.invoice_payment_links.applied'));
        $this->assertSame(0, data_get($payload, 'after.total_issue_count'));
        $this->assertSame('clean', $payload['status'] ?? null);
    }

    public function test_expired_raw_status_is_reported_as_informational_only(): void
    {
        $plan = $this->createPlan('Informational plan');
        $organization = $this->createOrganization('Informational org');

        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'trial',
            'start_date' => now()->subWeek(),
            'valid_until' => now()->subMinute(),
        ]);

        $exitCode = Artisan::call('system:billing-integrity-audit', [
            '--format' => 'json',
            '--strict' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $payload = json_decode((string) Artisan::output(), true);
        $this->assertSame(1, data_get($payload, 'before.categories.expired_subscriptions_still_marked_active.count'));
        $this->assertSame('info', data_get($payload, 'before.categories.expired_subscriptions_still_marked_active.severity'));
        $this->assertSame(0, data_get($payload, 'before.total_issue_count'));
        $this->assertSame('clean', $payload['status'] ?? null);
    }

    private function createOrganization(string $name, string $type = 'main', ?int $parentId = null): Organization
    {
        return Organization::create([
            'identifier' => strtolower(str_replace(' ', '-', $name)) . '-' . substr((string) microtime(true), -6),
            'name' => $name,
            'organization_type' => $type,
            'parent_organization_id' => $parentId,
            'created_by' => 1,
            'metadata' => json_encode([]),
        ]);
    }

    private function createPlan(string $name): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => $name,
            'name_en' => $name,
            'name_ar' => $name,
            'price' => 99,
            'period' => 'monthly',
            'status' => 'active',
            'metadata' => json_encode([
                'tier_rank' => 1,
                'addons' => [],
            ]),
        ]);
    }

    private function attachActiveSubscription(Organization $organization, SubscriptionPlan $plan): Subscription
    {
        return Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);
    }
}
