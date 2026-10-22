<?php

use App\Services\OrganizationApiTokenHasher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_api_keys', function (Blueprint $table) {
            if (! Schema::hasColumn('organization_api_keys', 'name')) {
                $table->string('name', 60)->nullable()->after('organization_id');
            }
            if (! Schema::hasColumn('organization_api_keys', 'scope')) {
                // Existing keys keep full access so no connected app breaks.
                $table->string('scope', 16)->default('full')->after('token_last_four');
            }
        });

        // Lookups now accept hashed tokens only: hash any key still stored as plain text.
        $hasher = app(OrganizationApiTokenHasher::class);
        DB::table('organization_api_keys')
            ->select(['id', 'token'])
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($hasher) {
                foreach ($rows as $row) {
                    if (! is_string($row->token) || $row->token === '' || $hasher->looksHashed($row->token)) {
                        continue;
                    }

                    DB::table('organization_api_keys')->where('id', $row->id)->update([
                        'token' => $hasher->hash($row->token),
                        'token_last_four' => $hasher->lastFour($row->token),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('organization_api_keys', function (Blueprint $table) {
            if (Schema::hasColumn('organization_api_keys', 'scope')) {
                $table->dropColumn('scope');
            }
            if (Schema::hasColumn('organization_api_keys', 'name')) {
                $table->dropColumn('name');
            }
        });
    }
};
