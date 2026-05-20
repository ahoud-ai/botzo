<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\AutomationFlow;
use App\Models\AutomationFlowAsset;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class AutomationFlowAssetAccessTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('automation_flows.enabled', true);
        Storage::fake('local');
    }

    public function test_signed_asset_url_returns_the_asset_payload(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($organization->id, $user->id);
        $asset = $this->createAsset($flow, $user->id);

        $url = URL::temporarySignedRoute('flowbuilder.assets.show', now()->addMinutes(30), [
            'uuid' => $flow->uuid,
            'assetUuid' => $asset->uuid,
        ]);

        $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->assertSeeText('asset-payload');
    }

    public function test_unsigned_asset_url_is_rejected(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->enableFlowBuilderForOrganization($organization->id);
        $flow = $this->createFlow($organization->id, $user->id);
        $asset = $this->createAsset($flow, $user->id);

        $this->get("/automation/flows/{$flow->uuid}/assets/{$asset->uuid}")
            ->assertForbidden();
    }

    private function enableFlowBuilderForOrganization(int $organizationId): void
    {
        Addon::updateOrCreate(
            ['name' => 'Flow builder'],
            [
                'uuid' => Addon::query()->where('name', 'Flow builder')->value('uuid') ?: (string) Str::uuid(),
                'category' => 'automation',
                'logo' => 'flow_builder.png',
                'description' => __('Flow Builder v2'),
                'metadata' => json_encode(['name' => 'FlowBuilder']),
                'status' => 1,
                'is_active' => 1,
                'is_plan_restricted' => 1,
            ]
        );

        $this->createActiveSubscription($organizationId, [
            'addons' => [
                'Flow builder' => true,
            ],
        ]);
    }

    private function createFlow(int $organizationId, int $userId): AutomationFlow
    {
        return AutomationFlow::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'name' => 'Asset flow',
            'description' => __('Asset route coverage flow.'),
            'goal_preset' => 'sales_qualification',
            'channel' => 'whatsapp',
            'trigger_type' => 'incoming_whatsapp_message',
            'status' => 'draft',
            'graph_json' => [],
            'ui_json' => [],
            'created_by' => $userId,
            'updated_by' => $userId,
            'has_unpublished_changes' => true,
        ]);
    }

    private function createAsset(AutomationFlow $flow, int $userId): AutomationFlowAsset
    {
        $path = sprintf('automation-flows/%d/%s/demo.txt', $flow->organization_id, $flow->uuid);
        Storage::disk('local')->put($path, 'asset-payload');

        return AutomationFlowAsset::create([
            'uuid' => (string) Str::uuid(),
            'automation_flow_id' => $flow->id,
            'organization_id' => $flow->organization_id,
            'media_kind' => 'document',
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'demo.txt',
            'mime_type' => 'text/plain',
            'size' => 13,
            'created_by' => $userId,
        ]);
    }
}
