<?php

namespace App\Services\EmbeddedSignup;

use App\Models\Addon;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

class EmbeddedSignupService
{
    private string $apiVersion;

    public function __construct()
    {
        $this->apiVersion = config('graph.api_version');
    }

    public function isConfigured(): bool
    {
        $config = $this->getConfiguration();

        return !empty($config['app_id']) && !empty($config['app_secret']);
    }

    public function getAppId(): ?string
    {
        return Setting::where('key', 'whatsapp_client_id')->value('value');
    }

    public function getVerifyToken(): ?string
    {
        return Setting::where('key', 'whatsapp_callback_token')->value('value');
    }

    public function exchangeCodeForToken(string $code): object
    {
        $config = $this->getConfiguration();

        if (empty($config['app_id']) || empty($config['app_secret'])) {
            return $this->errorResponse(
                'META_CONFIG_MISSING',
                __('Embedded signup is not configured correctly. Contact the administrator.')
            );
        }

        $response = Http::get("https://graph.facebook.com/{$this->apiVersion}/oauth/access_token", [
            'client_id' => $config['app_id'],
            'client_secret' => $config['app_secret'],
            'code' => $code,
        ]);

        if (!$response->successful()) {
            return $this->errorResponse(
                'CODE_EXCHANGE_FAILED',
                $this->extractGraphError($response->json(), __('Unable to exchange embedded signup code.'))
            );
        }

        $accessToken = $response->json('access_token');
        if (!$accessToken) {
            return $this->errorResponse('CODE_EXCHANGE_FAILED', __('Unable to exchange embedded signup code.'));
        }

        return $this->successResponse([
            'access_token' => $accessToken,
            'token_type' => $response->json('token_type'),
            'expires_in' => $response->json('expires_in'),
        ]);
    }

    public function exchangeToLongLivedToken(string $shortLivedToken): object
    {
        $config = $this->getConfiguration();

        if (empty($config['app_id']) || empty($config['app_secret'])) {
            return $this->errorResponse(
                'META_CONFIG_MISSING',
                __('Embedded signup is not configured correctly. Contact the administrator.')
            );
        }

        $response = Http::get("https://graph.facebook.com/{$this->apiVersion}/oauth/access_token", [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $config['app_id'],
            'client_secret' => $config['app_secret'],
            'fb_exchange_token' => $shortLivedToken,
        ]);

        if (!$response->successful()) {
            return $this->errorResponse(
                'LONG_TOKEN_EXCHANGE_FAILED',
                $this->extractGraphError($response->json(), __('Unable to generate a long-lived access token.'))
            );
        }

        $longLivedToken = $response->json('access_token');
        if (!$longLivedToken) {
            return $this->errorResponse('LONG_TOKEN_EXCHANGE_FAILED', __('Unable to generate a long-lived access token.'));
        }

        return $this->successResponse([
            'access_token' => $longLivedToken,
            'token_type' => $response->json('token_type'),
            'expires_in' => $response->json('expires_in'),
            'expires_at' => $response->json('expires_in')
                ? now()->addSeconds((int) $response->json('expires_in'))->toDateTimeString()
                : null,
        ]);
    }

    public function resolveWabaConnection(string $accessToken, ?string $wabaId = null, ?string $phoneNumberId = null): object
    {
        if (!empty($wabaId)) {
            return $this->successResponse([
                'waba_id' => $wabaId,
                'phone_number_id' => $phoneNumberId,
            ]);
        }

        $response = Http::withToken($accessToken)->get("https://graph.facebook.com/{$this->apiVersion}/me", [
            'fields' => 'businesses{owned_whatsapp_business_accounts{id,phone_numbers{id}}}',
        ]);

        if (!$response->successful()) {
            return $this->errorResponse(
                'WABA_RESOLUTION_FAILED',
                $this->extractGraphError($response->json(), __('Unable to resolve WhatsApp business account from embedded signup.'))
            );
        }

        $businesses = data_get($response->json(), 'businesses.data', []);

        foreach ($businesses as $business) {
            $wabaAccounts = data_get($business, 'owned_whatsapp_business_accounts.data', []);
            foreach ($wabaAccounts as $wabaAccount) {
                $resolvedWabaId = data_get($wabaAccount, 'id');
                if (!$resolvedWabaId) {
                    continue;
                }

                $resolvedPhoneNumberId = data_get($wabaAccount, 'phone_numbers.data.0.id');

                return $this->successResponse([
                    'waba_id' => $resolvedWabaId,
                    'phone_number_id' => $phoneNumberId ?: $resolvedPhoneNumberId,
                ]);
            }
        }

        return $this->errorResponse('WABA_RESOLUTION_FAILED', __('Unable to resolve WhatsApp business account from embedded signup.'));
    }

    public function overrideWabaCallback(string $accessToken, string $wabaId): object
    {
        $verifyToken = $this->getVerifyToken();
        if (empty($verifyToken)) {
            return $this->errorResponse('META_CONFIG_MISSING', __('Missing webhook verify token.'));
        }

        $response = Http::withToken($accessToken)
            ->post("https://graph.facebook.com/{$this->apiVersion}/{$wabaId}/subscribed_apps", [
                'override_callback_uri' => URL::to('/webhook/waba'),
                'verify_token' => $verifyToken,
            ]);

        if (!$response->successful()) {
            return $this->errorResponse(
                'WEBHOOK_OVERRIDE_FAILED',
                $this->extractGraphError($response->json(), __('Unable to override webhook callback URL.'))
            );
        }

        return $this->successResponse([
            'override_callback_uri' => URL::to('/webhook/waba'),
            'verify_token' => $verifyToken,
        ]);
    }

    public function getFallbackOnboardingToken(): ?string
    {
        return Setting::where('key', 'whatsapp_access_token')->value('value');
    }

    public function buildHealthReport(): array
    {
        $addonActive = Addon::where('name', 'Embedded Signup')
            ->where('status', 1)
            ->where('is_active', 1)
            ->exists();
        $addonFlag = (int) Setting::where('key', 'is_embedded_signup_active')->value('value') === 1;
        $appId = Setting::where('key', 'whatsapp_client_id')->value('value');
        $appSecret = Setting::where('key', 'whatsapp_client_secret')->value('value');
        $configId = Setting::where('key', 'whatsapp_config_id')->value('value');
        $fallbackToken = Setting::where('key', 'whatsapp_access_token')->value('value');
        $verifyToken = Setting::where('key', 'whatsapp_callback_token')->value('value');

        $checks = [
            'addon_active' => $addonActive && $addonFlag,
            'app_id' => !empty($appId),
            'app_secret' => !empty($appSecret),
            'config_id' => !empty($configId),
            'fallback_token' => !empty($fallbackToken),
            'verify_token' => !empty($verifyToken),
        ];

        return [
            'is_ready' => !in_array(false, $checks, true),
            'checks' => $checks,
            'config' => [
                'whatsapp_client_id' => $appId,
                'whatsapp_config_id' => $configId,
                'whatsapp_callback_token' => $verifyToken,
            ],
        ];
    }

    private function getConfiguration(): array
    {
        return [
            'app_id' => Setting::where('key', 'whatsapp_client_id')->value('value'),
            'app_secret' => Setting::where('key', 'whatsapp_client_secret')->value('value'),
            'config_id' => Setting::where('key', 'whatsapp_config_id')->value('value'),
        ];
    }

    private function extractGraphError(?array $payload, string $fallback): string
    {
        if (!$payload) {
            return $fallback;
        }

        return data_get($payload, 'error.message', $fallback);
    }

    private function successResponse(array $data = []): object
    {
        $response = new \stdClass();
        $response->success = true;
        $response->data = $data;
        $response->code = null;
        $response->message = null;

        return $response;
    }

    private function errorResponse(string $code, string $message): object
    {
        $response = new \stdClass();
        $response->success = false;
        $response->code = $code;
        $response->message = $message;
        $response->data = [];

        return $response;
    }
}
