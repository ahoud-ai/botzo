<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->refineSettings();
        $this->refineCoupons();
        $this->refineTemplates();
        $this->refineJsonMetadata('campaigns');
        $this->refineJsonMetadata('chats');
    }

    public function down(): void
    {
        //
    }

    private function refineSettings(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->whereIn('key', $this->retiredSettingKeys())->delete();
    }

    private function refineCoupons(): void
    {
        if (! Schema::hasTable('coupons')) {
            return;
        }

        $columns = array_values(array_filter(
            ['name', 'code'],
            fn (string $column): bool => Schema::hasColumn('coupons', $column),
        ));

        if ($columns === []) {
            return;
        }

        DB::table('coupons')
            ->where(function ($query) use ($columns): void {
                foreach ($columns as $column) {
                    foreach ($this->retiredTextMarkers() as $marker) {
                        $query->orWhere($column, 'like', '%'.$marker.'%');
                    }
                }
            })
            ->delete();
    }

    private function refineTemplates(): void
    {
        if (! Schema::hasTable('templates')) {
            return;
        }

        $columns = array_values(array_filter(
            ['name', 'metadata'],
            fn (string $column): bool => Schema::hasColumn('templates', $column),
        ));

        if ($columns === []) {
            return;
        }

        DB::table('templates')
            ->where(function ($query) use ($columns): void {
                foreach ($columns as $column) {
                    foreach ($this->retiredTextMarkers() as $marker) {
                        $query->orWhere($column, 'like', '%'.$marker.'%');
                    }
                }
            })
            ->delete();
    }

    private function refineJsonMetadata(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'metadata')) {
            return;
        }

        DB::table($table)
            ->select(['id', 'metadata'])
            ->where(function ($query): void {
                foreach ($this->retiredTextMarkers() as $marker) {
                    $query->orWhere('metadata', 'like', '%'.$marker.'%');
                }
            })
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    $metadata = $this->cleanMetadataValue($row->metadata);

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['metadata' => $metadata]);
                }
            });
    }

    private function cleanMetadataValue(mixed $value): ?string
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

    private function retiredSettingKeys(): array
    {
        return [
            'premium_home_footer_'.'meta_logo',
        ];
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
