<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class EncryptAiApiKeysMigrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_migration_encrypts_plain_api_key_and_removes_plaintext(): void
    {
        $organization = $this->createOrganizationWithAiMetadata([
            'api_key' => 'sk-test-plain',
        ]);

        $migration = require base_path('database/migrations/2026_03_03_130100_encrypt_ai_api_keys_in_organization_metadata.php');
        $migration->up();

        $organization->refresh();
        $metadata = json_decode($organization->metadata ?? '{}', true);

        $this->assertIsArray($metadata);
        $this->assertIsArray($metadata['ai'] ?? null);
        $this->assertArrayNotHasKey('api_key', $metadata['ai']);
        $this->assertNotEmpty($metadata['ai']['api_key_encrypted'] ?? null);
        $this->assertSame('sk-test-plain', Crypt::decryptString($metadata['ai']['api_key_encrypted']));
    }

    public function test_migration_preserves_existing_encrypted_key_and_removes_plaintext(): void
    {
        $existingEncrypted = Crypt::encryptString('sk-existing-encrypted');

        $organization = $this->createOrganizationWithAiMetadata([
            'api_key' => 'sk-previous-plain',
            'api_key_encrypted' => $existingEncrypted,
        ]);

        $migration = require base_path('database/migrations/2026_03_03_130100_encrypt_ai_api_keys_in_organization_metadata.php');
        $migration->up();

        $organization->refresh();
        $metadata = json_decode($organization->metadata ?? '{}', true);

        $this->assertIsArray($metadata);
        $this->assertArrayNotHasKey('api_key', $metadata['ai']);
        $this->assertSame('sk-existing-encrypted', Crypt::decryptString($metadata['ai']['api_key_encrypted']));
    }

    private function createOrganizationWithAiMetadata(array $ai): Organization
    {
        $user = User::create([
            'first_name' => 'AI',
            'last_name' => 'Tester',
            'email' => 'ai-migration+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        return Organization::create([
            'identifier' => 'org-'.Str::lower(Str::random(8)),
            'name' => 'Org '.Str::random(5),
            'created_by' => $user->id,
            'metadata' => json_encode([
                'ai' => $ai,
            ]),
        ]);
    }
}
