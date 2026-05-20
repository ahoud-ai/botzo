<?php

namespace App\Services\Whatsapp;

use App\Models\Organization;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappAccessTokenRefreshService
{
    public function __construct(private readonly WhatsappTokenVault $tokenVault)
    {
    }

    public function resolveTokenForOrganization(int $organizationId, bool $attemptRefresh = true): ?string
    {
        $organization = Organization::find($organizationId);
        if (!$organization) {
            return null;
        }

        $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];
        if (!is_array($metadata)) {
            $metadata = [];
        }

        if ($attemptRefresh) {
            $result = $this->refreshIfNeeded($organizationId, false, $metadata);
            if (($result->success ?? false) && is_array($result->metadata ?? null)) {
                $metadata = $result->metadata;
            }
        }

        return $this->tokenVault->resolveTokenFromMetadata($metadata);
    }

    public function refreshIfNeeded(int $organizationId, bool $force = false, ?array $knownMetadata = null): object
    {
        $organization = Organization::find($organizationId);
        if (!$organization) {
            return $this->result(false, false, null, 'organization_not_found');
        }

        $metadata = $knownMetadata ?? ($organization->metadata ? json_decode($organization->metadata, true) : []);
        if (!is_array($metadata) || empty($metadata['whatsapp']) || !is_array($metadata['whatsapp'])) {
            return $this->result(true, false, $metadata, 'whatsapp_not_configured');
        }

        $token = $this->tokenVault->resolveTokenFromMetadata($metadata);
        if (empty($token)) {
            return $this->result(true, false, $metadata, 'missing_access_token');
        }

        $expiresAtRaw = data_get($metadata, 'whatsapp.access_token_expires_at');
        if (!$force) {
            if (empty($expiresAtRaw)) {
                return $this->result(true, false, $metadata, 'expiry_unknown');
            }

            try {
                $expiresAt = Carbon::parse($expiresAtRaw);
            } catch (\Throwable) {
                return $this->result(true, false, $metadata, 'invalid_expiry');
            }

            $refreshWindowHours = (int) config('graph.access_token_refresh_buffer_hours', 168);
            if ($expiresAt->gt(now()->addHours(max(1, $refreshWindowHours)))) {
                return $this->result(true, false, $metadata, 'not_due');
            }
        }

        $appId = (string) (data_get($metadata, 'whatsapp.app_id') ?: Setting::where('key', 'whatsapp_client_id')->value('value'));
        $isEmbeddedSignup = ((int) data_get($metadata, 'whatsapp.is_embedded_signup', 0)) === 1;
        $appSecret = $isEmbeddedSignup
            ? (string) (Setting::where('key', 'whatsapp_client_secret')->value('value') ?: '')
            : (string) ($this->tokenVault->resolveAppSecretFromMetadata($metadata) ?: '');

        if ($appId === '' || $appSecret === '') {
            return $this->result(true, false, $metadata, 'missing_app_credentials');
        }

        $exchange = $this->exchangeToLongLivedToken($token, $appId, $appSecret);
        if (!($exchange->success ?? false)) {
            Log::warning('WhatsApp access token refresh failed.', [
                'organization_id' => $organizationId,
                'reason' => $exchange->reason ?? 'exchange_failed',
                'message' => $exchange->message ?? null,
            ]);

            return $this->result(false, false, $metadata, $exchange->reason ?? 'exchange_failed', $exchange->message ?? null);
        }

        $newToken = (string) ($exchange->access_token ?? '');
        if ($newToken === '') {
            return $this->result(false, false, $metadata, 'exchange_missing_token');
        }

        $metadata['whatsapp']['access_token_encrypted'] = $this->tokenVault->encryptToken($newToken);
        unset($metadata['whatsapp']['access_token']);

        if (!empty($exchange->expires_at)) {
            $metadata['whatsapp']['access_token_expires_at'] = $exchange->expires_at;
        }
        $metadata['whatsapp']['access_token_last_refreshed_at'] = now()->toDateTimeString();

        $organization->metadata = json_encode($metadata);
        $organization->save();

        return $this->result(true, true, $metadata, 'refreshed', null, $exchange->expires_at ?? null);
    }

    public function exchangeToLongLivedToken(string $token, string $appId, string $appSecret): object
    {
        if ($token === '' || $appId === '' || $appSecret === '') {
            return (object) [
                'success' => false,
                'reason' => 'missing_exchange_input',
                'message' => __('Missing token/app credentials for exchange.'),
            ];
        }

        try {
            $response = Http::timeout(20)->get('https://graph.facebook.com/' . config('graph.api_version', 'v20.0') . '/oauth/access_token', [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'fb_exchange_token' => $token,
            ]);

            if (!$response->successful()) {
                return (object) [
                    'success' => false,
                    'reason' => 'graph_exchange_failed',
                    'message' => data_get($response->json(), 'error.message', __('Graph token exchange failed.')),
                ];
            }

            $accessToken = (string) ($response->json('access_token') ?? '');
            if ($accessToken === '') {
                return (object) [
                    'success' => false,
                    'reason' => 'graph_exchange_missing_token',
                    'message' => __('Graph exchange response did not include access_token.'),
                ];
            }

            $expiresIn = $response->json('expires_in');
            $expiresAt = is_numeric($expiresIn)
                ? now()->addSeconds((int) $expiresIn)->toDateTimeString()
                : null;

            return (object) [
                'success' => true,
                'reason' => 'ok',
                'access_token' => $accessToken,
                'expires_in' => $expiresIn,
                'expires_at' => $expiresAt,
            ];
        } catch (\Throwable $exception) {
            return (object) [
                'success' => false,
                'reason' => 'exchange_exception',
                'message' => $exception->getMessage(),
            ];
        }
    }

    private function result(
        bool $success,
        bool $refreshed,
        ?array $metadata,
        string $reason,
        ?string $message = null,
        ?string $expiresAt = null
    ): object {
        return (object) [
            'success' => $success,
            'refreshed' => $refreshed,
            'metadata' => $metadata,
            'reason' => $reason,
            'message' => $message,
            'expires_at' => $expiresAt,
        ];
    }
}
