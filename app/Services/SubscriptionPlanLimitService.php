<?php

namespace App\Services;

use App\Models\Subscription;
use App\Support\SaClientPlanProfile;
use Illuminate\Support\Carbon;

class SubscriptionPlanLimitService
{
    public function __construct(
        private readonly OrganizationHierarchyService $organizationHierarchyService,
    ) {
    }

    public function activeSubscriptionForOrganization(int $organizationId): ?Subscription
    {
        $subscription = $this->subscriptionForOrganization($organizationId);

        return $this->isOperationallyActive($subscription) ? $subscription : null;
    }

    public function subscriptionForOrganization(int $organizationId): ?Subscription
    {
        $billingOwnerId = $this->organizationHierarchyService->billingOwnerId($organizationId);
        if (!$billingOwnerId) {
            return null;
        }

        $subscriptions = Subscription::with(['plan', 'scheduledPlan'])
            ->where('organization_id', $billingOwnerId)
            ->get();

        if ($subscriptions->isEmpty()) {
            return null;
        }

        return $subscriptions
            ->sort(function (Subscription $left, Subscription $right) {
                $leftPriority = $this->subscriptionPriority($left);
                $rightPriority = $this->subscriptionPriority($right);

                foreach (array_keys($leftPriority) as $index) {
                    $comparison = $rightPriority[$index] <=> $leftPriority[$index];

                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }

                return 0;
            })
            ->first();
    }

    public function operationalStatus(?Subscription $subscription): ?string
    {
        if (!$subscription) {
            return null;
        }

        $validUntil = $subscription->getRawOriginal('valid_until') ?? $subscription->valid_until;
        $validUntilAt = $validUntil ? Carbon::parse($validUntil) : null;

        if (!$validUntilAt || $validUntilAt->isPast()) {
            return 'expired';
        }

        return match ($subscription->status) {
            'trial' => 'trial',
            'active' => 'active',
            default => 'expired',
        };
    }

    public function isOperationallyActive(?Subscription $subscription): bool
    {
        return in_array($this->operationalStatus($subscription), ['active', 'trial'], true);
    }

    public function metadataForOrganization(int $organizationId): array
    {
        $subscription = $this->activeSubscriptionForOrganization($organizationId);
        if (!$subscription || !$subscription->plan) {
            return [];
        }

        $metadata = $subscription->plan->metadata;
        if (is_array($metadata)) {
            return SaClientPlanProfile::sanitizePlanMetadata($metadata);
        }

        if (is_string($metadata) && trim($metadata) !== '') {
            $decoded = json_decode($metadata, true);
            if (is_array($decoded)) {
                return SaClientPlanProfile::sanitizePlanMetadata($decoded);
            }
        }

        return [];
    }

    public function limitForOrganization(int $organizationId, string $key, int $default = -1): int
    {
        $subscription = $this->activeSubscriptionForOrganization($organizationId);
        if (!$subscription || !$subscription->plan) {
            return 0;
        }

        return $this->normalizeLimit(
            $this->metadataForOrganization($organizationId)[$key] ?? $default,
            $default
        );
    }

    public function boolForOrganization(int $organizationId, string $key, bool $default = false): bool
    {
        $subscription = $this->activeSubscriptionForOrganization($organizationId);
        if (!$subscription || !$subscription->plan) {
            return false;
        }

        $value = $this->metadataForOrganization($organizationId)[$key] ?? $default;

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return $default;
    }

    public function addonEnabledForOrganization(int $organizationId, string $addonName, bool $default = false): bool
    {
        if (! SaClientPlanProfile::isPlanAddon($addonName)) {
            return false;
        }

        $subscription = $this->activeSubscriptionForOrganization($organizationId);
        if (!$subscription || !$subscription->plan) {
            return false;
        }

        $addons = $this->metadataForOrganization($organizationId)['addons'] ?? [];

        if (!is_array($addons)) {
            return $default;
        }

        return $this->normalizeBoolean($addons[$addonName] ?? $default, $default);
    }

    /**
     * @return array{start:Carbon,end:Carbon}
     */
    public function currentMonthWindow(?Carbon $now = null): array
    {
        $reference = $now ? $now->copy() : now();

        return [
            'start' => $reference->copy()->startOfMonth(),
            'end' => $reference->copy()->endOfMonth(),
        ];
    }

    public function normalizeLimit(mixed $value, int $default = -1): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (!is_numeric($value)) {
            return $default;
        }

        $limit = (int) $value;

        return $limit < -1 ? -1 : $limit;
    }

    private function subscriptionPriority(Subscription $subscription): array
    {
        return [
            $this->isOperationallyActive($subscription) ? 1 : 0,
            $this->subscriptionTimestamp($subscription, 'start_date'),
            $this->subscriptionTimestamp($subscription, 'valid_until'),
            (int) $subscription->id,
        ];
    }

    private function subscriptionTimestamp(Subscription $subscription, string $field): int
    {
        $value = $subscription->getRawOriginal($field) ?? $subscription->{$field};

        if ($value === null || $value === '') {
            return 0;
        }

        return Carbon::parse($value)->getTimestamp();
    }

    private function normalizeBoolean(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return $default;
    }
}
