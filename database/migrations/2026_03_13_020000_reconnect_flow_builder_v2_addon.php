<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('addons')) {
            return;
        }

        $addon = DB::table('addons')->where('name', 'Flow builder')->first();
        if (!$addon) {
            return;
        }

        $metadata = json_decode($addon->metadata ?? '', true);
        $metadata = is_array($metadata) ? $metadata : [];

        unset($metadata['decommissioned'], $metadata['internal_only']);
        $metadata['name'] = $metadata['name'] ?? 'FlowBuilder';

        DB::table('addons')
            ->where('id', $addon->id)
            ->update([
                'description' => __('Build WhatsApp qualification journeys with CRM updates and visual customer paths.'),
                'metadata' => json_encode($metadata),
                'is_plan_restricted' => 1,
                'update_available' => 0,
            ]);
    }

    public function down(): void
    {
        // No rollback: the product now treats Flow Builder v2 as the only supported version.
    }
};
