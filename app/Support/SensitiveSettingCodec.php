<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * At-rest encryption for platform secrets stored in `settings` and `payment_gateways`.
 *
 * Encrypted values carry a prefix, so reads can decrypt without knowing the setting key
 * (Eloquent ->value() / ->pluck() only load the value column). Values without the prefix
 * are returned as-is, which keeps legacy plaintext rows and the older *_encrypted keys
 * (decrypted by their own services) working.
 */
final class SensitiveSettingCodec
{
    public const PREFIX = 'enc:v1:';

    /** Setting keys whose values are secrets. */
    public const SETTING_KEYS = [
        'whatsapp_access_token',
        'whatsapp_client_secret',
        'whatsapp_callback_token',
        'pusher_app_secret',
        'mail_config',
        'google_login',
        'facebook_login',
        'aws',
        'aws_access_key',
        'aws_secret_key',
        'recaptcha_secret_key',
        'ai_global_api_key',
    ];

    public static function isSensitiveSetting(?string $key): bool
    {
        return $key !== null && in_array($key, self::SETTING_KEYS, true);
    }

    public static function encodeSetting(?string $key, mixed $value): mixed
    {
        return self::isSensitiveSetting($key) ? self::encrypt($value) : $value;
    }

    public static function isEncrypted(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }

    public static function encrypt(mixed $value): mixed
    {
        if ($value === null || $value === '' || self::isEncrypted($value) || ! is_scalar($value)) {
            return $value;
        }

        return self::PREFIX.Crypt::encryptString((string) $value);
    }

    public static function decrypt(mixed $value): mixed
    {
        if (! self::isEncrypted($value)) {
            return $value;
        }

        try {
            return Crypt::decryptString(substr($value, strlen(self::PREFIX)));
        } catch (DecryptException) {
            // Never hand ciphertext to callers (it would be used as a password or key).
            Log::error('A stored secret could not be decrypted; check that APP_KEY has not changed.');

            return null;
        }
    }
}
