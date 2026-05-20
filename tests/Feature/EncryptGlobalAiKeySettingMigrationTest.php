<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EncryptGlobalAiKeySettingMigrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_migration_encrypts_plain_global_ai_key_and_removes_plaintext(): void
    {
        DB::table('settings')->updateOrInsert(['key' => 'ai_global_api_key'], ['value' => 'sk-global-plain']);
        DB::table('settings')->where('key', 'ai_global_api_key_encrypted')->delete();

        $migration = require base_path('database/migrations/2026_03_03_160400_encrypt_global_ai_key_setting.php');
        $migration->up();

        $encrypted = DB::table('settings')->where('key', 'ai_global_api_key_encrypted')->value('value');
        $plain = DB::table('settings')->where('key', 'ai_global_api_key')->value('value');

        $this->assertIsString($encrypted);
        $this->assertSame('sk-global-plain', Crypt::decryptString($encrypted));
        $this->assertNull($plain);
    }

    public function test_migration_keeps_existing_encrypted_value_and_removes_plaintext_row(): void
    {
        $existingEncrypted = Crypt::encryptString('sk-existing-encrypted');
        DB::table('settings')->updateOrInsert(['key' => 'ai_global_api_key_encrypted'], ['value' => $existingEncrypted]);
        DB::table('settings')->updateOrInsert(['key' => 'ai_global_api_key'], ['value' => 'sk-previous-plain']);

        $migration = require base_path('database/migrations/2026_03_03_160400_encrypt_global_ai_key_setting.php');
        $migration->up();

        $encrypted = DB::table('settings')->where('key', 'ai_global_api_key_encrypted')->value('value');
        $plain = DB::table('settings')->where('key', 'ai_global_api_key')->value('value');

        $this->assertSame('sk-existing-encrypted', Crypt::decryptString($encrypted));
        $this->assertNull($plain);
    }
}

