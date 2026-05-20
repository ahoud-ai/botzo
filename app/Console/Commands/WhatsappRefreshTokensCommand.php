<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\Whatsapp\WhatsappAccessTokenRefreshService;
use Illuminate\Console\Command;

class WhatsappRefreshTokensCommand extends Command
{
    protected $signature = 'whatsapp:refresh-tokens
        {--force : Force refresh even if token is not due}
        {--organization-id= : Refresh a single organization by ID}
        {--format=text : Output format (text|json)}';

    protected $description = 'Refresh due WhatsApp access tokens to avoid manual re-entry.';

    public function handle(WhatsappAccessTokenRefreshService $refreshService): int
    {
        $force = (bool) $this->option('force');
        $organizationId = $this->option('organization-id');

        $query = Organization::query()->select(['id', 'metadata'])->whereNotNull('metadata');
        if (!empty($organizationId)) {
            $query->where('id', (int) $organizationId);
        }

        $summary = [
            'scanned' => 0,
            'refreshed' => 0,
            'skipped' => 0,
            'failed' => 0,
            'failures' => [],
        ];

        $query->orderBy('id')->chunkById(100, function ($organizations) use ($refreshService, $force, &$summary) {
            foreach ($organizations as $organization) {
                $summary['scanned']++;

                $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];
                if (!is_array($metadata) || empty($metadata['whatsapp'])) {
                    $summary['skipped']++;
                    continue;
                }

                $result = $refreshService->refreshIfNeeded((int) $organization->id, $force, $metadata);
                if (($result->success ?? false) && ($result->refreshed ?? false)) {
                    $summary['refreshed']++;
                    continue;
                }

                if ($result->success ?? false) {
                    $summary['skipped']++;
                    continue;
                }

                $summary['failed']++;
                $summary['failures'][] = [
                    'organization_id' => (int) $organization->id,
                    'reason' => $result->reason ?? 'unknown',
                    'message' => $result->message ?? null,
                ];
            }
        });

        if (strtolower((string) $this->option('format')) === 'json') {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info('WhatsApp token refresh summary');
        $this->line(" - scanned: {$summary['scanned']}");
        $this->line(" - refreshed: {$summary['refreshed']}");
        $this->line(" - skipped: {$summary['skipped']}");
        $this->line(" - failed: {$summary['failed']}");

        if ($summary['failed'] > 0) {
            $this->warn('Failed organizations:');
            foreach ($summary['failures'] as $failure) {
                $this->line(
                    sprintf(
                        ' - org=%d, reason=%s%s',
                        $failure['organization_id'],
                        $failure['reason'],
                        !empty($failure['message']) ? ', message=' . $failure['message'] : ''
                    )
                );
            }
        }

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}

