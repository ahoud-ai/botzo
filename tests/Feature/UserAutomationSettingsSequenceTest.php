<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Services\AddonStateService;
use App\Services\AutomationFlows\AutomationFlowAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class UserAutomationSettingsSequenceTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_settings_automation_exposes_flow_builder_in_sequence_options_when_enabled(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->mockAutomationSequenceGates($organization->id, true, true);

        $response = $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/settings/automation');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/Settings/Automation')
            ->where('automationSequenceOptions', ['Automation Flows', 'Basic Replies', 'AI Reply Assistant'])
        );
    }

    public function test_settings_automation_persists_flow_builder_in_response_sequence(): void
    {
        [$user, $organization] = $this->createOwnerContext();
        $this->mockAutomationSequenceGates($organization->id, true, true);

        $this->withoutMiddleware()
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/settings/automation', [
                'response_sequence' => ['AI Reply Assistant', 'Automation Flows', 'Basic Replies'],
            ])
            ->assertOk();

        $savedOrganization = Organization::findOrFail($organization->id);
        $metadata = $savedOrganization->metadata ? json_decode($savedOrganization->metadata, true) : [];

        $this->assertSame(
            ['AI Reply Assistant', 'Automation Flows', 'Basic Replies'],
            data_get($metadata, 'automation.response_sequence')
        );
    }

    private function mockAutomationSequenceGates(int $organizationId, bool $aiEnabled, bool $flowEnabled): void
    {
        $addonStateMock = Mockery::mock(AddonStateService::class);
        $addonStateMock
            ->shouldReceive('isModuleEnabledForOrganization')
            ->with('AI Assistant', $organizationId)
            ->andReturn($aiEnabled);
        $this->instance(AddonStateService::class, $addonStateMock);

        $flowAccessMock = Mockery::mock(AutomationFlowAccessService::class);
        $flowAccessMock
            ->shouldReceive('availableForOrganization')
            ->with($organizationId)
            ->andReturn($flowEnabled);
        $this->instance(AutomationFlowAccessService::class, $flowAccessMock);
    }
}
