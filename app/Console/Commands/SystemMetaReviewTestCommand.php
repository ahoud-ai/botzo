<?php

namespace App\Console\Commands;

use App\Services\EmbeddedSignup\EmbeddedSignupReviewTestService;
use Illuminate\Console\Command;

class SystemMetaReviewTestCommand extends Command
{
    protected $signature = 'system:meta-review-test
        {--format=text : Output format (text|json)}
        {--permission=* : Limit the run to one or more review test keys}
        {--strict : Return non-zero when any review test fails}';

    protected $description = 'Run Meta App Review test calls from the server using the saved embedded signup token.';

    public function handle(EmbeddedSignupReviewTestService $reviewTestService): int
    {
        $report = $reviewTestService->buildReport((array) $this->option('permission'));

        if (strtolower((string) $this->option('format')) === 'json') {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->renderTextSummary($report);
        }

        if ((bool) $this->option('strict') && (int) data_get($report, 'summary.failed', 0) > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function renderTextSummary(array $report): void
    {
        $this->info('Meta review test report');
        $this->line(sprintf(' - status: %s', (string) ($report['status'] ?? 'unknown')));
        $this->line(sprintf(' - api version: %s', (string) ($report['api_version'] ?? 'unknown')));

        foreach ((array) ($report['configuration'] ?? []) as $key => $configured) {
            $this->line(sprintf(
                ' - config.%s: %s',
                $key,
                $configured ? 'configured' : 'missing'
            ));
        }

        foreach ((array) ($report['tests'] ?? []) as $test) {
            $this->line(sprintf(
                ' - %s => %s: %s',
                (string) ($test['key'] ?? 'unknown'),
                (string) ($test['status'] ?? 'unknown'),
                (string) ($test['message'] ?? '')
            ));
        }
    }
}
