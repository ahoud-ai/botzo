<?php

namespace App\Services\EmbeddedSignup;

use App\Models\Addon;
use App\Models\Organization;
use App\Models\Setting;
use App\Services\SubscriptionPlanLimitService;
use App\Services\TrialAddonEntitlementService;

class EmbeddedSignupGate
{
    private readonly SubscriptionPlanLimitService $planLimitService;
    private readonly TrialAddonEntitlementService $trialAddonEntitlementService;

    public function __construct(
        ?SubscriptionPlanLimitService $planLimitService = null,
        ?TrialAddonEntitlementService $trialAddonEntitlementService = null,
    ) {
        $this->planLimitService = $planLimitService
            ?? app(SubscriptionPlanLimitService::class);
        $this->trialAddonEntitlementService = $trialAddonEntitlementService
            ?? app(TrialAddonEntitlementService::class);
    }

    public function isGloballyEnabled(): bool
    {
        $addon = Addon::where('name', 'Embedded Signup')
            ->where('status', 1)
            ->where('is_active', 1)
            ->exists();

        $flag = (int) Setting::where('key', 'is_embedded_signup_active')->value('value');

        return $addon && $flag === 1;
    }

    public function isPlanEnabled(int $organizationId): bool
    {
        $subscription = $this->planLimitService->activeSubscriptionForOrganization($organizationId);
        if (!$subscription) {
            return false;
        }

        $trialOverride = $this->trialAddonEntitlementService
            ->addonOverrideForSubscription($subscription, 'Embedded Signup');
        if ($trialOverride !== null) {
            return $trialOverride;
        }

        return $this->planLimitService->addonEnabledForOrganization($organizationId, 'Embedded Signup', false);
    }

    public function isOrganizationEnabled(int $organizationId): bool
    {
        $organization = Organization::find($organizationId);
        if (!$organization) {
            return false;
        }

        $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];

        return (bool) data_get($metadata, 'addons.embedded_signup_enabled', true);
    }

    public function isEnabledForOrganization(int $organizationId): bool
    {
        return $this->isGloballyEnabled()
            && $this->isPlanEnabled($organizationId)
            && $this->isOrganizationEnabled($organizationId);
    }
}
