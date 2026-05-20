<?php

namespace App\Services\System;

use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BillingIntegrityAuditService
{
    public function buildReport(int $sampleLimit = 25): array
    {
        $sampleLimit = max(1, $sampleLimit);

        $categories = [
            'main_orgs_without_valid_plan' => $this->detectMainOrganizationsWithoutValidPlan($sampleLimit),
            'branches_with_standalone_subscriptions' => $this->detectBranchesWithStandaloneSubscriptions($sampleLimit),
            'orphan_branches' => $this->detectOrphanBranches($sampleLimit),
            'subscriptions_with_missing_current_plan' => $this->detectSubscriptionsWithMissingCurrentPlan($sampleLimit),
            'subscriptions_with_missing_scheduled_plan' => $this->detectSubscriptionsWithMissingScheduledPlan($sampleLimit),
            'expired_subscriptions_still_marked_active' => $this->detectExpiredSubscriptionsStillMarkedActive($sampleLimit),
            'duplicate_payment_groups' => $this->detectDuplicatePaymentGroups($sampleLimit),
            'payments_linked_to_foreign_invoice' => $this->detectPaymentsLinkedToForeignInvoice($sampleLimit),
            'invoice_payment_link_candidates' => $this->detectInvoicePaymentLinkCandidates($sampleLimit),
        ];

        $totalIssueCount = collect($categories)->sum(function (array $category): int {
            return ($category['severity'] ?? 'error') === 'info'
                ? 0
                : (int) ($category['count'] ?? 0);
        });
        $totalSafeFixCandidates = collect($categories)->sum(function (array $category): int {
            return !empty($category['auto_fixable']) && ($category['severity'] ?? 'error') !== 'info'
                ? (int) ($category['count'] ?? 0)
                : 0;
        });

        return [
            'status' => $totalIssueCount === 0
                ? 'clean'
                : ($totalIssueCount === $totalSafeFixCandidates ? 'safe_fixes_available' : 'review_required'),
            'total_issue_count' => $totalIssueCount,
            'total_safe_fix_candidates' => $totalSafeFixCandidates,
            'categories' => $categories,
            'timestamp' => now()->toISOString(),
        ];
    }

    public function applySafeFixes(int $sampleLimit = 25): array
    {
        $sampleLimit = max(1, $sampleLimit);
        $candidates = $this->resolveInvoicePaymentLinkCandidates();
        $applied = [];

        if (BillingPayment::hasColumn('invoice_id') && $candidates->isNotEmpty()) {
            DB::transaction(function () use ($candidates, &$applied): void {
                foreach ($candidates as $candidate) {
                    $updated = BillingPayment::query()
                        ->whereKey($candidate['payment_id'])
                        ->whereNull('invoice_id')
                        ->update(['invoice_id' => $candidate['invoice_id']]);

                    if ($updated > 0) {
                        $applied[] = $candidate;
                    }
                }
            });
        }

        return [
            'invoice_payment_links' => [
                'attempted' => $candidates->count(),
                'applied' => count($applied),
                'skipped' => max(0, $candidates->count() - count($applied)),
                'samples' => array_slice(array_values($applied), 0, $sampleLimit),
            ],
            'timestamp' => now()->toISOString(),
        ];
    }

    private function detectMainOrganizationsWithoutValidPlan(int $sampleLimit): array
    {
        $organizations = Organization::query()
            ->whereNull('deleted_at')
            ->where('organization_type', 'main')
            ->with('subscription')
            ->get();

        $plans = SubscriptionPlan::withTrashed()
            ->whereIn('id', $organizations->pluck('subscription.plan_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        $issues = $organizations
            ->map(function (Organization $organization) use ($plans) {
                $subscription = $organization->subscription;

                if (!$subscription) {
                    return $this->organizationIssuePayload($organization, null, 'missing_subscription');
                }

                if (!$subscription->plan_id) {
                    return $this->organizationIssuePayload($organization, $subscription->id, 'missing_plan_id');
                }

                $plan = $plans->get((int) $subscription->plan_id);
                if (!$plan) {
                    return $this->organizationIssuePayload($organization, $subscription->id, 'plan_missing');
                }

                if ($plan->trashed() || $plan->status !== 'active') {
                    return $this->organizationIssuePayload($organization, $subscription->id, 'plan_inactive_or_deleted', (int) $plan->id);
                }

                return null;
            })
            ->filter()
            ->values();

        return $this->category(
            'Main organizations that cannot resolve an active billable plan.',
            $issues,
            $sampleLimit
        );
    }

    private function detectBranchesWithStandaloneSubscriptions(int $sampleLimit): array
    {
        $issues = Organization::query()
            ->whereNull('deleted_at')
            ->where('organization_type', 'branch')
            ->with(['subscription', 'parentOrganization'])
            ->get()
            ->filter(fn (Organization $organization): bool => $organization->subscription !== null)
            ->map(function (Organization $organization) {
                return [
                    'organization_id' => $organization->id,
                    'organization_uuid' => $organization->uuid,
                    'organization_name' => $organization->name,
                    'subscription_id' => $organization->subscription?->id,
                    'parent_organization_id' => $organization->parent_organization_id,
                    'parent_organization_name' => $organization->parentOrganization?->name,
                ];
            })
            ->values();

        return $this->category(
            'Branch organizations still carry standalone subscriptions instead of inheriting the parent billing owner.',
            $issues,
            $sampleLimit
        );
    }

    private function detectOrphanBranches(int $sampleLimit): array
    {
        $branches = Organization::query()
            ->whereNull('deleted_at')
            ->where('organization_type', 'branch')
            ->with(['parentOrganization' => fn ($query) => $query->withTrashed()])
            ->get();

        $issues = $branches
            ->map(function (Organization $organization) {
                if (!$organization->parent_organization_id) {
                    return $this->orphanBranchPayload($organization, 'missing_parent_reference');
                }

                $parent = $organization->parentOrganization;
                if (!$parent) {
                    return $this->orphanBranchPayload($organization, 'parent_not_found');
                }

                if ($parent->deleted_at !== null) {
                    return $this->orphanBranchPayload($organization, 'parent_soft_deleted');
                }

                if ($parent->organization_type !== 'main') {
                    return $this->orphanBranchPayload($organization, 'parent_not_main');
                }

                return null;
            })
            ->filter()
            ->values();

        return $this->category(
            'Branch organizations whose billing parent is missing, deleted, or invalid.',
            $issues,
            $sampleLimit
        );
    }

    private function detectSubscriptionsWithMissingCurrentPlan(int $sampleLimit): array
    {
        $subscriptions = Subscription::query()
            ->whereNotNull('plan_id')
            ->get();

        $organizations = Organization::query()
            ->whereIn('id', $subscriptions->pluck('organization_id')->unique()->all())
            ->get()
            ->keyBy('id');

        $plans = SubscriptionPlan::withTrashed()
            ->whereIn('id', $subscriptions->pluck('plan_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        $issues = $subscriptions
            ->map(function (Subscription $subscription) use ($organizations, $plans) {
                $plan = $plans->get((int) $subscription->plan_id);
                if ($plan && !$plan->trashed() && $plan->status === 'active') {
                    return null;
                }

                return [
                    'subscription_id' => $subscription->id,
                    'subscription_uuid' => $subscription->uuid,
                    'organization_id' => $subscription->organization_id,
                    'organization_name' => $organizations->get($subscription->organization_id)?->name,
                    'plan_id' => $subscription->plan_id,
                    'reason' => $plan ? 'plan_inactive_or_deleted' : 'plan_missing',
                ];
            })
            ->filter()
            ->values();

        return $this->category(
            'Subscriptions whose current plan reference is missing or points to an inactive/deleted plan.',
            $issues,
            $sampleLimit
        );
    }

    private function detectSubscriptionsWithMissingScheduledPlan(int $sampleLimit): array
    {
        if (!Schema::hasColumn('subscriptions', 'scheduled_plan_id')) {
            return $this->category(
                'Scheduled plan integrity checks are unavailable because the schema is missing scheduled_plan_id.',
                collect(),
                $sampleLimit
            );
        }

        $subscriptions = Subscription::query()
            ->whereNotNull('scheduled_plan_id')
            ->get();

        $organizations = Organization::query()
            ->whereIn('id', $subscriptions->pluck('organization_id')->unique()->all())
            ->get()
            ->keyBy('id');

        $plans = SubscriptionPlan::withTrashed()
            ->whereIn('id', $subscriptions->pluck('scheduled_plan_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        $issues = $subscriptions
            ->map(function (Subscription $subscription) use ($organizations, $plans) {
                $plan = $plans->get((int) $subscription->scheduled_plan_id);
                if ($plan && !$plan->trashed() && $plan->status === 'active') {
                    return null;
                }

                return [
                    'subscription_id' => $subscription->id,
                    'subscription_uuid' => $subscription->uuid,
                    'organization_id' => $subscription->organization_id,
                    'organization_name' => $organizations->get($subscription->organization_id)?->name,
                    'scheduled_plan_id' => $subscription->scheduled_plan_id,
                    'reason' => $plan ? 'scheduled_plan_inactive_or_deleted' : 'scheduled_plan_missing',
                ];
            })
            ->filter()
            ->values();

        return $this->category(
            'Subscriptions whose scheduled plan reference is missing or points to an inactive/deleted plan.',
            $issues,
            $sampleLimit
        );
    }

    private function detectExpiredSubscriptionsStillMarkedActive(int $sampleLimit): array
    {
        $subscriptions = Subscription::query()
            ->whereIn('status', ['trial', 'active'])
            ->whereNotNull('valid_until')
            ->get();

        $organizations = Organization::query()
            ->whereIn('id', $subscriptions->pluck('organization_id')->unique()->all())
            ->get()
            ->keyBy('id');

        $issues = $subscriptions
            ->filter(function (Subscription $subscription): bool {
                $rawValidUntil = $subscription->getRawOriginal('valid_until');
                if (!$rawValidUntil) {
                    return false;
                }

                return Carbon::parse($rawValidUntil)->lessThanOrEqualTo(now());
            })
            ->map(function (Subscription $subscription) use ($organizations) {
                return [
                    'subscription_id' => $subscription->id,
                    'subscription_uuid' => $subscription->uuid,
                    'organization_id' => $subscription->organization_id,
                    'organization_name' => $organizations->get($subscription->organization_id)?->name,
                    'status' => $subscription->status,
                    'valid_until' => $subscription->getRawOriginal('valid_until'),
                ];
            })
            ->values();

        return $this->category(
            'Subscriptions whose raw status is still active/trial even though valid_until has already passed.',
            $issues,
            $sampleLimit,
            false,
            'info'
        );
    }

    private function detectDuplicatePaymentGroups(int $sampleLimit): array
    {
        $issues = BillingPayment::query()
            ->selectRaw('processor, details, COUNT(*) as duplicate_count, SUM(amount) as duplicate_amount')
            ->whereNotNull('details')
            ->where('details', '!=', '')
            ->groupBy('processor', 'details')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->map(function ($row) {
                return [
                    'processor' => $row->processor,
                    'details' => $row->details,
                    'duplicate_count' => (int) $row->duplicate_count,
                    'duplicate_amount' => round((float) $row->duplicate_amount, 2),
                ];
            })
            ->values();

        return $this->category(
            'Duplicate payment groups sharing the same processor + external details key.',
            $issues,
            $sampleLimit
        );
    }

    private function detectPaymentsLinkedToForeignInvoice(int $sampleLimit): array
    {
        if (!BillingPayment::hasColumn('invoice_id')) {
            return $this->category(
                'Cross-organization invoice link checks are unavailable because billing_payments.invoice_id is missing.',
                collect(),
                $sampleLimit
            );
        }

        $payments = BillingPayment::query()
            ->whereNotNull('invoice_id')
            ->get();

        $invoices = BillingInvoice::query()
            ->whereIn('id', $payments->pluck('invoice_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        $issues = $payments
            ->map(function (BillingPayment $payment) use ($invoices) {
                $invoice = $invoices->get((int) $payment->invoice_id);
                if ($invoice && (int) $invoice->organization_id === (int) $payment->organization_id) {
                    return null;
                }

                return [
                    'payment_id' => $payment->id,
                    'payment_uuid' => $payment->uuid,
                    'organization_id' => $payment->organization_id,
                    'invoice_id' => $payment->invoice_id,
                    'invoice_organization_id' => $invoice?->organization_id,
                    'reason' => $invoice ? 'invoice_belongs_to_different_organization' : 'invoice_missing',
                ];
            })
            ->filter()
            ->values();

        return $this->category(
            'Payments linked to a missing invoice or to an invoice owned by another organization.',
            $issues,
            $sampleLimit
        );
    }

    private function detectInvoicePaymentLinkCandidates(int $sampleLimit): array
    {
        return $this->category(
            'Invoices that have exactly one unlinked payment match and can be safely backfilled.',
            $this->resolveInvoicePaymentLinkCandidates(),
            $sampleLimit,
            true
        );
    }

    private function resolveInvoicePaymentLinkCandidates(): Collection
    {
        if (!BillingPayment::hasColumn('invoice_id')) {
            return collect();
        }

        $invoices = BillingInvoice::query()->get();
        if ($invoices->isEmpty()) {
            return collect();
        }

        $unlinkedPayments = BillingPayment::query()
            ->whereNull('invoice_id')
            ->get()
            ->groupBy(fn (BillingPayment $payment): int => (int) $payment->organization_id);

        $linkedInvoiceIds = BillingPayment::query()
            ->whereNotNull('invoice_id')
            ->pluck('invoice_id')
            ->map(fn ($id): int => (int) $id)
            ->flip();

        return $invoices
            ->filter(function (BillingInvoice $invoice) use ($linkedInvoiceIds): bool {
                return !isset($linkedInvoiceIds[(int) $invoice->id]) && (float) $invoice->total > 0;
            })
            ->map(function (BillingInvoice $invoice) use ($unlinkedPayments) {
                $payments = $unlinkedPayments->get((int) $invoice->organization_id, collect())
                    ->filter(function (BillingPayment $payment) use ($invoice): bool {
                        return $this->amountsMatch((float) $payment->amount, (float) $invoice->total)
                            && $this->isWithinPreviousInvoiceWindow($invoice, $payment);
                    })
                    ->values();

                if ($payments->count() !== 1) {
                    return null;
                }

                $payment = $payments->first();

                return [
                    'invoice_id' => $invoice->id,
                    'invoice_uuid' => $invoice->uuid,
                    'organization_id' => $invoice->organization_id,
                    'payment_id' => $payment->id,
                    'payment_uuid' => $payment->uuid,
                    'invoice_total' => round((float) $invoice->total, 2),
                    'payment_amount' => round((float) $payment->amount, 2),
                    'invoice_created_at' => $invoice->getRawOriginal('created_at'),
                    'payment_created_at' => $payment->getRawOriginal('created_at'),
                ];
            })
            ->filter()
            ->values()
            ->groupBy('payment_id')
            ->filter(fn (Collection $group): bool => $group->count() === 1)
            ->map(fn (Collection $group) => $group->first())
            ->values();
    }

    private function category(
        string $summary,
        Collection $issues,
        int $sampleLimit,
        bool $autoFixable = false,
        string $severity = 'error'
    ): array {
        return [
            'summary' => $summary,
            'count' => $issues->count(),
            'auto_fixable' => $autoFixable,
            'severity' => $severity,
            'samples' => $issues->take($sampleLimit)->values()->all(),
        ];
    }

    private function organizationIssuePayload(
        Organization $organization,
        ?int $subscriptionId,
        string $reason,
        ?int $planId = null
    ): array {
        return [
            'organization_id' => $organization->id,
            'organization_uuid' => $organization->uuid,
            'organization_name' => $organization->name,
            'subscription_id' => $subscriptionId,
            'plan_id' => $planId,
            'reason' => $reason,
        ];
    }

    private function orphanBranchPayload(Organization $organization, string $reason): array
    {
        return [
            'organization_id' => $organization->id,
            'organization_uuid' => $organization->uuid,
            'organization_name' => $organization->name,
            'parent_organization_id' => $organization->parent_organization_id,
            'reason' => $reason,
        ];
    }

    private function isWithinPreviousInvoiceWindow(BillingInvoice $invoice, BillingPayment $payment): bool
    {
        if (!BillingInvoice::hasColumn('created_at') || !BillingPayment::hasColumn('created_at')) {
            return true;
        }

        $invoiceCreatedAt = $invoice->getRawOriginal('created_at');
        $paymentCreatedAt = $payment->getRawOriginal('created_at');

        if (!$invoiceCreatedAt || !$paymentCreatedAt) {
            return true;
        }

        $invoiceAt = Carbon::parse($invoiceCreatedAt);
        $paymentAt = Carbon::parse($paymentCreatedAt);

        return $paymentAt->betweenIncluded(
            $invoiceAt->copy()->subMinutes(10),
            $invoiceAt->copy()->addMinutes(5)
        );
    }

    private function amountsMatch(float $left, float $right): bool
    {
        return abs(round($left, 2) - round($right, 2)) < 0.01;
    }
}
