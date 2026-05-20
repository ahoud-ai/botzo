<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MODULES = [
        'addons' => ['view', 'create', 'edit', 'delete'],
        'languages' => ['view', 'create', 'edit', 'delete'],
        'logs' => ['view'],
    ];

    private const PREVIOUS_PERMISSION_BACKFILL = [
        'settings.general' => [
            'addons' => ['view', 'create', 'edit', 'delete'],
            'languages' => ['view', 'create', 'edit', 'delete'],
        ],
        'customers.view' => [
            'logs' => ['view'],
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->upsertModules();
        $this->backfillPreviousRolePermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive by design.
    }

    private function upsertModules(): void
    {
        foreach (self::MODULES as $moduleName => $actions) {
            $normalizedActions = $this->normalizeActions($actions);

            $row = DB::table('modules')
                ->where('name', $moduleName)
                ->first();

            if (! $row) {
                DB::table('modules')->insert([
                    'name' => $moduleName,
                    'actions' => implode(', ', $normalizedActions),
                ]);

                continue;
            }

            $existingActions = $this->normalizeActions(
                explode(',', (string) ($row->actions ?? ''))
            );

            $merged = $existingActions;
            foreach ($normalizedActions as $action) {
                if (! in_array($action, $merged, true)) {
                    $merged[] = $action;
                }
            }

            if ($merged !== $existingActions) {
                DB::table('modules')
                    ->where('id', $row->id)
                    ->update([
                        'actions' => implode(', ', $merged),
                    ]);
            }
        }
    }

    private function backfillPreviousRolePermissions(): void
    {
        foreach (self::PREVIOUS_PERMISSION_BACKFILL as $previousPermission => $targets) {
            [$previousModule, $previousAction] = array_pad(explode('.', $previousPermission, 2), 2, null);

            if (! is_string($previousModule) || ! is_string($previousAction)) {
                continue;
            }

            $roleIds = DB::table('role_permissions')
                ->where('module', $previousModule)
                ->where('action', $previousAction)
                ->pluck('role_id')
                ->map(static fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($roleIds->isEmpty()) {
                continue;
            }

            foreach ($roleIds as $roleId) {
                foreach ($targets as $targetModule => $targetActions) {
                    foreach ($this->normalizeActions($targetActions) as $targetAction) {
                        $exists = DB::table('role_permissions')
                            ->where('role_id', $roleId)
                            ->where('module', $targetModule)
                            ->where('action', $targetAction)
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        DB::table('role_permissions')->insert([
                            'role_id' => $roleId,
                            'module' => $targetModule,
                            'action' => $targetAction,
                        ]);
                    }
                }
            }
        }
    }

    private function normalizeActions(array $actions): array
    {
        $normalized = [];

        foreach ($actions as $action) {
            $value = strtolower(trim((string) $action));
            if ($value === '') {
                continue;
            }

            if (! in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }
};
