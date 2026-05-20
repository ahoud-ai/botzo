<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use DB;
use Socialite;

class SocialLoginService
{
    public function makeGoogleDriver(?string $redirect = null)
    {
        $metadata = app(SettingValueService::class)->getString('google_login', '{}');
        $metadata = json_decode($metadata);
        $app_id = $metadata !== false ? isset($metadata->client_id) ?  $metadata->client_id : '' : '';
        $app_secret = $metadata !== false ? isset($metadata->client_secret) ? $metadata->client_secret : '' : '';

        $config = [
            'client_id' => $app_id,
            'client_secret' => $app_secret,
            'redirect' => $redirect ?: url('google/callback'),
        ];

        return Socialite::buildProvider('\Laravel\Socialite\Two\GoogleProvider', $config);
    }

    public function makeFacebookDriver(?string $redirect = null)
    {
        $metadata = app(SettingValueService::class)->getString('facebook_login', '{}');
        $metadata = json_decode($metadata);
        $app_id = $metadata !== false ? isset($metadata->client_id) ?  $metadata->client_id : '' : '';
        $app_secret = $metadata !== false ? isset($metadata->client_secret) ? $metadata->client_secret : '' : '';

        $config = [
            'client_id' => $app_id,
            'client_secret' => $app_secret,
            'redirect' => $redirect ?: url('facebook/callback'),
        ];

        return Socialite::buildProvider('\Laravel\Socialite\Two\FacebookProvider', $config);
    }
}
