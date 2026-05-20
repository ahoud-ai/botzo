<?php

namespace Tests\Feature;

use App\Services\System\ReadinessAssessmentService;
use App\Services\System\RiskReportService;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class SystemRiskReportCommandTest extends TestCase
{
    public function test_risk_report_command_writes_json_report_and_succeeds_when_strict_threshold_is_met(): void
    {
        $reportPath = base_path('tmp/test-risk-report.json');
        File::delete($reportPath);

        $assessmentMock = Mockery::mock(ReadinessAssessmentService::class);
        $assessmentMock->shouldReceive('assess')->once()->andReturn([
            'overall_score' => 95.0,
            'axes' => [],
        ]);
        $this->instance(ReadinessAssessmentService::class, $assessmentMock);

        $riskMock = Mockery::mock(RiskReportService::class);
        $riskMock->shouldReceive('build')->once()->andReturn([
            'status' => 'acceptable',
            'overall_readiness_score' => 95.0,
            'max_risk_score' => 30.0,
            'average_risk_score' => 20.0,
            'risks' => [],
            'timestamp' => now()->toISOString(),
        ]);
        $this->instance(RiskReportService::class, $riskMock);

        $this->artisan('system:risk-report', [
            '--format' => 'json',
            '--out' => 'tmp/test-risk-report.json',
            '--strict' => true,
            '--max-risk' => 40,
        ])->assertExitCode(0);

        $this->assertTrue(File::exists($reportPath));
        $payload = json_decode((string) File::get($reportPath), true);
        $this->assertEquals(30.0, $payload['max_risk_score'] ?? null);

        File::delete($reportPath);
    }

    public function test_risk_report_command_fails_in_strict_mode_when_max_risk_exceeds_threshold(): void
    {
        $assessmentMock = Mockery::mock(ReadinessAssessmentService::class);
        $assessmentMock->shouldReceive('assess')->once()->andReturn([
            'overall_score' => 80.0,
            'axes' => [],
        ]);
        $this->instance(ReadinessAssessmentService::class, $assessmentMock);

        $riskMock = Mockery::mock(RiskReportService::class);
        $riskMock->shouldReceive('build')->once()->andReturn([
            'status' => 'action_required',
            'overall_readiness_score' => 80.0,
            'max_risk_score' => 55.0,
            'average_risk_score' => 32.0,
            'risks' => [],
            'timestamp' => now()->toISOString(),
        ]);
        $this->instance(RiskReportService::class, $riskMock);

        $this->artisan('system:risk-report', [
            '--format' => 'json',
            '--strict' => true,
            '--max-risk' => 40,
        ])->assertExitCode(1);
    }
}
