<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organizations')) {
            DB::table('organizations')
                ->select(['id', 'metadata'])
                ->whereNotNull('metadata')
                ->orderBy('id')
                ->chunkById(100, function ($rows): void {
                    foreach ($rows as $row) {
                        $metadata = json_decode($row->metadata ?? '', true);
                        if (!is_array($metadata)) {
                            continue;
                        }

                        $sequence = collect((array) data_get($metadata, 'automation.response_sequence', []))
                            ->filter(fn ($item) => in_array($item, ['Basic Replies', 'AI Reply Assistant'], true))
                            ->values()
                            ->all();

                        if (!in_array('Basic Replies', $sequence, true)) {
                            array_unshift($sequence, 'Basic Replies');
                        }

                        data_set($metadata, 'automation.response_sequence', array_values(array_unique($sequence)));

                        DB::table('organizations')
                            ->where('id', $row->id)
                            ->update(['metadata' => json_encode($metadata)]);
                    }
                });
        }

        if (Schema::hasTable('addons')) {
            DB::table('addons')
                ->where('name', 'Flow builder')
                ->update([
                    'is_active' => 0,
                    'metadata' => json_encode([
                        'name' => 'FlowBuilder',
                        'decommissioned' => true,
                        'internal_only' => true,
                    ]),
                ]);
        }

        Schema::disableForeignKeyConstraints();

        try {
            foreach ([
                'flow_execution_steps',
                'flow_execution_events',
                'flow_executions',
                'flow_versions',
                'flow_template_installs',
                'flow_templates',
                'flow_ux_events',
                'flow_conversation_states',
                'flow_channel_support',
                'flow_definitions',
            ] as $table) {
                Schema::dropIfExists($table);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        // Destructive reset: Flow Builder is intentionally removed and rebuilt later as a new module.
    }
};
