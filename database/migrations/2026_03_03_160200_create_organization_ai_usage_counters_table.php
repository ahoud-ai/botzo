<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('organization_ai_usage_counters')) {
            $this->ensureIndexes();
            return;
        }

        Schema::create('organization_ai_usage_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->unsignedBigInteger('text_count')->default(0);
            $table->unsignedBigInteger('audio_count')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'subscription_id'], 'org_ai_usage_org_sub_idx');
            $table->unique(
                ['organization_id', 'subscription_id', 'period_start', 'period_end'],
                'org_ai_usage_period_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_ai_usage_counters');
    }

    private function ensureIndexes(): void
    {
        if (!$this->indexExists('organization_ai_usage_counters', 'org_ai_usage_org_sub_idx')) {
            Schema::table('organization_ai_usage_counters', function (Blueprint $table) {
                $table->index(['organization_id', 'subscription_id'], 'org_ai_usage_org_sub_idx');
            });
        }

        if (!$this->indexExists('organization_ai_usage_counters', 'org_ai_usage_period_unique')) {
            Schema::table('organization_ai_usage_counters', function (Blueprint $table) {
                $table->unique(
                    ['organization_id', 'subscription_id', 'period_start', 'period_end'],
                    'org_ai_usage_period_unique'
                );
            });
        }
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $results = DB::select('SHOW INDEX FROM '.$tableName.' WHERE Key_name = ?', [$indexName]);
            return !empty($results);
        }

        if ($driver === 'sqlite') {
            $results = DB::select("PRAGMA index_list('".$tableName."')");
            foreach ($results as $row) {
                if (($row->name ?? null) === $indexName) {
                    return true;
                }
            }
            return false;
        }

        return false;
    }
};
