<?php

namespace Tests\Unit;

use App\Contracts\QueueProfileContract;
use App\Services\System\DocsConsistencyService;
use App\Services\System\ReadinessAssessmentService;
use App\Services\System\RuntimeReadinessService;
use Mockery;
use Tests\TestCase;

class ReadinessAssessmentServiceTest extends TestCase
{
    public function test_parse_test_summary_prefers_terminal_suite_summary_line(): void
    {
        $service = $this->makeService();
        $method = new \ReflectionMethod($service, 'parseTestSummary');
        $method->setAccessible(true);

        $output = implode(PHP_EOL, [
            'Some nested command output: Tests: 49 failed, 242 passed (1692 assertions)',
            'Other logs',
            '  Tests:    291 passed (2029 assertions)',
        ]);

        /** @var array{failed:int,passed:int,assertions:int} $summary */
        $summary = $method->invoke($service, $output);

        $this->assertSame(0, $summary['failed']);
        $this->assertSame(291, $summary['passed']);
        $this->assertSame(2029, $summary['assertions']);
    }

    public function test_scalability_uses_readiness_profile_when_available(): void
    {
        config()->set('queue_profile.readiness_profile', 'production');
        config()->set('queue_profile.production', [
            'connection' => 'redis',
            'cache_store' => 'redis',
            'session_driver' => 'redis',
            'workers' => [
                ['name' => 'default-worker', 'queues' => ['default']],
                ['name' => 'campaign-worker', 'queues' => ['campaign-messages']],
                ['name' => 'media-worker', 'queues' => ['webhook-media']],
            ],
        ]);

        $runtimeReadiness = Mockery::mock(RuntimeReadinessService::class);
        $runtimeReadiness->shouldReceive('evaluate')->once()->andReturn([
            'status' => 'ready',
            'profile' => 'shared',
            'checks' => [],
        ]);

        $queueProfile = Mockery::mock(QueueProfileContract::class);
        $queueProfile->shouldReceive('getName')->atLeast()->once()->andReturn('shared');
        $queueProfile->shouldReceive('getConnection')->zeroOrMoreTimes()->andReturn('database');
        $queueProfile->shouldReceive('getCacheStore')->zeroOrMoreTimes()->andReturn('file');
        $queueProfile->shouldReceive('getSessionDriver')->zeroOrMoreTimes()->andReturn('file');
        $queueProfile->shouldReceive('getWorkers')->zeroOrMoreTimes()->andReturn([
            ['name' => 'shared-cron-worker', 'queues' => ['default']],
        ]);

        $docs = Mockery::mock(DocsConsistencyService::class);
        $service = new ReadinessAssessmentService($runtimeReadiness, $queueProfile, $docs);
        $method = new \ReflectionMethod($service, 'evaluateScalability');
        $method->setAccessible(true);

        /** @var array{score:float,details:array<string,mixed>} $axis */
        $axis = $method->invoke($service);

        $this->assertSame(100.0, $axis['score']);
        $this->assertSame('production', $axis['details']['profile']);
        $this->assertSame('shared', $axis['details']['active_profile']);
        $this->assertSame('readiness_profile', $axis['details']['profile_source']);
    }

    public function test_route_contracts_accept_allowed_environment_route_counts(): void
    {
        $actualCount = app('router')->getRoutes()->count();
        config()->set('architecture.route_snapshot.total_count', $actualCount + 1);
        config()->set('architecture.route_snapshot.allowed_total_counts', [$actualCount]);
        config()->set('architecture.route_snapshot.critical_routes', []);

        $service = $this->makeService();
        $method = new \ReflectionMethod($service, 'evaluateRouteContracts');
        $method->setAccessible(true);

        /** @var array{score:float,status:string,details:array<string,mixed>} $axis */
        $axis = $method->invoke($service);

        $this->assertSame(100.0, $axis['score']);
        $this->assertSame('pass', $axis['status']);
        $this->assertContains($actualCount, $axis['details']['allowed_route_counts']);
    }

    private function makeService(): ReadinessAssessmentService
    {
        $runtimeReadiness = Mockery::mock(RuntimeReadinessService::class);
        $queueProfile = Mockery::mock(QueueProfileContract::class);
        $docs = Mockery::mock(DocsConsistencyService::class);

        return new ReadinessAssessmentService($runtimeReadiness, $queueProfile, $docs);
    }
}
