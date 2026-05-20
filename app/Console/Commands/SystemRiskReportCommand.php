<?php

namespace App\Console\Commands;

use App\Services\System\ReadinessAssessmentService;
use App\Services\System\RiskReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SystemRiskReportCommand extends Command
{
    protected $signature = 'system:risk-report
        {--format=text : Output format (text|json)}
        {--out= : Optional output file path for JSON payload}
        {--skip-quality : Skip quality checks while preparing readiness baseline}
        {--skip-tests : Skip full test suite execution while preparing readiness baseline}
        {--skip-security-audits : Skip dependency/security audits while preparing readiness baseline}
        {--strict : Return non-zero when max risk score exceeds --max-risk}
        {--max-risk=40 : Maximum allowed risk score when strict mode is used}';

    protected $description = 'Generate a normalized risk register using the readiness assessment baseline.';

    public function handle(
        ReadinessAssessmentService $assessmentService,
        RiskReportService $riskReportService
    ): int {
        $assessment = $assessmentService->assess([
            'skip_quality' => (bool) $this->option('skip-quality'),
            'skip_tests' => (bool) $this->option('skip-tests'),
            'skip_security_audits' => (bool) $this->option('skip-security-audits'),
        ]);
        $report = $riskReportService->build($assessment);

        $format = strtolower((string) $this->option('format'));
        if ($format === 'json') {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->renderTextSummary($report);
        }

        $out = trim((string) $this->option('out'));
        if ($out !== '') {
            $path = $this->resolvePath($out);
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
            $this->line("Risk report written to {$path}");
        }

        if ((bool) $this->option('strict')) {
            $maxAllowed = (float) $this->option('max-risk');
            $maxRisk = (float) ($report['max_risk_score'] ?? 0.0);
            if ($maxRisk > $maxAllowed) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function renderTextSummary(array $report): void
    {
        $this->info('Risk report');
        $this->line(sprintf(' - status: %s', $report['status'] ?? 'unknown'));
        $this->line(sprintf(' - readiness score: %.2f', (float) ($report['overall_readiness_score'] ?? 0.0)));
        $this->line(sprintf(' - max risk score: %.2f', (float) ($report['max_risk_score'] ?? 0.0)));
        $this->line(sprintf(' - average risk score: %.2f', (float) ($report['average_risk_score'] ?? 0.0)));

        foreach ((array) ($report['risks'] ?? []) as $risk) {
            $this->line(sprintf(
                ' - %s => probability=%.2f impact=%.2f score=%.2f severity=%s',
                $risk['id'] ?? 'unknown',
                (float) ($risk['probability'] ?? 0.0),
                (float) ($risk['impact'] ?? 0.0),
                (float) ($risk['risk_score'] ?? 0.0),
                $risk['severity'] ?? 'unknown'
            ));
        }
    }

    private function resolvePath(string $path): string
    {
        if (
            str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[a-zA-Z]:[\\\\\\/]/', $path) === 1
        ) {
            return $path;
        }

        return base_path($path);
    }
}
