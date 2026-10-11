<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Runs real ALTER TABLE statements, so it cannot use DatabaseTransactions.
 * Uses organization_ai_usage_counters because, on a fresh InnoDB install, the flow
 * tables carry foreign keys and MySQL refuses to turn them into MyISAM for the test.
 * (Production MyISAM tables never stored those foreign keys, so converting them works.)
 * The table is always restored to InnoDB and the migration re-applied.
 */
class ConvertMyisamTablesToInnodbMigrationTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_10_12_000002_convert_myisam_tables_to_innodb.php';

    protected function tearDown(): void
    {
        DB::statement('ALTER TABLE `organization_ai_usage_counters` ENGINE=InnoDB');

        $migration = require base_path(self::MIGRATION);
        $migration->up();

        parent::tearDown();
    }

    public function test_up_converts_myisam_tables_and_down_restores_only_what_it_converted(): void
    {
        $migration = require base_path(self::MIGRATION);
        $migration->down();

        DB::statement('ALTER TABLE `organization_ai_usage_counters` ENGINE=MyISAM');
        $this->assertSame('MyISAM', $this->engine('organization_ai_usage_counters'));

        $migration->up();

        $this->assertSame('InnoDB', $this->engine('organization_ai_usage_counters'));
        $this->assertSame('InnoDB', $this->engine('automation_flow_runs'), 'Tables that were already InnoDB stay InnoDB.');
        $this->assertSame('MyISAM', DB::table('schema_engine_conversions')->where('table_name', 'organization_ai_usage_counters')->value('previous_engine'));
        $this->assertFalse(DB::table('schema_engine_conversions')->where('table_name', 'automation_flow_runs')->exists());

        $migration->down();

        $this->assertSame('MyISAM', $this->engine('organization_ai_usage_counters'));
        $this->assertSame('InnoDB', $this->engine('automation_flow_runs'), 'down() never touches tables it did not convert.');
        $this->assertFalse(Schema::hasTable('schema_engine_conversions'));
    }

    public function test_up_is_safe_to_run_twice(): void
    {
        $migration = require base_path(self::MIGRATION);

        $migration->up();
        $migration->up();

        $this->assertSame('InnoDB', $this->engine('organization_ai_usage_counters'));
        $this->assertSame('InnoDB', $this->engine('automation_flows'));
    }

    private function engine(string $table): ?string
    {
        return DB::selectOne(
            'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        )?->engine;
    }
}
