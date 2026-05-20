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
            $organization = $this->resolveOrganizationForEmbeddedWebhook($request);
            if (! $organization) {
                Log::warning('Embedded WhatsApp webhook organization resolution failed.', [
                    'entry_id' => $request->input('entry.0.id'),
                    'phone_number_id' => $request->input('entry.0.changes.0.value.metadata.phone_number_id'),
                ]);

                return $this->forbiddenResponse();
            }

            if ($signatureError = $this->webhookVerification->verifyWhatsappRequest($request, $organization)) {
                return $signatureError;
            }

            return $this->handlePostRequest($request, $organization);
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
        if (! $this->payloadMatchesOrganization($request, $organization)) {
            Log::warning('WhatsApp webhook payload skipped because it does not match the route organization.', [
                'organization_id' => $organization->id,
                'payload_waba_id' => $request->input('entry.0.id'),
                'payload_phone_number_id' => $request->input('entry.0.changes.0.value.metadata.phone_number_id'),
            ]);

            return Response::json(['status' => 'success'], 200);
        }

        ProcessWebhookJob::dispatch($request->all(), (int) $organization->id)
            ->onQueue('webhook-media');

        return Response::json(['status' => 'success'], 200);
    }

    private function payloadMatchesOrganization(Request $request, Organization $organization): bool
    {
        $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];
        $expectedWabaId = data_get($metadata, 'whatsapp.waba_id');
        $expectedPhoneNumberId = data_get($metadata, 'whatsapp.phone_number_id');
        $payloadWabaId = $request->input('entry.0.id');
        $payloadPhoneNumberId = $request->input('entry.0.changes.0.value.metadata.phone_number_id');

        if ($payloadWabaId && $expectedWabaId && (string) $payloadWabaId !== (string) $expectedWabaId) {
            return false;
        }

        if ($payloadPhoneNumberId && $expectedPhoneNumberId && (string) $payloadPhoneNumberId !== (string) $expectedPhoneNumberId) {
            return false;
        }

        return true;
    }

    private function resolveOrganizationForEmbeddedWebhook(Request $request): ?Organization
    {
        $wabaId = $request->input('entry.0.id');
        $phoneNumberId = $request->input('entry.0.changes.0.value.metadata.phone_number_id');

        if (! empty($wabaId)) {
            $organization = Organization::where('metadata->whatsapp->waba_id', $wabaId)->first();

            if ($organization) {
                return $organization;
            }
        }

        if (! empty($phoneNumberId)) {
            return Organization::where('metadata->whatsapp->phone_number_id', $phoneNumberId)->first();
        }

        return null;
    }
}
