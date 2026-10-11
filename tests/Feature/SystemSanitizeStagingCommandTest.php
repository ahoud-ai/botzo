<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Whatsapp\WhatsappTokenVault;
use App\Support\SensitiveSettingCodec;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class SystemSanitizeStagingCommandTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_it_strips_secrets_and_disconnects_whatsapp(): void
    {
        Setting::updateOrCreate(['key' => 'whatsapp_client_secret'], ['value' => 'real-app-secret']);
        Setting::updateOrCreate(['key' => 'whatsapp_callback_token'], ['value' => 'real-verify-token']);
        Setting::updateOrCreate(['key' => 'mail_config'], ['value' => json_encode(['driver' => 'smtp', 'password' => 'real-smtp-password'])]);
        Setting::updateOrCreate(['key' => 'company_name'], ['value' => 'Botzo']);

        $gatewayId = DB::table('payment_gateways')->insertGetId([
            'name' => 'StagingTestGateway',
            'metadata' => json_encode([
                'active_mode' => 'live',
                'test' => ['secret_key' => 'sk_test_x'],
                'live' => ['secret_key' => 'sk_live_real'],
                'secret_key' => 'sk_live_real',
            ]),
            'is_active' => 1,
        ]);

        [, $organization] = $this->createOwnerContext([
            'whatsapp' => [
                'waba_id' => 'real-waba',
                'access_token_encrypted' => (new WhatsappTokenVault())->encryptToken('real-token'),
            ],
            'ai' => ['active' => true, 'api_key_encrypted' => 'real-ai-key'],
            'timezone' => 'Asia/Riyadh',
        ]);

        $this->artisan('system:sanitize-staging', ['--confirm-database' => $this->databaseName()])
            ->assertSuccessful();

        $this->assertNull(Setting::getValueByKey('whatsapp_client_secret'));
        $this->assertNotSame('real-verify-token', Setting::getValueByKey('whatsapp_callback_token'));
        $this->assertStringNotContainsString('real-smtp-password', (string) Setting::getValueByKey('mail_config'));
        $this->assertSame('Botzo', Setting::getValueByKey('company_name'));

        $gatewayMetadata = (string) SensitiveSettingCodec::decrypt(DB::table('payment_gateways')->where('id', $gatewayId)->value('metadata'));
        $this->assertStringNotContainsString('sk_live_real', $gatewayMetadata);
        $this->assertSame('test', json_decode($gatewayMetadata, true)['active_mode']);
        $this->assertSame('sk_test_x', json_decode($gatewayMetadata, true)['secret_key']);

        $metadata = json_decode((string) $organization->fresh()->metadata, true);
        $this->assertArrayNotHasKey('whatsapp', $metadata);
        $this->assertArrayNotHasKey('api_key_encrypted', $metadata['ai']);
        $this->assertTrue($metadata['ai']['active']);
        $this->assertSame('Asia/Riyadh', $metadata['timezone']);
    }

    public function test_dry_run_writes_nothing(): void
    {
        Setting::updateOrCreate(['key' => 'whatsapp_client_secret'], ['value' => 'real-app-secret']);

        $this->artisan('system:sanitize-staging', ['--confirm-database' => $this->databaseName(), '--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame('real-app-secret', Setting::getValueByKey('whatsapp_client_secret'));
    }

    public function test_it_refuses_without_the_matching_database_name(): void
    {
        Setting::updateOrCreate(['key' => 'whatsapp_client_secret'], ['value' => 'real-app-secret']);

        $this->artisan('system:sanitize-staging', ['--confirm-database' => 'some-other-database'])
            ->assertFailed();

        $this->assertSame('real-app-secret', Setting::getValueByKey('whatsapp_client_secret'));
    }

    public function test_it_refuses_in_production(): void
    {
        Setting::updateOrCreate(['key' => 'whatsapp_client_secret'], ['value' => 'real-app-secret']);
        $this->app['env'] = 'production';

        $this->artisan('system:sanitize-staging', ['--confirm-database' => $this->databaseName()])
            ->assertFailed();

        $this->app['env'] = 'testing';
        $this->assertSame('real-app-secret', Setting::getValueByKey('whatsapp_client_secret'));
    }

    private function databaseName(): string
    {
        return (string) config('database.connections.'.config('database.default').'.database');
    }
}
