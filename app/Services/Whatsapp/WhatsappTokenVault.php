<?php

namespace App\Services\Whatsapp;

use App\Models\Organization;
use Illuminate\Support\Facades\Crypt;

class WhatsappTokenVault
{
    public function encryptToken(?string $token): ?string
    {
        return $this->encryptValue($token);
    }

    public function decryptToken(?string $encryptedToken): ?string
    {
        return $this->decryptValue($encryptedToken);
    }

    public function encryptAppSecret(?string $appSecret): ?string
    {
        return $this->encryptValue($appSecret);
    }

    public function decryptAppSecret(?string $encryptedSecret): ?string
    {
        return $this->decryptValue($encryptedSecret);
    }

    public function resolveTokenFromMetadata(array $metadata): ?string
    {
        $encryptedToken = data_get($metadata, 'whatsapp.access_token_encrypted');
        $decryptedToken = $this->decryptToken($encryptedToken);

        if (!empty($decryptedToken)) {
            return $decryptedToken;
        }

        return data_get($metadata, 'whatsapp.access_token');
    }

    public function resolveAppSecretFromMetadata(array $metadata): ?string
    {
        $encryptedSecret = data_get($metadata, 'whatsapp.app_secret_encrypted');
        $decryptedSecret = $this->decryptAppSecret($encryptedSecret);

        if (!empty($decryptedSecret)) {
            return $decryptedSecret;
        }

        return data_get($metadata, 'whatsapp.app_secret');
    }

    public function resolveTokenForOrganization(int $organizationId): ?string
    {
        $organization = Organization::find($organizationId);
        if (!$organization || empty($organization->metadata)) {
            return null;
        }

        $metadata = json_decode($organization->metadata, true) ?: [];

        return $this->resolveTokenFromMetadata($metadata);
    }

    public function resolveAppSecretForOrganization(int $organizationId): ?string
    {
        $organization = Organization::find($organizationId);
        if (!$organization || empty($organization->metadata)) {
            return null;
        }

        $metadata = json_decode($organization->metadata, true) ?: [];

        return $this->resolveAppSecretFromMetadata($metadata);
    }

    private function encryptValue(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return Crypt::encryptString($value);
    }

    private function decryptValue(?string $encryptedValue): ?string
    {
        if (empty($encryptedValue)) {
            return null;
        }

        try {
            return Crypt::decryptString($encryptedValue);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
