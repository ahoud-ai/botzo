<?php

namespace App\Services\Salla;

use App\Models\SallaStore;
use App\Services\SubscriptionPlanLimitService;

/**
 * What being a Salla store changes for an organization. Organizations without a store row are not affected.
 */
class SallaStoreAccess
{
    public function __construct(private readonly SubscriptionPlanLimitService $plans)
    {
    }

    /** Salla collects the payment, so Botzo's own checkout must not. */
    public function billingIsManagedBySalla(int $organizationId): bool
    {
        return SallaStore::query()->where('organization_id', $organizationId)->exists();
    }

    public function assistantAllowed(int $organizationId): bool
    {
        $status = SallaStore::query()->where('organization_id', $organizationId)->value('status');

        if ($status === null) {
            return true;
        }

        return $status !== SallaStore::STATUS_UNINSTALLED
            && $this->plans->activeSubscriptionForOrganization($organizationId) !== null;
    }
}
