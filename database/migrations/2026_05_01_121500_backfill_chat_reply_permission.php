<?php

use App\Support\OrganizationPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $replyPresetNames = [
            'Executive Management',
            'Operations Manager',
            'Customer Support Supervisor',
            'Reply Employee',
        ];

        DB::table('organization_roles')
            ->select(['id', 'name', 'permissions'])
            ->orderBy('id')
            ->chunkById(100, function ($roles) use ($replyPresetNames) {
                foreach ($roles as $role) {
                    $permissions = json_decode($role->permissions ?? '[]', true);
                    if (! is_array($permissions)) {
                        $permissions = [];
                    }

                    $shouldGrantReply = in_array($role->name, $replyPresetNames, true)
                        || in_array('chats.send_message', $permissions, true);

                    if ($shouldGrantReply && ! in_array('chats.reply', $permissions, true)) {
                        $permissions[] = 'chats.reply';
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
        // Keeping explicit reply grants is safer than silently removing a production capability.
    }
};
