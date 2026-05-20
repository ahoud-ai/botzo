<?php

namespace App\Services\Webhooks;

use App\Contracts\WebhookVerificationContract;
use App\Models\Organization;
use App\Models\Setting;
use App\Services\Whatsapp\WhatsappTokenVault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookVerificationService implements WebhookVerificationContract
{
    public function __construct(private readonly WhatsappTokenVault $tokenVault)
    {
    }

    public function verifyWhatsappRequest(Request $request, Organization $organization): ?JsonResponse
    {
        $appSecret = $this->resolveWebhookAppSecret($organization);
        if (empty($appSecret)) {
            Log::warning('WhatsApp webhook rejected because app secret is missing.', [
                'organization_id' => $organization->id,
                'identifier' => $organization->identifier,
                'embedded_signup' => ((int) data_get(json_decode($organization->metadata, true) ?: [], 'whatsapp.is_embedded_signup', 0)) === 1,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => __('Webhook signature validation is not ready because the WhatsApp app secret is missing.'),
            ], 503);
        }

        $headerSignature = $request->header('X-Hub-Signature-256');
        $payload = $request->getContent();
        $calculatedSignature = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);

        if (empty($headerSignature) || !hash_equals($calculatedSignature, $headerSignature)) {
            return response()->json([
                'status' => 'error',
                'message' => __('Invalid payload signature'),
            ], 400);
        }

        return null;
    }

    private function resolveWebhookAppSecret(Organization $organization): ?string
    {
        $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];
        $isEmbeddedSignup = ((int) data_get($metadata, 'whatsapp.is_embedded_signup', 0)) === 1;

        if ($isEmbeddedSignup) {
            return Setting::where('key', 'whatsapp_client_secret')->value('value');
        }

        return $this->tokenVault->resolveAppSecretFromMetadata($metadata);
    }
}
