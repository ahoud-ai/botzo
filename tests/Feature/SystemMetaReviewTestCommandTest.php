<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SystemMetaReviewTestCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_meta_review_command_outputs_json_report(): void
    {
        $this->seedEmbeddedSignupSettings();

        Http::fake([
            'https://graph.facebook.com/*/me/businesses*' => Http::response([
                'data' => [
                    ['id' => 'biz-1', 'name' => 'Botzo Solutions'],
                ],
            ], 200),
            'https://graph.facebook.com/*/biz-1/owned_whatsapp_business_accounts*' => Http::response([
                'data' => [
                    ['id' => 'waba-1', 'name' => 'Botzo WABA'],
                ],
            ], 200),
            'https://graph.facebook.com/*/waba-1/phone_numbers*' => Http::response([
                'data' => [
                    ['id' => 'phone-1', 'display_phone_number' => '+966501234567', 'verified_name' => 'Botzo'],
                ],
            ], 200),
        ]);

        $exitCode = Artisan::call('system:meta-review-test', [
            '--format' => 'json',
        ]);

        $this->assertSame(0, $exitCode);

        $payload = json_decode(Artisan::output(), true);

        $this->assertIsArray($payload);
        $this->assertSame('passed', $payload['status']);
        $this->assertSame(3, $payload['summary']['passed']);
    }

    public function test_meta_review_command_strict_mode_fails_when_access_token_is_missing(): void
    {
        $this->seedEmbeddedSignupSettings([
            'whatsapp_access_token' => '',
        ]);

        Http::fake();

        $exitCode = Artisan::call('system:meta-review-test', [
            '--format' => 'json',
            '--strict' => true,
        ]);

        $this->assertSame(1, $exitCode);

        $payload = json_decode(Artisan::output(), true);

        $this->assertIsArray($payload);
        $this->assertSame('failed', $payload['status']);
        $this->assertSame(3, $payload['summary']['failed']);
    }

    public function test_meta_review_command_falls_back_to_known_waba_owner_business_info_when_meta_returns_empty_businesses_list(): void
    {
        $this->seedEmbeddedSignupSettings();
        $this->seedOrganizationWithWaba('waba-1');

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/me/businesses')) {
                return Http::response([
                    'data' => [],
                ], 200);
            }

            if (preg_match('#/waba-1(?:\\?|$)#', $url)) {
                return Http::response([
                    'id' => 'waba-1',
                    'name' => 'Botzo WABA',
                    'owner_business_info' => [
                        'id' => 'biz-1',
                        'name' => 'Botzo Technologies',
                    ],
                ], 200);
            }

            if (str_contains($url, '/biz-1/owned_whatsapp_business_accounts')) {
                return Http::response([
                    'data' => [
                        ['id' => 'waba-1', 'name' => 'Botzo WABA', 'currency' => 'SAR', 'timezone_id' => '1'],
                    ],
                ], 200);
            }

            if (str_contains($url, '/waba-1/phone_numbers')) {
                return Http::response([
                    'data' => [
                        ['id' => 'phone-1', 'display_phone_number' => '+966501234567', 'verified_name' => 'Botzo'],
                    ],
                ], 200);
            }

            return Http::response([
                'error' => [
                    'message' => 'Unexpected URL in test fake.',
                ],
            ], 500);
        });

        $exitCode = Artisan::call('system:meta-review-test', [
            '--format' => 'json',
        ]);

        $this->assertSame(0, $exitCode);

        $payload = json_decode(Artisan::output(), true);

        $this->assertIsArray($payload);
        $this->assertSame('passed', $payload['status']);
        $this->assertSame(3, $payload['summary']['passed']);
        $this->assertSame('passed', data_get($payload, 'tests.0.status'));
        $this->assertSame('waba_owner_business_info', data_get($payload, 'tests.0.meta.resolution_strategy'));
    }

    private function seedEmbeddedSignupSettings(array $overrides = []): void
    {
        $settings = array_merge([
            'whatsapp_client_id' => 'app-id-123',
            'whatsapp_client_secret' => 'app-secret-123',
            'whatsapp_config_id' => 'config-id-123',
            'whatsapp_access_token' => 'system-user-token-123',
        ], $overrides);

        foreach ($settings as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }

    private function seedOrganizationWithWaba(string $wabaId): void
    {
        $user = User::create([
            'first_name' => 'Owner',
            'last_name' => 'Tester',
            'email' => 'owner+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        Organization::create([
            'identifier' => 'org-'.Str::lower(Str::random(8)),
            'name' => 'Org '.Str::random(5),
            'created_by' => $user->id,
            'metadata' => json_encode([
                'whatsapp' => [
                    'waba_id' => $wabaId,
                ],
            ]),
        ]);
    }
}
