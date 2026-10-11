<?php

namespace Tests\Feature;

use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Services\SettingValueService;
use App\Support\SensitiveSettingCodec;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SensitiveSettingEncryptionTest extends TestCase
{
    use DatabaseTransactions;

    private const MIGRATION = 'database/migrations/2026_10_13_000001_encrypt_sensitive_settings_and_payment_gateways.php';

    public function test_sensitive_settings_are_encrypted_at_rest_and_read_back_plain(): void
    {
        Setting::updateOrCreate(['key' => 'whatsapp_client_secret'], ['value' => 'real-app-secret']);
        Setting::updateOrCreate(['key' => 'company_name'], ['value' => 'Botzo']);

        $raw = DB::table('settings')->where('key', 'whatsapp_client_secret')->value('value');
        $this->assertStringStartsWith(SensitiveSettingCodec::PREFIX, $raw);
        $this->assertStringNotContainsString('real-app-secret', $raw);
        $this->assertSame('Botzo', DB::table('settings')->where('key', 'company_name')->value('value'), 'Non-secret settings stay readable.');

        // Every read style used in the codebase returns the plain value.
        $this->assertSame('real-app-secret', Setting::where('key', 'whatsapp_client_secret')->value('value'));
        $this->assertSame('real-app-secret', Setting::getValueByKey('whatsapp_client_secret'));
        $this->assertSame('real-app-secret', Setting::whereIn('key', ['whatsapp_client_secret'])->pluck('value', 'key')['whatsapp_client_secret']);
        $this->assertSame('real-app-secret', (new SettingValueService())->getString('whatsapp_client_secret'));
    }

    public function test_legacy_plaintext_and_older_encrypted_keys_still_read_as_stored(): void
    {
        DB::table('settings')->updateOrInsert(['key' => 'pusher_app_secret'], ['value' => 'legacy-plain']);
        DB::table('settings')->updateOrInsert(['key' => 'ai_global_api_key_encrypted'], ['value' => 'eyJpdiI6-older-format']);

        $this->assertSame('legacy-plain', Setting::getValueByKey('pusher_app_secret'));
        $this->assertSame('eyJpdiI6-older-format', Setting::getValueByKey('ai_global_api_key_encrypted'));
    }

    public function test_payment_gateway_metadata_is_encrypted_at_rest(): void
    {
        $gateway = PaymentGateway::create(['name' => 'EncryptionTestGateway', 'metadata' => json_encode(['secret_key' => 'sk_live_x']), 'is_active' => 0]);

        $raw = DB::table('payment_gateways')->where('id', $gateway->id)->value('metadata');
        $this->assertStringNotContainsString('sk_live_x', $raw);
        $this->assertSame('sk_live_x', json_decode($gateway->fresh()->metadata, true)['secret_key']);
    }

    public function test_migration_encrypts_existing_plaintext_rows_and_down_restores_them(): void
    {
        DB::table('settings')->updateOrInsert(['key' => 'mail_config'], ['value' => '{"password":"smtp-pass"}']);
        DB::table('settings')->updateOrInsert(['key' => 'company_name'], ['value' => 'Botzo']);
        $gatewayId = DB::table('payment_gateways')->insertGetId(['name' => 'MigrationTestGateway', 'metadata' => '{"secret_key":"sk_live_y"}', 'is_active' => 0]);

        $migration = require base_path(self::MIGRATION);
        $migration->up();
        $migration->up(); // idempotent: never double-encrypts

        $this->assertStringStartsWith(SensitiveSettingCodec::PREFIX, DB::table('settings')->where('key', 'mail_config')->value('value'));
        $this->assertSame('{"password":"smtp-pass"}', Setting::getValueByKey('mail_config'));
        $this->assertSame('Botzo', DB::table('settings')->where('key', 'company_name')->value('value'));
        $this->assertStringNotContainsString('sk_live_y', DB::table('payment_gateways')->where('id', $gatewayId)->value('metadata'));

        $migration->down();

        $this->assertSame('{"password":"smtp-pass"}', DB::table('settings')->where('key', 'mail_config')->value('value'));
        $this->assertSame('{"secret_key":"sk_live_y"}', DB::table('payment_gateways')->where('id', $gatewayId)->value('metadata'));
    }

    public function test_an_undecryptable_value_is_never_returned_as_ciphertext(): void
    {
        DB::table('settings')->updateOrInsert(['key' => 'whatsapp_client_secret'], ['value' => SensitiveSettingCodec::PREFIX.'not-valid']);

        $this->assertNull(Setting::getValueByKey('whatsapp_client_secret'));
    }
}
