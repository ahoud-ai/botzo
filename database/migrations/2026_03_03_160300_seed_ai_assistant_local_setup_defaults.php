<?php

use App\Models\Addon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $addon = Addon::where('name', 'AI Assistant')->first();
        if ($addon) {
            $metadata = $addon->metadata ? json_decode($addon->metadata, true) : [];
            $metadata = is_array($metadata) ? $metadata : [];
            $metadata = array_merge($metadata, [
                'name' => 'IntelliReply',
                'input_fields' => [
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
                ],
            ]);

            $addon->update([
                'metadata' => json_encode($metadata),
            ]);
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
        // Keep defaults and metadata in place to avoid breaking existing AI addon setup.
    }
};

