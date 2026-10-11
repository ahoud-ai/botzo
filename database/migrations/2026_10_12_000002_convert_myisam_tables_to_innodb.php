<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Some production tables were created as MyISAM, which ignores transactions and
 * row locks (the AI usage counter and flow runs rely on both). Converts them to
 * InnoDB and records the previous engine so down() restores exactly what changed.
 *
 * ALTER ... ENGINE copies the table and blocks writes while it runs, so tables
 * above MAX_ROWS are skipped (with a log warning) to keep deploys from hanging;
 * convert those in a maintenance window instead.
 */
return new class extends Migration
{
    private const TABLES = ['automation_flows', 'automation_flow_runs', 'organization_ai_usage_counters'];

    private const TRACKING_TABLE = 'schema_engine_conversions';

    private const MAX_ROWS = 100000;

    public function up(): void
    {
        if (! $this->supportsEngines()) {
            return;
        }

        if (! Schema::hasTable(self::TRACKING_TABLE)) {
            Schema::create(self::TRACKING_TABLE, function (Blueprint $table) {
                $table->id();
                $table->string('table_name', 128)->unique();
                $table->string('previous_engine', 32);
                $table->timestamp('converted_at')->useCurrent();
            });
        }

        foreach (self::TABLES as $tableName) {
            $engine = $this->engine($tableName);

            if ($engine === null || strcasecmp($engine, 'InnoDB') === 0) {
                continue;
            }

            if (DB::table($tableName)->count() > self::MAX_ROWS) {
                Log::warning("Skipped converting {$tableName} to InnoDB: more than ".self::MAX_ROWS.' rows. Convert it in a maintenance window.');
                continue;
            }

            DB::statement("ALTER TABLE `{$tableName}` ENGINE=InnoDB");
            DB::table(self::TRACKING_TABLE)->updateOrInsert(
                ['table_name' => $tableName],
                ['previous_engine' => $engine, 'converted_at' => now()]
            );
        }
    }

    public function down(): void
    {
        if (! $this->supportsEngines() || ! Schema::hasTable(self::TRACKING_TABLE)) {
            return;
        }

        foreach (DB::table(self::TRACKING_TABLE)->get() as $conversion) {
            $engine = (string) $conversion->previous_engine;

            if (Schema::hasTable($conversion->table_name) && preg_match('/^[A-Za-z]+$/', $engine) === 1) {
                DB::statement("ALTER TABLE `{$conversion->table_name}` ENGINE={$engine}");
            }
        }

        Schema::drop(self::TRACKING_TABLE);
    }

    private function supportsEngines(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function engine(string $tableName): ?string
    {
        return DB::selectOne(
            'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$tableName]
        )?->engine;
    }
};
