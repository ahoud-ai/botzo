<?php

namespace App\Http\Controllers;

use App\Contracts\WebhookVerificationContract;
use App\Http\Controllers\Controller as BaseController;
use App\Jobs\ProcessWebhookJob;
use App\Models\Organization;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class WebhookController extends BaseController
{
    public function __construct(
        private readonly WebhookVerificationContract $webhookVerification,
    ) {
        $pusherKey = Setting::where('key', 'pusher_app_key')->value('value');
        $pusherSecret = Setting::where('key', 'pusher_app_secret')->value('value');
        $pusherAppId = Setting::where('key', 'pusher_app_id')->value('value');
        $pusherCluster = Setting::where('key', 'pusher_app_cluster')->value('value');
        $broadcastDriver = Setting::where('key', 'broadcast_driver')->value('value') ?? 'pusher';

        Config::set('broadcasting.default', $broadcastDriver);

        if ($pusherKey && $pusherSecret && $pusherAppId) {
            Config::set('broadcasting.connections.pusher', [
                'driver' => 'pusher',
                'key' => $pusherKey,
                'secret' => $pusherSecret,
                'app_id' => $pusherAppId,
                'options' => [
                    'cluster' => $pusherCluster ?? 'mt1',
                ],
            ]);
        }
    }

    public function whatsappWebhook(Request $request)
    {
        $verifyToken = Setting::where('key', 'whatsapp_callback_token')->value('value');

        if ($request->isMethod('get')) {
            $mode = $request->input('hub_mode');
            $token = $request->input('hub_verify_token');
            $challenge = $request->input('hub_challenge');

            if ($mode === 'subscribe' && $token === $verifyToken) {
                return Response::make($challenge, 200);
            }

            return $this->forbiddenResponse();
        }

        if ($request->isMethod('post')) {
            $payloadsByOrganization = $this->groupEmbeddedPayloadByOrganization($request);
            if ($payloadsByOrganization === []) {
                Log::warning('Embedded WhatsApp webhook organization resolution failed.', [
                    'entry_id' => $request->input('entry.0.id'),
                    'phone_number_id' => $request->input('entry.0.changes.0.value.metadata.phone_number_id'),
                ]);

                return $this->forbiddenResponse();
            }

            $verifyingOrganization = $payloadsByOrganization[array_key_first($payloadsByOrganization)]['organization'];
            if ($signatureError = $this->webhookVerification->verifyWhatsappRequest($request, $verifyingOrganization)) {
                return $signatureError;
            }

            foreach ($payloadsByOrganization as $group) {
                $this->dispatchWebhookProcessing($group['payload'], (int) $group['organization']->id);
            }

            return Response::json(['status' => 'success'], 200);
        }

        return Response::json(['error' => __('Method Not Allowed')], 405);
    }

    public function handle(Request $request, $identifier = null)
    {
        $organization = Organization::where('identifier', $identifier)->first();

        if (! $organization) {
            Log::warning('WhatsApp webhook organization not found.', [
                'identifier' => $identifier,
            ]);

            return $this->forbiddenResponse();
        }

        if ($request->isMethod('get')) {
            return $this->handleGetRequest($request, $organization);
        }

        if ($request->isMethod('post')) {
            if ($signatureError = $this->webhookVerification->verifyWhatsappRequest($request, $organization)) {
                return $signatureError;
            }

            return $this->handlePostRequest($request, $organization);
        }

        return Response::json(['error' => __('Method Not Allowed')], 405);
    }

    protected function forbiddenResponse()
    {
        return Response::json(['error' => __('Forbidden')], 403);
    }

    protected function handleGetRequest(Request $request, Organization $organization)
    {
        $mode = $request->input('hub_mode');
        $token = $request->input('hub_verify_token');
        $challenge = $request->input('hub_challenge');

        if ($mode === 'subscribe' && $token === $organization->identifier) {
            return Response::make($challenge, 200);
        }

        return $this->forbiddenResponse();
    }

    protected function handlePostRequest(Request $request, Organization $organization)
    {
        $payload = $this->filterPayloadForOrganization($request->all(), $organization);

        if ($payload === null) {
            Log::warning('WhatsApp webhook payload skipped because it does not match the route organization.', [
                'organization_id' => $organization->id,
                'payload_waba_id' => $request->input('entry.0.id'),
                'payload_phone_number_id' => $request->input('entry.0.changes.0.value.metadata.phone_number_id'),
            ]);

            return Response::json(['status' => 'success'], 200);
        }

        $this->dispatchWebhookProcessing($payload, (int) $organization->id);

        return Response::json(['status' => 'success'], 200);
    }

    /**
     * Meta may batch several entries (and several changes per entry) into one POST.
     * Keep only the changes that belong to this organization's WABA / phone number.
     */
    private function filterPayloadForOrganization(array $payload, Organization $organization): ?array
    {
        $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];
        $expectedWabaId = data_get($metadata, 'whatsapp.waba_id');
        $expectedPhoneNumberId = data_get($metadata, 'whatsapp.phone_number_id');
        $entries = [];

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $wabaId = $entry['id'] ?? null;
            if ($wabaId && $expectedWabaId && (string) $wabaId !== (string) $expectedWabaId) {
                continue;
            }

            $changes = array_values(array_filter((array) ($entry['changes'] ?? []), function ($change) use ($expectedPhoneNumberId) {
                $phoneNumberId = is_array($change) ? data_get($change, 'value.metadata.phone_number_id') : null;

                return is_array($change)
                    && (! $phoneNumberId || ! $expectedPhoneNumberId || (string) $phoneNumberId === (string) $expectedPhoneNumberId);
            }));

            if ($changes !== []) {
                $entry['changes'] = $changes;
                $entries[] = $entry;
            }
        }

        if ($entries === []) {
            return null;
        }

        $payload['entry'] = $entries;

        return $payload;
    }

    /**
     * @return array<int, array{organization: Organization, payload: array}>
     */
    private function groupEmbeddedPayloadByOrganization(Request $request): array
    {
        $payload = $request->all();
        $groups = [];
        $organizationsByPhone = [];
        $organizationsByWaba = [];

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $wabaId = (string) ($entry['id'] ?? '');

            foreach ((array) ($entry['changes'] ?? []) as $change) {
                if (! is_array($change)) {
                    continue;
                }

                $phoneNumberId = (string) data_get($change, 'value.metadata.phone_number_id', '');
                $organization = null;

                if ($phoneNumberId !== '') {
                    $organization = $organizationsByPhone[$phoneNumberId]
                        ??= Organization::where('metadata->whatsapp->phone_number_id', $phoneNumberId)->first();
                }

                if (! $organization && $wabaId !== '') {
                    $organization = $organizationsByWaba[$wabaId]
                        ??= Organization::where('metadata->whatsapp->waba_id', $wabaId)->first();
                }

                if (! $organization) {
                    continue;
                }

                $groupKey = (int) $organization->id;
                $groups[$groupKey] ??= [
                    'organization' => $organization,
                    'payload' => array_merge($payload, ['entry' => []]),
                ];

                $entryIndex = null;
                foreach ($groups[$groupKey]['payload']['entry'] as $index => $groupedEntry) {
                    if ((string) ($groupedEntry['id'] ?? '') === $wabaId) {
                        $entryIndex = $index;
                        break;
                    }
                }

                if ($entryIndex === null) {
                    $groups[$groupKey]['payload']['entry'][] = array_merge($entry, ['changes' => []]);
                    $entryIndex = array_key_last($groups[$groupKey]['payload']['entry']);
                }

                $groups[$groupKey]['payload']['entry'][$entryIndex]['changes'][] = $change;
            }
        }

        return $groups;
    }

    private function dispatchWebhookProcessing(array $payload, int $organizationId): void
    {
        $mode = (string) config('graph.webhook_processing', 'after_response');

        try {
            if ($mode === 'queue') {
                ProcessWebhookJob::dispatch($payload, $organizationId)
                    ->onQueue((string) config('graph.webhook_queue', 'whatsapp-webhooks'));

                return;
            }

            if ($mode === 'sync') {
                ProcessWebhookJob::dispatchSync($payload, $organizationId);

                return;
            }

            // Default: respond to Meta first, then process in the same request lifecycle.
            app()->terminating(function () use ($payload, $organizationId): void {
                try {
                    ProcessWebhookJob::dispatchSync($payload, $organizationId);
                } catch (\Throwable $e) {
                    Log::error('Deferred webhook processing failed.', [
                        'organization_id' => $organizationId,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::error('Webhook processing dispatch failed.', [
                'organization_id' => $organizationId,
                'mode' => $mode,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
