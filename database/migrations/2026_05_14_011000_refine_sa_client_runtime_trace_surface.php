<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            ['automation_flow_run_steps', 'input_json'],
            ['automation_flow_run_steps', 'output_json'],
            ['automation_flow_runs', 'metadata'],
        ] as [$table, $column]) {
            $this->refineJsonColumn($table, $column);
        }
    }

    public function down(): void
    {
        //
    }

    private function refineJsonColumn(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->select(['id', $column])
            ->where(function ($query) use ($column): void {
                foreach ($this->retiredTextMarkers() as $marker) {
                    $query->orWhere($column, 'like', '%'.$marker.'%');
                }
            })
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($table, $column): void {
                foreach ($rows as $row) {
                    DB::table($table)
                        ->where('id', $row->id)
                        ->update([$column => $this->cleanJsonValue($row->{$column})]);
                }
            });
    }

    private function cleanJsonValue(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            return null;
        }

        $cleaned = $this->cleanArray($decoded);

        return $cleaned === [] ? null : json_encode($cleaned);
    }

    private function cleanArray(array $payload): array
    {
        $cleaned = [];

        foreach ($payload as $key => $value) {
            if ($this->containsRetiredMarker((string) $key)) {
                continue;
            }

            if (is_array($value)) {
                $nested = $this->cleanArray($value);
                if ($nested !== []) {
                    $cleaned[$key] = $nested;
                }

                continue;
            }

            if (is_scalar($value) && $this->containsRetiredMarker((string) $value)) {
                continue;
            }

            $cleaned[$key] = $value;
        }

        return $cleaned;
    }

    private function containsRetiredMarker(string $value): bool
    {
        foreach ($this->retiredTextMarkers() as $marker) {
            if (stripos($value, $marker) !== false) {
                return true;
            }
        }

        return false;
    }

    private function retiredTextMarkers(): array
    {
        return [
            'Na'.'wait',
            'na'.'wait',
            'Swift'.'chats',
            'swift'.'chats',
        ];
    }
};
