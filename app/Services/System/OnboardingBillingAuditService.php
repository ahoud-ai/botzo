<?php

namespace App\Services\System;

use App\Models\BillingCheckoutIntent;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\BillingTransaction;
use App\Models\Campaign;
use App\Models\Chat;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\PaymentGateway;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\Template;
use App\Models\User;
use App\Services\MoyasarService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OnboardingBillingAuditService
{
    public function buildReport(int $days = 30, int $sampleLimit = 25): array
    {
        $sampleLimit = max(1, $sampleLimit);
        $since = now()->subDays(max(1, $days));

        $noUsage = $this->detectAutoProvisionedUnpaidWorkspaces($since, false);
        $withUsage = $this->detectAutoProvisionedUnpaidWorkspaces($since, true);
        $stuckIntents = $this->detectStuckMoyasarProcessingIntents($since);
        $gatewayWarnings = $this->detectGatewayConfigurationWarnings();

        $categories = [
            'auto_provisioned_unpaid_no_usage' => $this->category(
                'Main workspaces that received an automatic subscription without billing artifacts and still have no product usage.',
                $noUsage,
                $sampleLimit,
                true
            ),
            'auto_provisioned_unpaid_with_usage' => $this->category(
                'Main workspaces that received an automatic subscription without billing artifacts but already have product usage.',
                $withUsage,
                $sampleLimit
            ),
            'stuck_moyasar_processing_intents' => $this->category(
                'Moyasar subscription checkout intents still marked as processing after the checkout window.',
                $stuckIntents,
                $sampleLimit,
                true
            ),
            'gateway_configuration_warnings' => $this->category(
                'Payment gateways that are active but still missing required runtime configuration.',
                $gatewayWarnings,
                $sampleLimit
            ),
        ];

        $totalIssueCount = collect($categories)->sum(fn (array $category): int => (int) ($category['count'] ?? 0));
        $totalSafeFixCandidates = collect($categories)->sum(function (array $category): int {
            return !empty($category['auto_fixable']) ? (int) ($category['count'] ?? 0) : 0;
        });

        return [
            'status' => $totalIssueCount === 0
                ? 'clean'
                : ($totalSafeFixCandidates === $totalIssueCount ? 'safe_fixes_available' : 'review_required'),
            'since' => $since->toISOString(),
            'total_issue_count' => $totalIssueCount,
            'total_safe_fix_candidates' => $totalSafeFixCandidates,
            'categories' => $categories,
            'timestamp' => now()->toISOString(),
        ];
    }

    public function applySafeFixes(int $days = 30, int $sampleLimit = 25): array
    {
        $since = now()->subDays(max(1, $days));
        $candidates = $this->detectAutoProvisionedUnpaidWorkspaces($since, false);
        $stuckIntents = $this->detectStuckMoyasarProcessingIntents($since);
        $applied = [];
        $reconciled = [];

        DB::transaction(function () use ($candidates, &$applied): void {
            foreach ($candidates as $candidate) {
                $subscription = Subscription::query()
                    ->whereKey($candidate['subscription_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$subscription) {
                    continue;
                }

                if (!$this->subscriptionStillSafeToRemove($subscription)) {
                    continue;
                }

                $subscription->delete();
                $applied[] = $candidate;
            }
        });

        foreach ($stuckIntents as $candidate) {
            $externalReference = trim((string) ($candidate['external_reference'] ?? ''));

            if ($externalReference === '') {
                continue;
            }

            try {
                $result = app(MoyasarService::class)->completePayment($externalReference);
            } catch (\Throwable) {
                continue;
            }

            if (is_object($result) && ($result->success ?? false) === true) {
                $reconciled[] = $candidate;
            }
        }

        return [
            'removed_auto_provisioned_subscriptions' => [
                'attempted' => count($candidates),
                'applied' => count($applied),
                'skipped' => max(0, count($candidates) - count($applied)),
                'samples' => array_slice(array_values($applied), 0, max(1, $sampleLimit)),
            ],
            'reconciled_stuck_moyasar_intents' => [
                'attempted' => count($stuckIntents),
                'applied' => count($reconciled),
                'skipped' => max(0, count($stuckIntents) - count($reconciled)),
                'samples' => array_slice(array_values($reconciled), 0, max(1, $sampleLimit)),
            ],
            'timestamp' => now()->toISOString(),
        ];
    }

    private function detectAutoProvisionedUnpaidWorkspaces(CarbonInterface $since, bool $withUsage): array
    {
        $organizations = Organization::query()
            ->where('organization_type', 'main')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $since)
            ->with(['subscription', 'owner.user'])
            ->get();

        return $organizations
            ->map(function (Organization $organization) {
                $subscription = $organization->subscription;
                if (!$subscription) {
                    return null;
                }

                if (!$this->looksLikeAutoProvisionedSubscription($organization, $subscription)) {
                    return null;
                }

                $usage = $this->usageSnapshotForOrganization($organization->id);
                $owner = $organization->owner?->user;

                return [
                    'organization_id' => $organization->id,
                    'organization_uuid' => $organization->uuid,
                    'organization_name' => $organization->name,
                    'subscription_id' => $subscription->id,
                    'subscription_uuid' => $subscription->uuid,
                    'subscription_status' => $subscription->status,
                    'plan_id' => $subscription->plan_id,
                    'owner_user_id' => $owner?->id,
                    'owner_email' => $owner?->email,
                    'source_hint' => $this->ownerSourceHint($owner),
                    'organization_created_at' => $organization->getRawOriginal('created_at'),
                    'subscription_start_date' => $subscription->getRawOriginal('start_date'),
                    'subscription_valid_until' => $subscription->getRawOriginal('valid_until'),
                    'usage' => $usage,
                ];
            })
            ->filter()
            ->filter(function (array $row) use ($withUsage): bool {
                $hasUsage = $this->usageSnapshotHasActivity((array) ($row['usage'] ?? []));

                return $withUsage ? $hasUsage : !$hasUsage;
            })
            ->values()
            ->all();
    }

    private function detectStuckMoyasarProcessingIntents(CarbonInterface $since): array
    {
        if (!Schema::hasTable('billing_checkout_intents')) {
            return [];
        }

        return BillingCheckoutIntent::query()
            ->where('processor', 'moyasar')
            ->where('type', 'subscription_purchase')
            ->where('status', 'processing')
            ->where('created_at', '>=', $since)
            ->where('created_at', '<=', now()->subMinutes(5))
            ->orderByDesc('id')
            ->get()
            ->map(function (BillingCheckoutIntent $intent) {
                return [
                    'intent_id' => $intent->id,
                    'intent_uuid' => $intent->uuid,
                    'organization_id' => $intent->organization_id,
                    'billing_organization_id' => $intent->billing_organization_id,
                    'user_id' => $intent->user_id,
                    'target_plan_id' => $intent->target_plan_id,
                    'amount_due' => (float) $intent->amount_due,
                    'external_reference' => $intent->external_reference,
                    'created_at' => optional($intent->created_at)?->toISOString(),
                    'last_error' => $intent->last_error,
                ];
            })
            ->values()
            ->all();
    }

    private function detectGatewayConfigurationWarnings(): array
    {
        if (!Schema::hasTable('payment_gateways')) {
            return [];
        }

        return PaymentGateway::query()
            ->where('is_active', 1)
            ->get()
            ->map(function (PaymentGateway $gateway) {
                $metadata = $this->decodeMetadata($gateway->metadata);
                $normalized = strtolower(trim((string) $gateway->name));

                if ($normalized !== 'moyasar') {
                    return null;
                }

                $activeMode = strtolower((string) ($metadata['active_mode'] ?? $metadata['mode'] ?? 'test'));
                $activeMode = in_array($activeMode, ['test', 'live'], true) ? $activeMode : 'test';
                $activeConfig = is_array($metadata[$activeMode] ?? null) ? $metadata[$activeMode] : $metadata;
                $secretKey = trim((string) ($activeConfig['secret_key'] ?? $metadata['secret_key'] ?? ''));
                $webhookSecret = trim((string) ($activeConfig['webhook_secret'] ?? $metadata['webhook_secret'] ?? ''));

                $issues = [];
                if ($secretKey === '') {
                    $issues[] = 'missing_secret_key';
                }
                if ($webhookSecret === '') {
                    $issues[] = 'missing_webhook_secret';
                }
                if (app()->environment('production') && $activeMode !== 'live') {
                    $issues[] = 'production_running_non_live_mode';
                }

                if ($issues === []) {
                    return null;
                }

                return [
                    'gateway_id' => $gateway->id,
                    'gateway_name' => $gateway->name,
                    'active_mode' => $activeMode,
                    'issues' => $issues,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function looksLikeAutoProvisionedSubscription(Organization $organization, Subscription $subscription): bool
    {
        $createdAt = $organization->getRawOriginal('created_at');
        $startDate = $subscription->getRawOriginal('start_date');

        if (!$createdAt || !$startDate) {
            return false;
        }

        $createdAtTs = Carbon::parse($createdAt);
        $startDateTs = Carbon::parse($startDate);
        if ($createdAtTs->diffInMinutes($startDateTs) > 10) {
            return false;
        }

        if (BillingInvoice::query()->where('organization_id', $organization->id)->exists()) {
            return false;
        }

        if (BillingPayment::query()->where('organization_id', $organization->id)->exists()) {
            return false;
        }

        if (BillingTransaction::query()->where('organization_id', $organization->id)->exists()) {
            return false;
        }

        return true;
    }

    private function subscriptionStillSafeToRemove(Subscription $subscription): bool
    {
        $organization = Organization::query()->find($subscription->organization_id);
        if (!$organization || $organization->organization_type !== 'main') {
            return false;
        }

        if (BillingInvoice::query()->where('organization_id', $organization->id)->exists()) {
            return false;
        }

        if (BillingPayment::query()->where('organization_id', $organization->id)->exists()) {
            return false;
        }

        if (BillingTransaction::query()->where('organization_id', $organization->id)->exists()) {
            return false;
        }

        return !$this->usageSnapshotHasActivity($this->usageSnapshotForOrganization($organization->id));
    }

    private function usageSnapshotForOrganization(int $organizationId): array
    {
        return [
            'teams_count' => Team::query()->where('organization_id', $organizationId)->count(),
            'contacts_count' => Contact::query()->where('organization_id', $organizationId)->whereNull('deleted_at')->count(),
            'chats_count' => Chat::query()->where('organization_id', $organizationId)->whereNull('deleted_at')->count(),
            'campaigns_count' => Campaign::query()->where('organization_id', $organizationId)->whereNull('deleted_at')->count(),
            'templates_count' => Template::query()->where('organization_id', $organizationId)->whereNull('deleted_at')->count(),
        ];
    }

    private function usageSnapshotHasActivity(array $usage): bool
    {
        return ((int) ($usage['contacts_count'] ?? 0)) > 0
            || ((int) ($usage['chats_count'] ?? 0)) > 0
            || ((int) ($usage['campaigns_count'] ?? 0)) > 0
            || ((int) ($usage['templates_count'] ?? 0)) > 0
            || ((int) ($usage['teams_count'] ?? 0)) > 1;
    }

    private function ownerSourceHint(?User $user): string
    {
        if (!$user) {
            return 'unknown';
        }

        if (!empty($user->google_id)) {
            return 'google';
        }

        if (!empty($user->facebook_id)) {
            return 'facebook';
        }

        if ($user->password === null) {
            return 'social_or_passwordless';
        }

        return 'password_signup';
    }

    private function category(string $description, array $issues, int $sampleLimit, bool $autoFixable = false): array
    {
        return [
            'description' => $description,
            'count' => count($issues),
            'auto_fixable' => $autoFixable,
            'samples' => array_slice(array_values($issues), 0, $sampleLimit),
        ];
    }

    private function decodeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (!is_string($metadata) || trim($metadata) === '') {
            return [];
        }

        $decoded = json_decode($metadata, true);

        return is_array($decoded) ? $decoded : [];
    }
}
