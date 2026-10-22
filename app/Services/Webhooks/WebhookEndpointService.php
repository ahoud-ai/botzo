<?php

namespace App\Services\Webhooks;

use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WebhookEndpointService
{
    /** @return array{0: WebhookEndpoint, 1: string} the endpoint and its secret, shown once */
    public function create(int $organizationId, string $url, array $events, ?int $userId): array
    {
        if (WebhookEndpoint::where('organization_id', $organizationId)->count() >= WebhookEndpoint::MAX_PER_ORGANIZATION) {
            throw ValidationException::withMessages(['url' => __('You can add up to :count webhook URLs.', ['count' => WebhookEndpoint::MAX_PER_ORGANIZATION])]);
        }

        $secret = $this->newSecret();
        $endpoint = WebhookEndpoint::create([
            'organization_id' => $organizationId,
            'url' => $url,
            'events' => array_values(array_intersect(WebhookEndpoint::EVENTS, $events)),
            'secret_encrypted' => Crypt::encryptString($secret),
            'is_active' => true,
            'created_by' => $userId,
        ]);

        return [$endpoint, $secret];
    }

    public function update(WebhookEndpoint $endpoint, array $events, bool $active): WebhookEndpoint
    {
        $endpoint->events = array_values(array_intersect(WebhookEndpoint::EVENTS, $events));
        if ($active && ! $endpoint->is_active) {
            // Switching a disabled URL back on starts its failure count again.
            $endpoint->consecutive_failures = 0;
            $endpoint->disabled_at = null;
        }
        $endpoint->is_active = $active;
        $endpoint->save();

        return $endpoint;
    }

    public function rotateSecret(WebhookEndpoint $endpoint): string
    {
        $secret = $this->newSecret();
        $endpoint->forceFill(['secret_encrypted' => Crypt::encryptString($secret)])->save();

        return $secret;
    }

    private function newSecret(): string
    {
        return 'whsec_'.Str::random(40);
    }
}
