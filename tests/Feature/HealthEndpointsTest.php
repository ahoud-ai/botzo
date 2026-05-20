<?php

namespace Tests\Feature;

use App\Contracts\QueueProfileContract;
use App\Services\AutomationFlows\AutomationFlowAccessService;
use App\Services\System\RuntimeReadinessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\RebuildsTestingDatabase;
use Tests\TestCase;

class HealthEndpointsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_live_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/health/live');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'service',
                'timestamp',
            ])
            ->assertJson([
                'status' => 'ok',
            ]);
    }

    public function test_ready_health_endpoint_returns_ready_when_database_is_reachable(): void
    {
        $this->configureReadyRuntimeProfile();

        $response = $this->getJson('/health/ready');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('checks.database', 'up')
            ->assertJsonPath('checks.queue_profile', 'up')
            ->assertJsonPath('checks.cache', 'up')
            ->assertJsonPath('checks.session', 'configured')
            ->assertJsonPath('checks.workers', 'configured');
    }

    public function test_ready_health_endpoint_returns_not_ready_when_queue_profile_mismatches_runtime(): void
    {
        $this->configureReadyRuntimeProfile();
        config()->set('queue.default', 'database');
        $this->resetQueueProfileSingleton();

        $response = $this->getJson('/health/ready');

        $response
            ->assertStatus(503)
            ->assertJsonPath('status', 'not_ready')
            ->assertJsonPath('checks.queue_profile', 'mismatch');
    }

    public function test_ready_health_endpoint_returns_not_ready_when_cache_store_is_unconfigured(): void
    {
        $this->configureReadyRuntimeProfile();
        config()->set('queue_profile.shared.cache_store', 'missing-store');
        config()->set('cache.default', 'missing-store');
        $this->resetQueueProfileSingleton();

        $response = $this->getJson('/health/ready');

        $response
            ->assertStatus(503)
            ->assertJsonPath('status', 'not_ready')
            ->assertJsonPath('checks.queue_profile', 'up')
            ->assertJsonPath('checks.cache', 'down');
    }

    public function test_ready_health_endpoint_returns_not_ready_when_session_backend_is_not_ready(): void
    {
        $this->configureReadyRuntimeProfile();
        config()->set('queue_profile.shared.session_driver', 'database');
        config()->set('session.driver', 'database');
        config()->set('session.table', 'missing_sessions');
        $this->resetQueueProfileSingleton();

        $report = app(RuntimeReadinessService::class)->evaluate();

        $this->assertSame('not_ready', $report['status']);
        $this->assertSame('up', $report['checks']['queue_profile']);
        $this->assertSame('down', $report['checks']['session']);
    }

    public function test_ready_health_endpoint_returns_not_ready_when_workers_are_not_declared(): void
    {
        $this->configureReadyRuntimeProfile();
        config()->set('queue_profile.shared.workers', []);
        $this->resetQueueProfileSingleton();

        $response = $this->getJson('/health/ready');

        $response
            ->assertStatus(503)
            ->assertJsonPath('status', 'not_ready')
            ->assertJsonPath('checks.queue_profile', 'up')
            ->assertJsonPath('checks.workers', 'down');
    }

    public function test_ready_health_endpoint_returns_not_ready_when_automation_flow_schema_is_incomplete(): void
    {
        $this->configureReadyRuntimeProfile();
        $mock = \Mockery::mock(AutomationFlowAccessService::class);
        $mock->shouldReceive('runtimeEnabled')->andReturn(true);
        $mock->shouldReceive('readinessReport')->withNoArgs()->andReturn([
            'runtime_enabled' => true,
            'addon_enabled' => null,
            'base_schema_ready' => true,
            'builder_schema_ready' => false,
            'surface_ready' => true,
            'builder_ready' => false,
            'missing_base_tables' => [],
            'missing_builder_tables' => ['automation_flow_assets', 'automation_flow_node_secrets'],
            'missing_tables' => ['automation_flow_assets', 'automation_flow_node_secrets'],
            'message' => __('Flow Builder setup is incomplete. Run the latest migrations and try again.'),
        ]);
        $this->instance(AutomationFlowAccessService::class, $mock);

        $response = $this->getJson('/health/ready');

        $response
            ->assertStatus(503)
            ->assertJsonPath('status', 'not_ready')
            ->assertJsonPath('checks.automation_flows', 'down');
    }

    private function configureReadyRuntimeProfile(): void
    {
        config()->set('queue_profile.active', 'shared');
        config()->set('queue_profile.shared.connection', 'sync');
        config()->set('queue_profile.shared.cache_store', 'array');
        config()->set('queue_profile.shared.session_driver', 'array');
        config()->set('queue_profile.shared.queues', [
            'default',
            'campaign-messages',
            'webhook-media',
        ]);
        config()->set('queue_profile.shared.workers', [
            [
                'name' => 'test-worker',
                'queues' => [
                    'default',
                    'campaign-messages',
                    'webhook-media',
                ],
                'sleep' => 1,
                'tries' => 1,
                'timeout' => 60,
            ],
        ]);
        config()->set('queue.default', 'sync');
        config()->set('cache.default', 'array');
        config()->set('session.driver', 'array');

        $this->resetQueueProfileSingleton();
    }

    private function resetQueueProfileSingleton(): void
    {
        $this->app->forgetInstance(QueueProfileContract::class);
    }
}

