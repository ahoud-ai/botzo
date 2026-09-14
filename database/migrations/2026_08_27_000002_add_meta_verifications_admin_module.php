<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MODULES = [
        'meta_verifications' => ['view', 'advance', 'reject'],
    ];

    public function up(): void
    {
        $this->upsertModules();
    }

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
