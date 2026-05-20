<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const RETIRED_TABLES = [
        'mobile_push_devices',
        'mobile_message_idempotencies',
    ];

    private const RETIRED_SETTING_KEYS = [
        'mobile_app_enabled',
        'mobile_push_enabled',
        'firebase_credentials',
        'firebase_project_id',
        'fcm_server_key',
        'fcm_sender_id',
    ];

    private const RETIRED_SETTING_PATTERNS = [
        'mobile\_%',
        'firebase\_%',
        'fcm\_%',
    ];

    private const RETIRED_METADATA_KEYS = [
        'mobile',
        'mobile_app',
        'mobile_push',
        'firebase',
        'firebase_credentials',
        'firebase_project_id',
        'fcm',
        'push_notifications',
    ];

    private const RETIRED_METADATA_PREFIXES = [
        'mobile_',
        'firebase_',
        'fcm_',
    ];

    public function up(): void
    {
        $this->refineTables();
        $this->refineSettings();
        $this->refineAddonsAndPermissions();
        $this->refineSubscriptionPlans();
    }

    public function down(): void
    {
        //
    }

    private function refineTables(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (self::RETIRED_TABLES as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    private function refineSettings(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->whereIn('key', self::RETIRED_SETTING_KEYS)->delete();

        foreach (self::RETIRED_SETTING_PATTERNS as $pattern) {
            DB::table('settings')->where('key', 'like', $pattern)->delete();
        }

        $this->cleanJsonSetting('trial_addons');
        $this->cleanJsonSetting('socials');
    }

    private function refineAddonsAndPermissions(): void
    {
        if (Schema::hasTable('addons')) {
            DB::table('addons')
                ->whereIn('name', [
                    'Mobile App',
                    'Mobile Push',
                    'Firebase',
                    'Firebase Push',
                    'FCM',
                ])
                ->delete();
        }

        if (Schema::hasTable('modules')) {
            DB::table('modules')
                ->whereIn('name', ['mobile', 'mobile_app', 'firebase', 'fcm'])
                ->delete();
        }

        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')
                ->whereIn('module', ['mobile', 'mobile_app', 'firebase', 'fcm'])
                ->delete();
        }

        if (! Schema::hasTable('organization_roles') || ! Schema::hasColumn('organization_roles', 'permissions')) {
            return;
        }

        DB::table('organization_roles')
            ->select(['id', 'permissions'])
            ->orderBy('id')
            ->chunkById(100, function ($roles): void {
                foreach ($roles as $role) {
                    $permissions = json_decode((string) $role->permissions, true);
                    if (! is_array($permissions)) {
                        continue;
                    }

                    $filtered = array_values(array_filter($permissions, function ($permission): bool {
                        $permission = strtolower((string) $permission);

                        foreach (['mobile.', 'mobile_app.', 'firebase.', 'fcm.'] as $prefix) {
                            if (str_starts_with($permission, $prefix)) {
                                return false;
                            }
                        }

                        return true;
                    }));

                    if ($filtered !== $permissions) {
                        DB::table('organization_roles')
                            ->where('id', $role->id)
                            ->update(['permissions' => json_encode($filtered)]);
                    }
                }
            });
    }

    private function refineSubscriptionPlans(): void
    {
        if (! Schema::hasTable('subscription_plans') || ! Schema::hasColumn('subscription_plans', 'metadata')) {
            return;
        }

        DB::table('subscription_plans')
            ->select(['id', 'metadata'])
            ->orderBy('id')
            ->chunkById(100, function ($plans): void {
                foreach ($plans as $plan) {
                    $metadata = json_decode((string) $plan->metadata, true);
                    if (! is_array($metadata)) {
                        $metadata = [];
                    }

                    DB::table('subscription_plans')
                        ->where('id', $plan->id)
                        ->update(['metadata' => json_encode($this->cleanRetiredPayload($metadata))]);
                }
            });
    }

    private function cleanJsonSetting(string $key): void
    {
        $value = DB::table('settings')->where('key', $key)->value('value');
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            return;
        }

        DB::table('settings')
            ->where('key', $key)
            ->update(['value' => json_encode($this->cleanRetiredPayload($decoded))]);
    }

    private function cleanRetiredPayload(array $payload): array
    {
        foreach (array_keys($payload) as $key) {
            if ($this->isRetiredPayloadKey((string) $key)) {
                unset($payload[$key]);
            }
        }

        if (isset($payload['addons']) && is_array($payload['addons'])) {
            foreach ([
                'Mobile App',
                'Mobile Push',
                'Firebase',
                'Firebase Push',
                'FCM',
            ] as $addon) {
                unset($payload['addons'][$addon]);
            }
        }

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->cleanRetiredPayload($value);
            }
        }

        return $payload;
    }

    private function isRetiredPayloadKey(string $key): bool
    {
        if (in_array($key, self::RETIRED_METADATA_KEYS, true)) {
            return true;
        }

        foreach (self::RETIRED_METADATA_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }
};
