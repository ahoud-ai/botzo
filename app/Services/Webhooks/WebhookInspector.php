<?php

namespace App\Services\Webhooks;

use App\Models\WebhookEndpoint;
use App\Models\WebhookInspectorRequest;
use Illuminate\Http\Request;

/**
 * A test receiver inside Botzo: each organization gets a private URL that stores the last
 * requests sent to it, so a customer can see real webhook events and check the signature
 * before writing any code on their own server.
 */
class WebhookInspector
{
    private const KEEP = 20;
    private const MAX_BODY_BYTES = 65536;
    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    public function url(int $organizationId): string
    {
        return url('/api/webhook-inspector/'.$this->token($organizationId));
    }

    public function organizationFor(string $token): ?int
    {
        [$organizationId] = explode('.', $token, 2) + [null];

        return ctype_digit((string) $organizationId) && hash_equals($this->token((int) $organizationId), $token)
            ? (int) $organizationId
            : null;
    }

    public function store(int $organizationId, Request $request): WebhookInspectorRequest
    {
        $body = substr((string) $request->getContent(), 0, self::MAX_BODY_BYTES);
        $headers = collect(['content-type', 'user-agent', 'x-botzo-event', 'x-botzo-delivery', 'x-botzo-timestamp', 'x-botzo-signature'])
            ->mapWithKeys(fn (string $name) => [$name => (string) $request->header($name, '')])
            ->filter(fn (string $value) => $value !== '')
            ->all();

        $stored = WebhookInspectorRequest::create([
            'organization_id' => $organizationId,
            'event' => $headers['x-botzo-event'] ?? null,
            'headers' => $headers,
            'body' => $body,
            'signature_valid' => $this->signatureValid($organizationId, $headers, $body),
            'created_at' => now(),
        ]);

        $keepFrom = WebhookInspectorRequest::where('organization_id', $organizationId)->orderByDesc('id')->skip(self::KEEP - 1)->value('id');
        if ($keepFrom) {
            WebhookInspectorRequest::where('organization_id', $organizationId)->where('id', '<', $keepFrom)->delete();
        }

        return $stored;
    }

    public function recent(int $organizationId): array
    {
        return WebhookInspectorRequest::where('organization_id', $organizationId)
            ->orderByDesc('id')
            ->limit(self::KEEP)
            ->get()
            ->map(fn (WebhookInspectorRequest $request) => [
                'id' => $request->id,
                'event' => $request->event,
                'signature_valid' => $request->signature_valid,
                'headers' => $request->headers,
                'body' => $request->body,
                'received_at' => $request->created_at?->toIso8601String(),
            ])
            ->all();
    }

    // Valid when the signature matches the secret of one of the organization's webhooks that points here.
    private function signatureValid(int $organizationId, array $headers, string $body): ?bool
    {
        $signature = $headers['x-botzo-signature'] ?? '';
        $timestamp = $headers['x-botzo-timestamp'] ?? '';
        if ($signature === '' || ! ctype_digit($timestamp)) {
            return null;
        }
        if (abs(time() - (int) $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS) {
            return false;
        }

        return WebhookEndpoint::where('organization_id', $organizationId)
            ->where('url', $this->url($organizationId))
            ->get()
            ->contains(fn (WebhookEndpoint $endpoint) => hash_equals('sha256='.WebhookDispatcher::sign($endpoint->secret(), $timestamp, $body), $signature));
    }

    private function token(int $organizationId): string
    {
        return $organizationId.'.'.substr(hash_hmac('sha256', 'webhook-inspector:'.$organizationId, (string) config('app.key')), 0, 32);
    }
}
