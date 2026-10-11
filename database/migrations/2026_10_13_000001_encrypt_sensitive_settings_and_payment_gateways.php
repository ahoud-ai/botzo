<?php

use App\Support\SensitiveSettingCodec;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Encrypts platform secrets that were stored as plain text: sensitive `settings`
 * rows (WhatsApp app secret and system token, SMTP, Pusher, social login, ...) and
 * the whole `payment_gateways.metadata` (API and webhook keys). down() decrypts
 * them back. Uses APP_KEY, so the key must stay the same across deploys.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->whereIn('key', SensitiveSettingCodec::SETTING_KEYS)
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->where('value', 'not like', SensitiveSettingCodec::PREFIX.'%')
            ->get(['key', 'value'])
            ->each(fn ($setting) => DB::table('settings')
                ->where('key', $setting->key)
                ->update(['value' => SensitiveSettingCodec::encrypt($setting->value)]));

        DB::table('payment_gateways')
            ->whereNotNull('metadata')
            ->where('metadata', 'not like', SensitiveSettingCodec::PREFIX.'%')
            ->get(['id', 'metadata'])
            ->each(fn ($gateway) => DB::table('payment_gateways')
                ->where('id', $gateway->id)
                ->update(['metadata' => SensitiveSettingCodec::encrypt($gateway->metadata)]));
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('value', 'like', SensitiveSettingCodec::PREFIX.'%')
            ->get(['key', 'value'])
            ->each(fn ($setting) => DB::table('settings')
                ->where('key', $setting->key)
                ->update(['value' => SensitiveSettingCodec::decrypt($setting->value)]));

        DB::table('payment_gateways')
            ->where('metadata', 'like', SensitiveSettingCodec::PREFIX.'%')
            ->get(['id', 'metadata'])
            ->each(fn ($gateway) => DB::table('payment_gateways')
                ->where('id', $gateway->id)
                ->update(['metadata' => SensitiveSettingCodec::decrypt($gateway->metadata)]));
    }
};
