<?php

namespace App\Console\Commands;

use App\Services\System\OnboardingBillingAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SystemSignupBillingAuditCommand extends Command
{
    protected $signature = 'system:signup-billing-audit
        {--days=30 : Look back window in days}
        {--format=text : Output format (text|json)}
        {--out= : Optional output file path for JSON payload}
        {--sample=25 : Maximum number of sample rows per category}
        {--apply-safe-fixes : Apply only deterministic low-risk fixes}
        {--strict : Return non-zero when actionable issues remain after the optional safe fixes}';

    protected $description = 'Audit onboarding, social signup, and subscription/payment incident fallout and optionally apply safe fixes.';

    public function handle(OnboardingBillingAuditService $auditService): int
    {
        $days = max(1, (int) $this->option('days'));
        $sampleLimit = max(1, (int) $this->option('sample'));
        $before = $auditService->buildReport($days, $sampleLimit);
        $fixes = null;
        $after = $before;

        if ((bool) $this->option('apply-safe-fixes')) {
            $fixes = $auditService->applySafeFixes($days, $sampleLimit);
            $after = $auditService->buildReport($days, $sampleLimit);
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
            $this->line("Signup/billing audit report written to {$path}");
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
        $this->info('Signup + billing incident audit');
        $this->line(sprintf(' - status: %s', $payload['status'] ?? 'unknown'));

        $before = (array) ($payload['before'] ?? []);
        $after = (array) ($payload['after'] ?? []);

        $this->line(sprintf(' - before total issues: %d', (int) ($before['total_issue_count'] ?? 0)));
        $this->line(sprintf(' - before safe-fix candidates: %d', (int) ($before['total_safe_fix_candidates'] ?? 0)));

        if (is_array($payload['applied_fixes'] ?? null)) {
            $removed = (array) (($payload['applied_fixes']['removed_auto_provisioned_subscriptions'] ?? []));
            $reconciled = (array) (($payload['applied_fixes']['reconciled_stuck_moyasar_intents'] ?? []));

            $this->line(sprintf(
                ' - removed auto subscriptions: %d/%d',
                (int) ($removed['applied'] ?? 0),
                (int) ($removed['attempted'] ?? 0)
            ));
            $this->line(sprintf(
                ' - reconciled stuck Moyasar intents: %d/%d',
                (int) ($reconciled['applied'] ?? 0),
                (int) ($reconciled['attempted'] ?? 0)
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
