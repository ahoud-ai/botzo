<?php

namespace App\Services;

use App\Contracts\FeatureGateContract;
use App\Models\Addon;
use App\Support\SaClientPlanProfile;

class AddonStateService implements FeatureGateContract
{
    public function __construct(
        private readonly SubscriptionPlanLimitService $planLimitService,
        private readonly TrialAddonEntitlementService $trialAddonEntitlementService,
    ) {
    }

    public function findByName(string $name): ?Addon
    {
        return Addon::query()->where('name', $name)->first();
    }

    public function isAddonEnabled(string $name): bool
    {
        if (SaClientPlanProfile::isRetiredAddon($name)) {
            return false;
        }

        $addon = $this->findByName($name);
        if (!$addon) {
            return false;
        }

        return (int) $addon->status === 1 && (int) $addon->is_active === 1;
    }

    public function isModuleEnabledForOrganization(string $name, ?int $organizationId = null): bool
    {
        if (!$this->isAddonEnabled($name)) {
            return false;
        }

        if (!$organizationId) {
            return false;
        }

        $subscription = $this->planLimitService->activeSubscriptionForOrganization($organizationId);
        if (!$subscription || !$subscription->plan) {
            return false;
        }

        $trialOverride = $this->trialAddonEntitlementService->addonOverrideForSubscription($subscription, $name);
        if ($trialOverride !== null) {
            return $trialOverride;
        }

        return $this->planLimitService->addonEnabledForOrganization($organizationId, $name, false);
    }
}

