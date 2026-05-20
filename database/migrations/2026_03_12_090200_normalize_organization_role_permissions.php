<?php

use App\Support\OrganizationPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('organization_roles')
            ->select(['id', 'permissions'])
            ->orderBy('id')
            ->chunkById(100, function ($roles) {
                foreach ($roles as $role) {
                    $permissions = json_decode($role->permissions ?? '[]', true);
                    if (!is_array($permissions)) {
                        $permissions = [];
                    }

                    $normalized = OrganizationPermissions::normalizePermissions($permissions);

                    if ($normalized === $permissions) {
                        continue;
                    }

                    DB::table('organization_roles')
                        ->where('id', $role->id)
                        ->update([
                            'permissions' => json_encode($normalized),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Canonical permission names are safe to keep on rollback.
    }
};
