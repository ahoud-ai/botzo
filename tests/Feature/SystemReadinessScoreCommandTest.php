<?php

namespace Tests\Feature;

use App\Services\System\ReadinessAssessmentService;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class SystemReadinessScoreCommandTest extends TestCase
{
    public function test_readiness_score_command_writes_json_report_and_passes_strict_gate_on_full_score(): void
    {
        $reportPath = base_path('tmp/test-readiness-score-report.json');
        File::delete($reportPath);

        $mock = Mockery::mock(ReadinessAssessmentService::class);
        $mock->shouldReceive('assess')->once()->andReturn([
            'status' => 'strong',
            'overall_score' => 100.0,
            'axes' => [
                'system_health' => ['score' => 100.0, 'status' => 'pass'],
                'migrations' => ['score' => 100.0, 'status' => 'pass'],
            ],
            'timestamp' => now()->toISOString(),
        ]);
        $this->instance(ReadinessAssessmentService::class, $mock);

        $this->artisan('system:readiness-score', [
            '--format' => 'json',
            '--out' => 'tmp/test-readiness-score-report.json',
            '--strict' => true,
        ])->assertExitCode(0);

        $this->assertTrue(File::exists($reportPath));
        $payload = json_decode((string) File::get($reportPath), true);
        $this->assertEquals(100.0, $payload['overall_score'] ?? null);

        File::delete($reportPath);
    }

    public function test_readiness_score_command_fails_in_strict_mode_when_any_axis_is_not_pass_or_not_100(): void
    {
        $mock = Mockery::mock(ReadinessAssessmentService::class);
        $mock->shouldReceive('assess')->once()->andReturn([
            'status' => 'needs_attention',
            'overall_score' => 80.0,
            'axes' => [
                'system_health' => ['score' => 100.0, 'status' => 'pass'],
                'scalability' => ['score' => 60.0, 'status' => 'warning'],
            ],
            'timestamp' => now()->toISOString(),
        ]);
        $this->instance(ReadinessAssessmentService::class, $mock);

        $this->artisan('system:readiness-score', [
            '--format' => 'json',
            '--strict' => true,
        ])->assertExitCode(1);
    }
}
