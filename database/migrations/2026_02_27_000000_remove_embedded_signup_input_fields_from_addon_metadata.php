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
        $addon = DB::table('addons')->where('name', 'Embedded Signup')->first();

        if (!$addon || empty($addon->metadata)) {
            return;
        }

        $metadata = json_decode($addon->metadata, true);
        if (!is_array($metadata)) {
            return;
        }

        if (array_key_exists('input_fields', $metadata)) {
            unset($metadata['input_fields']);
            DB::table('addons')
                ->where('id', $addon->id)
                ->update(['metadata' => json_encode($metadata)]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: historical input field definitions should not be restored.
    }
};
