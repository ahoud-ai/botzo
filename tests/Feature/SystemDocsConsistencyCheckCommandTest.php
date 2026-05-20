<?php

namespace Tests\Feature;

use App\Services\System\DocsConsistencyService;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class SystemDocsConsistencyCheckCommandTest extends TestCase
{
    public function test_docs_consistency_check_writes_json_report_and_succeeds_when_consistent(): void
    {
        $reportPath = base_path('tmp/test-docs-consistency-report.json');
        File::delete($reportPath);

        $mock = Mockery::mock(DocsConsistencyService::class);
        $mock->shouldReceive('evaluate')->once()->andReturn([
            'status' => 'consistent',
            'score' => 100.0,
            'actual' => ['route_count' => 352],
            'documents' => [],
            'mismatches' => [],
            'warnings' => [],
            'timestamp' => now()->toISOString(),
        ]);
        $this->instance(DocsConsistencyService::class, $mock);

        $this->artisan('system:docs-consistency-check', [
            '--format' => 'json',
            '--out' => 'tmp/test-docs-consistency-report.json',
            '--strict' => true,
        ])->assertExitCode(0);

        $this->assertTrue(File::exists($reportPath));
        $payload = json_decode((string) File::get($reportPath), true);
        $this->assertSame('consistent', $payload['status'] ?? null);

        File::delete($reportPath);
    }

    public function test_docs_consistency_check_fails_in_strict_mode_when_mismatches_exist(): void
    {
        $mock = Mockery::mock(DocsConsistencyService::class);
        $mock->shouldReceive('evaluate')->once()->andReturn([
            'status' => 'drifted',
            'score' => 70.0,
            'actual' => ['route_count' => 352],
            'documents' => [],
            'mismatches' => ['Route snapshot mismatch'],
            'warnings' => [],
            'timestamp' => now()->toISOString(),
        ]);
        $this->instance(DocsConsistencyService::class, $mock);

        $this->artisan('system:docs-consistency-check', [
            '--format' => 'json',
            '--strict' => true,
        ])->assertExitCode(1);
    }
}
