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
        if (!Schema::hasColumn('organization_api_keys', 'token_last_four')) {
            Schema::table('organization_api_keys', function (Blueprint $table) {
                $table->string('token_last_four', 4)->nullable()->after('token');
            });
        }

        $hasher = app(OrganizationApiTokenHasher::class);

        DB::table('organization_api_keys')
            ->select(['id', 'token', 'token_last_four'])
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($hasher) {
                foreach ($rows as $row) {
                    if (!is_string($row->token) || $row->token === '') {
                        continue;
                    }

                    if ($hasher->looksHashed($row->token)) {
                        continue;
                    }

                    DB::table('organization_api_keys')
                        ->where('id', $row->id)
                        ->update([
                            'token' => $hasher->hash($row->token),
                            'token_last_four' => $hasher->lastFour($row->token),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('organization_api_keys', 'token_last_four')) {
            Schema::table('organization_api_keys', function (Blueprint $table) {
                $table->dropColumn('token_last_four');
            });
        }
    }
};
