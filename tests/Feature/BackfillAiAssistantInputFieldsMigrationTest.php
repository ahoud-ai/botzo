<?php

namespace Tests\Feature;

use App\Models\Addon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackfillAiAssistantInputFieldsMigrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_migration_backfills_ai_assistant_input_fields_when_missing(): void
    {
        $addon = $this->createAiAddon([
            'metadata' => json_encode(['name' => 'IntelliReply']),
        ]);

        DB::table('settings')->whereIn('key', ['ai_key_policy', 'ai_allow_org_override'])->delete();

        $migration = require base_path('database/migrations/2026_03_05_000000_backfill_ai_assistant_input_fields_if_missing.php');
        $migration->up();

        $metadata = json_decode(
            DB::table('addons')->where('id', $addon->id)->value('metadata'),
            true
        );

        $this->assertIsArray($metadata);
        $this->assertSame('IntelliReply', $metadata['name'] ?? null);
        $this->assertCount(3, $metadata['input_fields'] ?? []);
        $this->assertSame('ai_global_api_key', $metadata['input_fields'][0]['name'] ?? null);
        $this->assertSame('hybrid', DB::table('settings')->where('key', 'ai_key_policy')->value('value'));
        $this->assertSame('1', (string) DB::table('settings')->where('key', 'ai_allow_org_override')->value('value'));
    }

    public function test_migration_is_idempotent_and_does_not_override_existing_input_fields(): void
    {
        $addon = $this->createAiAddon([
            'metadata' => json_encode([
                'name' => 'PreviousName',
                'input_fields' => [
                    [
                        'element' => 'input',
                        'type' => 'text',
                        'name' => 'existing_field',
                        'label' => 'Existing',
                        'class' => 'col-span-2',
                    ],
                ],
            ]),
        ]);

        $migration = require base_path('database/migrations/2026_03_05_000000_backfill_ai_assistant_input_fields_if_missing.php');
        $migration->up();
        $migration->up();

        $metadata = json_decode(
            DB::table('addons')->where('id', $addon->id)->value('metadata'),
            true
        );

        $this->assertSame('IntelliReply', $metadata['name'] ?? null);
        $this->assertCount(1, $metadata['input_fields'] ?? []);
        $this->assertSame('existing_field', $metadata['input_fields'][0]['name'] ?? null);
    }

    private function createAiAddon(array $attributes): Addon
    {
        return Addon::create(array_merge([
            'category' => 'ai',
            'name' => 'AI Assistant',
            'logo' => 'ai.png',
            'description' => __('AI addon'),
            'metadata' => null,
            'status' => 1,
            'is_active' => 0,
            'is_plan_restricted' => 1,
        ], $attributes));
    }
}
