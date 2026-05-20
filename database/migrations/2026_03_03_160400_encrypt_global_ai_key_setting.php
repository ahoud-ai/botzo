<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $plain = DB::table('settings')->where('key', 'ai_global_api_key')->value('value');
        $encrypted = DB::table('settings')->where('key', 'ai_global_api_key_encrypted')->value('value');

        if ((!is_string($encrypted) || trim($encrypted) === '') && is_string($plain) && trim($plain) !== '') {
            DB::table('settings')->updateOrInsert(
                ['key' => 'ai_global_api_key_encrypted'],
                ['value' => Crypt::encryptString($plain)]
            );
        }

        DB::table('settings')->where('key', 'ai_global_api_key')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep encrypted value on rollback. Do not restore plaintext secrets.
    }
};

