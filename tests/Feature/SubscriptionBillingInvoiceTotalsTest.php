<?php

namespace Tests\Feature;

use App\Models\BillingTransaction;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TaxRate;
use App\Models\Team;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionBillingInvoiceTotalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_exclusive_tax_invoice_keeps_subtotal_tax_and_total_consistent(): void
    {
        Setting::query()->updateOrCreate(['key' => 'is_tax_inclusive'], ['value' => '0']);
        TaxRate::query()->create([
            'name' => 'VAT',
            'percentage' => 15,
            'status' => 'active',
        ]);

        [$owner, $organization] = $this->createOwnerWorkspace();
        $plan = $this->createPlan(100.00);

        Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $billingDetails = SubscriptionService::calculateSubscriptionBillingDetails($organization->id, $plan->id);
        $invoice = SubscriptionService::createBillingInvoice($billingDetails, $organization->id, $plan->id, $owner->id);

        $invoice->refresh();

        $this->assertEqualsWithDelta(100.00, (float) $invoice->subtotal, 0.01);
        $this->assertEqualsWithDelta(15.00, (float) $invoice->tax, 0.01);
        $this->assertEqualsWithDelta(115.00, (float) $invoice->total, 0.01);
        $this->assertEqualsWithDelta(
            (float) $invoice->total,
            (float) $invoice->subtotal + (float) $invoice->tax,
            0.01
        );
        $this->assertSame('exclusive', $invoice->tax_type);
        $this->assertEqualsWithDelta(
            -115.00,
            (float) BillingTransaction::query()
                ->where('organization_id', $organization->id)
                ->where('entity_type', 'invoice')
                ->value('amount'),
            0.01
        );
    }

    public function test_inclusive_tax_invoice_uses_pre_tax_subtotal_and_tax_inclusive_total(): void
    {
        Setting::query()->updateOrCreate(['key' => 'is_tax_inclusive'], ['value' => '1']);
        TaxRate::query()->create([
            'name' => 'VAT',
            'percentage' => 15,
            'status' => 'active',
        ]);

        [$owner, $organization] = $this->createOwnerWorkspace();
        $plan = $this->createPlan(115.00);

        Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $billingDetails = SubscriptionService::calculateSubscriptionBillingDetails($organization->id, $plan->id);
        $invoice = SubscriptionService::createBillingInvoice($billingDetails, $organization->id, $plan->id, $owner->id);

        $invoice->refresh();

        $this->assertEqualsWithDelta(100.00, (float) $invoice->subtotal, 0.01);
        $this->assertEqualsWithDelta(15.00, (float) $invoice->tax, 0.01);
        $this->assertEqualsWithDelta(115.00, (float) $invoice->total, 0.01);
        $this->assertEqualsWithDelta(
            (float) $invoice->total,
            (float) $invoice->subtotal + (float) $invoice->tax,
            0.01
        );
        $this->assertSame('inclusive', $invoice->tax_type);
    }

    private function createOwnerWorkspace(): array
    {
        $owner = User::query()->create([
            'first_name' => 'Billing',
            'last_name' => 'Owner',
            'email' => 'invoice-owner+'.Str::random(8).'@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $organization = Organization::query()->create([
            'name' => 'Billing Org '.Str::random(4),
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => $owner->id,
            'organization_type' => 'main',
            'parent_organization_id' => null,
            'metadata' => json_encode(['addons' => []]),
        ]);

        Team::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'organization_role_id' => $this->ownerRole()->id,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        return [$owner, $organization];
    }

    private function createPlan(float $price): SubscriptionPlan
    {
        return SubscriptionPlan::query()->create([
            'name' => 'Plan '.Str::random(4),
            'name_ar' => 'خطة '.Str::random(4),
            'name_en' => 'Plan '.Str::random(4),
            'price' => $price,
            'period' => 'monthly',
            'metadata' => json_encode([
                'tier_rank' => 2,
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

    private function ownerRole(): OrganizationRole
    {
        return OrganizationRole::query()->firstOrCreate(
            [
                'organization_id' => null,
                'name' => 'Owner',
            ],
            [
                'description' => 'Owner role for billing invoice totals tests',
                'permissions' => ['*'],
            ]
        );
    }
}
