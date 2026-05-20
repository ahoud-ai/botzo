<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Whatsapp\WhatsappTokenVault;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class WhatsappWebhookSignatureTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_manual_webhook_accepts_valid_signature(): void
    {
        [, $organization] = $this->createOwnerContext([
            'whatsapp' => [
                'is_embedded_signup' => 0,
                'app_secret_encrypted' => (new WhatsappTokenVault())->encryptAppSecret('manual-secret'),
            ],
        ]);

        $payload = json_encode([
            'entry' => [[
                'changes' => [[
                    'field' => 'account_review_update',
                    'value' => [
                        'decision' => 'APPROVED',
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'manual-secret');

        $response = $this->call(
            'POST',
            '/webhook/whatsapp/'.$organization->identifier,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
            ],
            $payload
        );

        $response->assertOk();
        $response->assertJson(['status' => 'success']);
    }

    public function test_manual_webhook_rejects_invalid_signature(): void
    {
        [, $organization] = $this->createOwnerContext([
            'whatsapp' => [
                'is_embedded_signup' => 0,
                'app_secret_encrypted' => (new WhatsappTokenVault())->encryptAppSecret('manual-secret'),
            ],
        ]);

        $payload = json_encode([
            'entry' => [[
                'changes' => [[
                    'field' => 'account_review_update',
                    'value' => [
                        'decision' => 'APPROVED',
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $response = $this->call(
            'POST',
            '/webhook/whatsapp/'.$organization->identifier,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid',
            ],
            $payload
        );

        $response->assertStatus(400);
        $response->assertJson(['status' => 'error']);
    }

    public function test_manual_webhook_rejects_requests_when_secret_is_missing(): void
    {
        [, $organization] = $this->createOwnerContext([
            'whatsapp' => [
                'is_embedded_signup' => 0,
            ],
        ]);

        $payload = json_encode([
            'entry' => [[
                'changes' => [[
                    'field' => 'account_review_update',
                    'value' => [
                        'decision' => 'APPROVED',
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $response = $this->call(
            'POST',
            '/webhook/whatsapp/'.$organization->identifier,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(503);
        $response->assertJson(['status' => 'error']);
    }

    public function test_embedded_webhook_uses_global_app_secret(): void
    {
        Setting::updateOrCreate(['key' => 'whatsapp_callback_token'], ['value' => 'verify-token']);
        Setting::updateOrCreate(['key' => 'whatsapp_client_secret'], ['value' => 'embedded-secret']);

        [, $organization] = $this->createOwnerContext([
            'whatsapp' => [
                'is_embedded_signup' => 1,
                'waba_id' => 'waba-123',
                'phone_number_id' => 'phone-123',
            ],
        ]);

        $payload = json_encode([
            'entry' => [[
                'id' => 'waba-123',
                'changes' => [[
                    'field' => 'account_review_update',
                    'value' => [
                        'metadata' => [
                            'phone_number_id' => 'phone-123',
                        ],
                        'decision' => 'APPROVED',
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'embedded-secret');

        $response = $this->call(
            'POST',
            '/webhook/waba',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
            ],
            $payload
        );

        $response->assertOk();
        $response->assertJson(['status' => 'success']);
    }

    public function test_manual_webhook_get_verification_is_unchanged(): void
    {
        [, $organization] = $this->createOwnerContext();

        $response = $this->get('/webhook/whatsapp/'.$organization->identifier.'?hub_mode=subscribe&hub_verify_token='.$organization->identifier.'&hub_challenge=challenge-token');

        $response->assertOk();
        $response->assertSee('challenge-token');
    }
}
