<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationApiKey;
use App\Models\User;
use App\Services\OrganizationApiTokenHasher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackfillOrganizationApiKeysHashMigrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_migration_hashes_existing_plaintext_tokens_and_sets_last_four(): void
    {
        $organization = $this->createOrganization();
        $plainToken = 'previous-plain-token-'.Str::random(8).'1234';

        $apiKey = OrganizationApiKey::create([
            'organization_id' => $organization->id,
            'token' => $plainToken,
        ]);

        $migration = require base_path('database/migrations/2026_03_12_090000_harden_organization_api_keys.php');
        $migration->up();

        $apiKey->refresh();

        $this->assertSame(
            app(OrganizationApiTokenHasher::class)->hashToken($plainToken),
            $apiKey->getRawOriginal('token')
        );
        $this->assertSame('1234', $apiKey->token_last_four);
    }

    private function createOrganization(): Organization
    {
        $user = User::create([
            'first_name' => 'Migration',
            'last_name' => 'Tester',
            'email' => 'migration+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        return Organization::create([
            'identifier' => 'org-'.Str::lower(Str::random(8)),
            'name' => 'Org '.Str::random(5),
            'created_by' => $user->id,
            'metadata' => json_encode([]),
        ]);
    }
}
