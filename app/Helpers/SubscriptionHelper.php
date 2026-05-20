<?php 

namespace App\Helpers;

use App\Models\Setting;
use App\Models\Subscription;
use App\Services\OrganizationHierarchyService;
use App\Services\SubscriptionPlanLimitService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SubscriptionHelper
{
    public static function status(string $organizationId)
    {
        $subscription = app(SubscriptionPlanLimitService::class)->subscriptionForOrganization((int) $organizationId);

        return app(SubscriptionPlanLimitService::class)->operationalStatus($subscription) ?? 'billing_pending';
    }

    public static function info(string $organizationId)
    {
        return app(SubscriptionPlanLimitService::class)->subscriptionForOrganization((int) $organizationId);
    }

    public static function isActive(string $organizationId)
    {
        $subscription = app(SubscriptionPlanLimitService::class)->subscriptionForOrganization((int) $organizationId);

        return app(SubscriptionPlanLimitService::class)->isOperationallyActive($subscription);
    }
}
