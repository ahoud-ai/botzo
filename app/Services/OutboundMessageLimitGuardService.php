<?php

namespace App\Services;

class OutboundMessageLimitGuardService
{
    public function blockedResponseForOrganization(int $organizationId): ?object
    {
        if (!SubscriptionService::isSubscriptionFeatureLimitReached($organizationId, 'message_limit')) {
            return null;
        }

        $response = new \stdClass;
        $response->success = false;
        $response->message = __('You have reached your message limit for the current subscription cycle. Please upgrade your plan to continue sending messages.');
        $response->data = (object) [
            'error' => (object) [
                'type' => 'subscription_limit',
                'feature' => 'message_limit',
            ],
        ];

        return $response;
    }
}
