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
        $addons = DB::table('addons')
            ->where('name', 'AI Assistant')
            ->select(['id', 'metadata'])
            ->get();

        foreach ($addons as $addon) {
            $metadata = json_decode($addon->metadata ?? '', true);
            $metadata = is_array($metadata) ? $metadata : [];

            $updated = false;

            if (($metadata['name'] ?? null) !== 'IntelliReply') {
                $metadata['name'] = 'IntelliReply';
                $updated = true;
            }

            $inputFields = $metadata['input_fields'] ?? null;
            if (!is_array($inputFields) || count($inputFields) === 0) {
                $metadata['input_fields'] = $this->defaultAiInputFields();
                $updated = true;
            }

            if ($updated) {
                DB::table('addons')
                    ->where('id', $addon->id)
                    ->update(['metadata' => json_encode($metadata)]);
            }
        }

        DB::table('settings')->updateOrInsert(
            ['key' => 'ai_key_policy'],
            ['value' => 'hybrid']
        );

        DB::table('settings')->updateOrInsert(
            ['key' => 'ai_allow_org_override'],
            ['value' => 1]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep backfilled AI setup metadata in place to avoid regressions.
    }

    private function defaultAiInputFields(): array
    {
        return [
            [
                'element' => 'input',
                'type' => 'password',
                'name' => 'ai_global_api_key',
                'label' => __('Global OpenAI API Key'),
                'class' => 'col-span-2',
            ],
            [
                'element' => 'select',
                'type' => 'text',
                'name' => 'ai_key_policy',
                'label' => __('AI key policy'),
                'class' => 'col-span-2',
                'options' => [
                    ['value' => 'hybrid', 'label' => 'Hybrid'],
                    ['value' => 'global_only', 'label' => __('Global only')],
                    ['value' => 'organization_only', 'label' => __('Organization only')],
                ],
            ],
            [
                'element' => 'toggle',
                'type' => 'checkbox',
                'name' => 'ai_allow_org_override',
                'label' => __('Allow organization API key override'),
                'class' => 'col-span-2',
            ],
        ];
    }
};
