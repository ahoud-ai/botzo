<?php

namespace Tests\Feature;

use App\Services\Whatsapp\WhatsappTokenVault;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class OrganizationMetadataRedactionTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_serialized_organizations_never_expose_whatsapp_or_ai_secrets(): void
    {
        $vault = new WhatsappTokenVault();
        [, $organization] = $this->createOwnerContext([
            'whatsapp' => [
                'waba_id' => 'waba-123',
                'access_token' => 'legacy-plain-token',
                'access_token_encrypted' => $vault->encryptToken('secret-token'),
                'app_secret' => 'legacy-plain-secret',
                'app_secret_encrypted' => $vault->encryptAppSecret('secret-app'),
                'two_step_pin_encrypted' => $vault->encryptTwoStepPin('123456'),
            ],
            'ai' => [
                'active' => true,
                'api_key' => 'sk-legacy',
                'api_key_encrypted' => 'encrypted-ai-key',
            ],
        ]);

        $serialized = json_encode($organization->fresh());
        $clientMetadata = json_decode(json_decode($serialized, true)['metadata'], true);

        foreach (['access_token', 'access_token_encrypted', 'app_secret', 'app_secret_encrypted', 'two_step_pin_encrypted'] as $key) {
            $this->assertArrayNotHasKey($key, $clientMetadata['whatsapp']);
        }
        $this->assertArrayNotHasKey('api_key', $clientMetadata['ai']);
        $this->assertArrayNotHasKey('api_key_encrypted', $clientMetadata['ai']);
        $this->assertStringNotContainsString('legacy-plain-token', $serialized);
        $this->assertStringNotContainsString('sk-legacy', $serialized);

        // Non-secret settings stay available to the UI.
        $this->assertSame('waba-123', $clientMetadata['whatsapp']['waba_id']);
        $this->assertTrue($clientMetadata['ai']['active']);
    }

    public function test_server_side_code_still_reads_the_secrets(): void
    {
        $vault = new WhatsappTokenVault();
        [, $organization] = $this->createOwnerContext([
            'whatsapp' => ['access_token_encrypted' => $vault->encryptToken('secret-token')],
        ]);

        $metadata = json_decode($organization->fresh()->metadata, true);

        $this->assertSame('secret-token', $vault->resolveTokenFromMetadata($metadata));
    }
}
