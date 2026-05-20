<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_system_owner')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('is_system_owner')->default(false)->after('role')->index();
            });
        }

        $hasSystemOwner = DB::table('users')
            ->whereNull('deleted_at')
            ->where('is_system_owner', true)
            ->exists();

        if (! $hasSystemOwner) {
            $primaryAdmin = DB::table('users')
                ->whereNull('deleted_at')
                ->whereIn(DB::raw('LOWER(role)'), ['admin', 'owner'])
                ->orderBy('created_at')
                ->orderBy('id')
                ->first(['id']);

            if ($primaryAdmin) {
                DB::table('users')
                    ->where('id', $primaryAdmin->id)
                    ->update([
                        'is_system_owner' => true,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'is_system_owner')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex(['is_system_owner']);
                $table->dropColumn('is_system_owner');
            });
        }
    }
};
