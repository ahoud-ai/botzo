<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $addon = DB::table('addons')->where('name', 'Flow builder')->first();
        if (!$addon) {
            return;
        }

        $metadata = [];
        if (is_string($addon->metadata) && trim($addon->metadata) !== '') {
            $decoded = json_decode($addon->metadata, true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        if (!isset($metadata['name']) || trim((string) $metadata['name']) === '') {
            $metadata['name'] = 'FlowBuilder';
        }

        DB::table('addons')
            ->where('id', $addon->id)
            ->update([
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                'status' => 1,
                'is_active' => (int) ($addon->is_active ?? 0),
                'is_plan_restricted' => 1,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive by design.
    }
};

