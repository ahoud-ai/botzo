<?php

namespace App\Services;

use App\Models\Subscription;
use App\Support\SaClientPlanProfile;
use Carbon\Carbon;

class TrialAddonEntitlementService
{
    public function __construct(
        private readonly SettingValueService $settingValueService,
    ) {
    }

    public function addonOverrideForSubscription(?Subscription $subscription, string $addonName): ?bool
    {
        if (!$this->isActiveTrial($subscription)) {
            return null;
        }

        $configuredAddons = $this->configuredTrialAddons();

        if (!array_key_exists($addonName, $configuredAddons)) {
            return null;
        }

        return $this->normalizeBoolean($configuredAddons[$addonName], false);
    }

    public function isActiveTrial(?Subscription $subscription): bool
    {
        if (!$subscription || $subscription->status !== 'trial') {
            return false;
        }

        $validUntil = $subscription->getRawOriginal('valid_until') ?? $subscription->valid_until;

        return $validUntil !== null && Carbon::parse($validUntil)->isFuture();
    }

    public function configuredTrialAddons(): array
    {
        $raw = $this->settingValueService->getString('trial_addons', '');

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? SaClientPlanProfile::sanitizeAddonMap($decoded) : [];
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
