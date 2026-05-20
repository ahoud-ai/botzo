<?php

namespace App\Services;

use App\Models\AutoReply;
use App\Models\Campaign;
use App\Models\Chat;
use App\Models\Contact;
use App\Models\Subscription;
use Carbon\Carbon;

class SubscriptionFeatureUsageService
{
    public function __construct(
        private readonly SubscriptionPlanLimitService $planLimitService,
        private readonly OrganizationHierarchyService $organizationHierarchyService,
        private readonly OrganizationUserSeatUsageService $organizationUserSeatUsageService,
        private readonly SettingValueService $settingValueService,
    ) {
    }

    /**
     * @return array{
     *     organization_id:int,
     *     billing_organization_id:int,
     *     usage_organization_ids:array<int>,
     *     feature:string,
     *     subscription:?Subscription,
     *     limit:int,
     *     used:int,
     *     reached:bool,
     *     window_start:?string,
     *     window_end:?string
     * }
     */
    public function snapshot(int $organizationId, string $feature): array
    {
        $organizationId = (int) $organizationId;
        $billingOrganizationId = $this->organizationHierarchyService->billingOwnerId($organizationId) ?? $organizationId;
        $usageOrganizationIds = $this->organizationHierarchyService->familyOrganizationIds($organizationId);
        $usageOrganizationIds = $usageOrganizationIds !== [] ? $usageOrganizationIds : [$organizationId];

        $subscription = $this->planLimitService->activeSubscriptionForOrganization($organizationId);
        if (!$subscription) {
            return [
                'organization_id' => $organizationId,
                'billing_organization_id' => $billingOrganizationId,
                'usage_organization_ids' => $usageOrganizationIds,
                'feature' => $feature,
                'subscription' => null,
                'limit' => 0,
                'used' => 0,
                'reached' => true,
                'window_start' => null,
                'window_end' => null,
            ];
        }

        $windowStart = $this->subscriptionDate($subscription, 'start_date');
        $windowEnd = $this->subscriptionDate($subscription, 'valid_until');
        $limit = $this->resolveLimit($subscription, $feature);
        $used = $this->resolveUsage($subscription, $usageOrganizationIds, $feature);

        return [
            'organization_id' => $organizationId,
            'billing_organization_id' => $billingOrganizationId,
            'usage_organization_ids' => $usageOrganizationIds,
            'feature' => $feature,
            'subscription' => $subscription,
            'limit' => $limit,
            'used' => $used,
            'reached' => $limit >= 0 && $used >= $limit,
            'window_start' => $windowStart?->toDateTimeString(),
            'window_end' => $windowEnd?->toDateTimeString(),
        ];
    }

    public function isReached(int $organizationId, string $feature): bool
    {
        return (bool) ($this->snapshot($organizationId, $feature)['reached'] ?? true);
    }

    private function resolveLimit(Subscription $subscription, string $feature): int
    {
        if ($this->isActiveTrial($subscription)) {
            $trialLimit = $this->trialLimitForFeature($feature);
            if ($trialLimit !== null) {
                return $trialLimit;
            }
        }

        return $this->planLimitService->limitForOrganization((int) $subscription->organization_id, $feature, -1);
    }

    /**
     * @param  array<int>  $usageOrganizationIds
     */
    private function resolveUsage(Subscription $subscription, array $usageOrganizationIds, string $feature): int
    {
        return match ($feature) {
            'canned_replies_limit' => AutoReply::query()
                ->whereIn('organization_id', $usageOrganizationIds)
                ->whereNull('deleted_at')
                ->count(),
            'contacts_limit' => Contact::query()
                ->whereIn('organization_id', $usageOrganizationIds)
                ->whereNull('deleted_at')
                ->count(),
            'campaign_limit' => Campaign::query()
                ->whereIn('organization_id', $usageOrganizationIds)
                ->whereNull('deleted_at')
                ->count(),
            'message_limit' => $this->outboundMessageCountForCurrentCycle($subscription, $usageOrganizationIds),
            'team_limit' => (int) ($this->organizationUserSeatUsageService->snapshot((int) $subscription->organization_id)['used'] ?? 0),
            default => 0,
        };
    }

    /**
     * @param  array<int>  $usageOrganizationIds
     */
    private function outboundMessageCountForCurrentCycle(Subscription $subscription, array $usageOrganizationIds): int
    {
        $query = Chat::query()
            ->whereIn('organization_id', $usageOrganizationIds)
            ->whereNull('deleted_at')
            ->where('type', 'outbound');

        $windowStart = $this->subscriptionDate($subscription, 'start_date');
        $windowEnd = $this->subscriptionDate($subscription, 'valid_until');

        if ($windowStart && $windowEnd) {
            $query->whereBetween('created_at', [
                $windowStart->copy()->startOfSecond(),
                $windowEnd->copy()->endOfSecond(),
            ]);
        }

        return $query->count();
    }

    private function isActiveTrial(Subscription $subscription): bool
    {
        return $subscription->status === 'trial'
            && Carbon::parse($subscription->getRawOriginal('valid_until') ?? $subscription->valid_until)->isFuture();
    }

    private function trialLimitForFeature(string $feature): ?int
    {
        $trialLimitsRaw = $this->settingValueService->getString('trial_limits', '');
        $trialLimits = $trialLimitsRaw !== '' ? json_decode($trialLimitsRaw, true) : [];
        if (!is_array($trialLimits)) {
            $trialLimits = [];
        }

        $key = match ($feature) {
            'canned_replies_limit' => 'automated_replies',
            'contacts_limit' => 'contacts',
            'campaign_limit' => 'campaigns',
            'message_limit' => 'messages',
            'team_limit' => 'users',
            default => null,
        };

        if ($key === null || !array_key_exists($key, $trialLimits)) {
            return null;
        }

        return $this->planLimitService->normalizeLimit($trialLimits[$key], -1);
    }

    private function subscriptionDate(Subscription $subscription, string $field): ?Carbon
    {
        $value = $subscription->getRawOriginal($field) ?? $subscription->{$field};
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value, 'UTC');
    }
}
