<?php

namespace App\Console\Commands;

use App\Services\System\DocsConsistencyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SystemDocsConsistencyCheckCommand extends Command
{
    protected $signature = 'system:docs-consistency-check
        {--format=text : Output format (text|json)}
        {--out= : Optional output file path for JSON payload}
        {--strict : Return non-zero on any mismatch}';

    protected $description = 'Validate the consolidated README snapshot claims against executable code state.';

    public function handle(DocsConsistencyService $docsConsistencyService): int
    {
        $report = $docsConsistencyService->evaluate();
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
            $this->line("Docs consistency report written to {$path}");
        }

        $hasMismatches = ((array) ($report['mismatches'] ?? [])) !== [];
        if ((bool) $this->option('strict') && $hasMismatches) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function renderTextSummary(array $report): void
    {
        $this->info('Docs/code consistency');
        $this->line(sprintf(' - status: %s', $report['status'] ?? 'unknown'));
        $this->line(sprintf(' - score: %.2f', (float) ($report['score'] ?? 0.0)));
        $this->line(sprintf(' - actual route count: %d', (int) data_get($report, 'actual.route_count', 0)));

        foreach ((array) ($report['mismatches'] ?? []) as $mismatch) {
            $this->warn(" - mismatch: {$mismatch}");
        }

        foreach ((array) ($report['warnings'] ?? []) as $warning) {
            $this->line(" - warning: {$warning}");
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
