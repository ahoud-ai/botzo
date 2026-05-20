<?php

namespace Tests\Unit;

use App\Services\Whatsapp\WhatsappTokenVault;
use Tests\TestCase;

class WhatsappTokenVaultTest extends TestCase
{
    public function test_encrypt_and_decrypt_round_trip(): void
    {
        $vault = new WhatsappTokenVault();
        $plainToken = 'EAAB-embedded-token-example';

        $encrypted = $vault->encryptToken($plainToken);

        $this->assertNotNull($encrypted);
        $this->assertNotSame($plainToken, $encrypted);
        $this->assertSame($plainToken, $vault->decryptToken($encrypted));
    }

    public function test_decrypt_invalid_payload_returns_null(): void
    {
        $vault = new WhatsappTokenVault();

        $this->assertNull($vault->decryptToken('invalid-payload'));
    }

    public function test_resolve_token_fallbacks_to_plain_token_when_encrypted_missing(): void
    {
        $vault = new WhatsappTokenVault();
        $metadata = [
            'whatsapp' => [
                'access_token' => 'previous-plain-token',
            ],
        ];

        $this->assertSame('previous-plain-token', $vault->resolveTokenFromMetadata($metadata));
    }
}
