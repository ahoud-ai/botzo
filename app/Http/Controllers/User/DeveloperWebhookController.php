<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller as BaseController;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookDispatcher;
use App\Services\Webhooks\WebhookEndpointService;
use App\Services\Webhooks\WebhookInspector;
use App\Support\PublicMediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;

class DeveloperWebhookController extends BaseController
{
    public function __construct(
        private WebhookEndpointService $endpoints,
        private WebhookDispatcher $dispatcher,
        private WebhookInspector $inspector,
    ) {
    }

    /** What the developer tools page shows about webhooks. */
    public static function pageData(int $organizationId): array
    {
        $endpoints = WebhookEndpoint::where('organization_id', $organizationId)->orderBy('id')->get();
        $urls = $endpoints->pluck('url', 'id');

        return [
            'endpoints' => $endpoints->map(fn (WebhookEndpoint $endpoint) => [
                'uuid' => $endpoint->uuid,
                'url' => $endpoint->url,
                'events' => $endpoint->events,
                'is_active' => $endpoint->is_active,
                'disabled_at' => $endpoint->disabled_at?->toIso8601String(),
                'consecutive_failures' => $endpoint->consecutive_failures,
                'last_success_at' => $endpoint->last_success_at?->toIso8601String(),
            ])->all(),
            'deliveries' => WebhookDelivery::where('organization_id', $organizationId)
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(fn (WebhookDelivery $delivery) => [
                    'event_id' => $delivery->event_id,
                    'event' => $delivery->event,
                    'url' => $urls[$delivery->webhook_endpoint_id] ?? null,
                    'status' => $delivery->status,
                    'attempts' => $delivery->attempts,
                    'response_status' => $delivery->response_status,
                    'response_ms' => $delivery->response_ms,
                    'error' => $delivery->error,
                    'next_attempt_at' => $delivery->next_attempt_at?->toIso8601String(),
                    'created_at' => $delivery->created_at?->toIso8601String(),
                ])->all(),
            'events' => WebhookEndpoint::EVENTS,
            'max' => WebhookEndpoint::MAX_PER_ORGANIZATION,
            'inspector_url' => app(WebhookInspector::class)->url($organizationId),
            'inspector_requests' => app(WebhookInspector::class)->recent($organizationId),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $organizationId = $this->organizationId('developer_tools.add');
        $validated = $request->validate($this->rules());

        [, $secret] = $this->endpoints->create($organizationId, $validated['url'], $validated['events'], auth()->id());

        return Redirect::back()->with([
            'status' => ['type' => 'success', 'message' => __('Webhook added. Save its signing secret now because it will not be shown again.')],
            'generated_webhook_secret' => $secret,
        ]);
    }

    public function update(Request $request, string $uuid): RedirectResponse
    {
        $endpoint = $this->endpoint($uuid, 'developer_tools.edit');
        $validated = $request->validate([
            'events' => $this->rules()['events'],
            'events.*' => $this->rules()['events.*'],
            'is_active' => ['required', 'boolean'],
        ]);

        $this->endpoints->update($endpoint, $validated['events'], (bool) $validated['is_active']);

        return Redirect::back()->with('status', ['type' => 'success', 'message' => __('Webhook updated.')]);
    }

    public function rotate(string $uuid): RedirectResponse
    {
        $secret = $this->endpoints->rotateSecret($this->endpoint($uuid, 'developer_tools.edit'));

        return Redirect::back()->with([
            'status' => ['type' => 'success', 'message' => __('New signing secret created. Update your server with it now; the old one stopped working.')],
            'generated_webhook_secret' => $secret,
        ]);
    }

    public function test(string $uuid): RedirectResponse
    {
        $delivery = $this->dispatcher->sendTest($this->endpoint($uuid, 'developer_tools.edit'));

        return Redirect::back()->with('status', $delivery->status === WebhookDelivery::STATUS_SUCCEEDED
            ? ['type' => 'success', 'message' => __('Test event delivered in :ms ms.', ['ms' => $delivery->response_ms])]
            : ['type' => 'error', 'message' => __('Test event failed: :error', ['error' => $delivery->error])]);
    }

    public function destroy(string $uuid): RedirectResponse
    {
        $this->endpoint($uuid, 'developer_tools.delete')->delete();

        return Redirect::back()->with('status', ['type' => 'success', 'message' => __('Webhook deleted.')]);
    }

    public function inspector(): JsonResponse
    {
        return response()->json(['requests' => $this->inspector->recent($this->organizationId('developer_tools.view'))]);
    }

    private function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:500', function ($attribute, $value, $fail) {
                if (! PublicMediaUrl::isAllowed($value)) {
                    $fail(__('The webhook URL must be a public https link.'));
                }
            }],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(WebhookEndpoint::EVENTS)],
        ];
    }

    private function organizationId(string $permission): int
    {
        $organizationId = (int) session()->get('current_organization');
        $this->checkPermission($permission, $organizationId);

        return $organizationId;
    }

    private function endpoint(string $uuid, string $permission): WebhookEndpoint
    {
        return WebhookEndpoint::where('organization_id', $this->organizationId($permission))->where('uuid', $uuid)->firstOrFail();
    }
}
