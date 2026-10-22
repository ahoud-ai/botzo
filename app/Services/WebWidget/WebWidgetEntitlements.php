<?php

namespace App\Services\WebWidget;

use App\Services\SubscriptionPlanLimitService;

/**
 * What an organization's plan allows in its website widget.
 */
class WebWidgetEntitlements
{
    public function __construct(private readonly SubscriptionPlanLimitService $plans)
    {
    }

    /**
     * @return array{remove_branding: bool, voice_minutes_monthly: int} voice minutes: 0 = none, -1 = unlimited
     */
    public function for(int $organizationId): array
    {
        $metadata = $this->plans->subscriptionForOrganization($organizationId)?->plan?->metadataArray() ?? [];

        return [
            'remove_branding' => (string) ($metadata['widget_remove_branding'] ?? '0') === '1',
            'voice_minutes_monthly' => (int) ($metadata['widget_voice_minutes_monthly'] ?? 0),
        ];
    }
}
