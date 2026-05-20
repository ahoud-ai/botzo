<?php

namespace Tests\Feature;

use App\Http\Resources\AddonResource;
use App\Models\Addon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AddonResourceResolvedInputFieldsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_ai_assistant_uses_policy_fallback_when_metadata_has_no_input_fields(): void
    {
        $this->seedDateTimeFormatSettings();

        $addon = $this->createAddon([
            'name' => 'AI Assistant',
            'category' => 'ai',
            'logo' => 'ai.png',
            'metadata' => json_encode([
                'name' => 'IntelliReply',
            ]),
        ]);

        $data = (new AddonResource($addon->fresh()))->toArray(Request::create('/admin/settings/features', 'GET'));

        $this->assertArrayHasKey('resolved_input_fields', $data);
        $this->assertCount(3, $data['resolved_input_fields']);
        $this->assertSame(
            ['ai_global_api_key', 'ai_key_policy', 'ai_allow_org_override'],
            array_values(array_map(static fn($field) => $field['name'], $data['resolved_input_fields']))
        );
    }

    public function test_non_ai_addon_does_not_get_fallback_input_fields(): void
    {
        $this->seedDateTimeFormatSettings();

        $addon = $this->createAddon([
            'name' => 'Embedded Signup',
            'category' => 'chat',
            'logo' => 'whatsapp.png',
            'metadata' => json_encode([
                'name' => 'EmbeddedSignup',
            ]),
        ]);

        $data = (new AddonResource($addon->fresh()))->toArray(Request::create('/admin/settings/features', 'GET'));

        $this->assertArrayHasKey('resolved_input_fields', $data);
        $this->assertSame([], $data['resolved_input_fields']);
    }

    public function test_ai_assistant_keeps_existing_metadata_input_fields(): void
    {
        $this->seedDateTimeFormatSettings();

        $addon = $this->createAddon([
            'name' => 'AI Assistant',
            'category' => 'ai',
            'logo' => 'ai.png',
            'metadata' => json_encode([
                'name' => 'IntelliReply',
                'input_fields' => [
                    [
                        'element' => 'input',
                        'type' => 'text',
                        'name' => 'custom_ai_field',
                        'label' => __('Custom field'),
                        'class' => 'col-span-2',
                    ],
                ],
            ]),
        ]);

        $data = (new AddonResource($addon->fresh()))->toArray(Request::create('/admin/settings/features', 'GET'));

        $this->assertCount(1, $data['resolved_input_fields']);
        $this->assertSame('custom_ai_field', $data['resolved_input_fields'][0]['name']);
    }

    private function createAddon(array $attributes): Addon
    {
        return Addon::create(array_merge([
            'category' => 'general',
            'name' => 'Test Addon',
            'logo' => 'test.png',
            'description' => __('Test addon'),
            'metadata' => null,
            'status' => 1,
            'is_active' => 0,
            'is_plan_restricted' => 1,
        ], $attributes));
    }

    private function seedDateTimeFormatSettings(): void
    {
        DB::table('settings')->updateOrInsert(['key' => 'date_format'], ['value' => 'Y-m-d']);
        DB::table('settings')->updateOrInsert(['key' => 'time_format'], ['value' => 'H:i:s']);
    }
}
