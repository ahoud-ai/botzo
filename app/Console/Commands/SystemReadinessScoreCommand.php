<?php

namespace App\Console\Commands;

use App\Services\System\ReadinessAssessmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SystemReadinessScoreCommand extends Command
{
    protected $signature = 'system:readiness-score
        {--format=text : Output format (text|json)}
        {--out= : Optional output file path for JSON payload}
        {--skip-quality : Skip quality checks (composer/npm gates)}
        {--skip-tests : Skip full test suite execution}
        {--skip-security-audits : Skip dependency/security audits}
        {--strict : Return non-zero unless all non-skipped axes are 100/pass}';

    protected $description = 'Generate a production-readiness scorecard with weighted risk axes.';

    public function handle(ReadinessAssessmentService $assessmentService): int
    {
        $payload = $assessmentService->assess([
            'skip_quality' => (bool) $this->option('skip-quality'),
            'skip_tests' => (bool) $this->option('skip-tests'),
            'skip_security_audits' => (bool) $this->option('skip-security-audits'),
        ]);

        $format = strtolower((string) $this->option('format'));
        if ($format === 'json') {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->renderTextSummary($payload);
        }

        $out = trim((string) $this->option('out'));
        if ($out !== '') {
            $path = $this->resolvePath($out);
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
            $this->line("Readiness report written to {$path}");
        }

        if ((bool) $this->option('strict') && !$this->passesStrictGate($payload)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function renderTextSummary(array $payload): void
    {
        $this->info('Readiness scorecard');
        $this->line(sprintf(' - status: %s', $payload['status'] ?? 'unknown'));
        $this->line(sprintf(' - overall score: %.2f', (float) ($payload['overall_score'] ?? 0.0)));

        foreach ((array) ($payload['axes'] ?? []) as $axis => $data) {
            $score = $data['score'] ?? null;
            $scoreText = $score === null ? 'n/a' : number_format((float) $score, 2);
            $this->line(sprintf(' - %s: %s (%s)', $axis, $scoreText, $data['status'] ?? 'unknown'));
        }
    }

    private function passesStrictGate(array $payload): bool
    {
        $axes = collect((array) ($payload['axes'] ?? []))
            ->filter(fn (array $axis): bool => ($axis['status'] ?? 'skipped') !== 'skipped');

        if ($axes->isEmpty()) {
            return false;
        }

        $allPass = $axes->every(function (array $axis): bool {
            $score = $axis['score'] ?? null;
            $status = $axis['status'] ?? 'fail';

            return $status === 'pass' && is_numeric($score) && (float) $score >= 100.0;
        });

        return $allPass && (float) ($payload['overall_score'] ?? 0.0) >= 100.0;
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
