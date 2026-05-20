<?php

namespace App\Console\Commands;

use App\Services\System\BillingIntegrityAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SystemBillingIntegrityAuditCommand extends Command
{
    protected $signature = 'system:billing-integrity-audit
        {--format=text : Output format (text|json)}
        {--out= : Optional output file path for JSON payload}
        {--sample=25 : Maximum number of sample rows per category}
        {--apply-safe-fixes : Apply only the deterministic low-risk invoice/payment backfill fixes}
        {--strict : Return non-zero when issues remain after the optional safe fixes}';

    protected $description = 'Audit billing, subscription, and organization previous integrity drift and optionally apply safe fixes.';

    public function handle(BillingIntegrityAuditService $auditService): int
    {
        $sampleLimit = max(1, (int) $this->option('sample'));
        $before = $auditService->buildReport($sampleLimit);
        $fixes = null;
        $after = $before;

        if ((bool) $this->option('apply-safe-fixes')) {
            $fixes = $auditService->applySafeFixes($sampleLimit);
            $after = $auditService->buildReport($sampleLimit);
        }

        $payload = [
            'status' => $after['status'] ?? 'unknown',
            'before' => $before,
            'applied_fixes' => $fixes,
            'after' => $after,
            'timestamp' => now()->toISOString(),
        ];

        if (strtolower((string) $this->option('format')) === 'json') {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->renderTextSummary($payload);
        }

        $out = trim((string) $this->option('out'));
        if ($out !== '') {
            $path = $this->resolvePath($out);
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
            $this->line("Billing integrity report written to {$path}");
        }

        if ((bool) $this->option('strict')) {
            if ((int) ($after['total_issue_count'] ?? 0) > 0) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function renderTextSummary(array $payload): void
    {
        $this->info('Billing integrity audit');
        $this->line(sprintf(' - status: %s', $payload['status'] ?? 'unknown'));

        $before = (array) ($payload['before'] ?? []);
        $after = (array) ($payload['after'] ?? []);

        $this->line(sprintf(' - before total issues: %d', (int) ($before['total_issue_count'] ?? 0)));
        $this->line(sprintf(' - before safe-fix candidates: %d', (int) ($before['total_safe_fix_candidates'] ?? 0)));

        if (is_array($payload['applied_fixes'] ?? null)) {
            $links = (array) (($payload['applied_fixes']['invoice_payment_links'] ?? []));
            $this->line(sprintf(
                ' - applied safe invoice links: %d/%d',
                (int) ($links['applied'] ?? 0),
                (int) ($links['attempted'] ?? 0)
            ));
        }

        $this->line(sprintf(' - after total issues: %d', (int) ($after['total_issue_count'] ?? 0)));

        foreach ((array) ($after['categories'] ?? []) as $key => $category) {
            $this->line(sprintf(
                ' - %s => count=%d%s',
                $key,
                (int) ($category['count'] ?? 0),
                !empty($category['auto_fixable']) ? ' (auto-fixable)' : ''
            ));
        }
    }

    private function resolvePath(string $path): string
    {
        if (
            str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[a-zA-Z]:[\\\\\/]/', $path) === 1
        ) {
            return $path;
        }

        return base_path($path);
    }
}
