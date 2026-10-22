<?php

namespace App\Services\Campaigns;

use App\Jobs\ProcessCampaignMessagesJob;
use App\Jobs\SendCampaignMessageJob;
use App\Models\Campaign;
use App\Models\CampaignLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Sends campaign messages without a queue worker: activates campaigns whose time came, sends
 * due messages in batches, and marks finished campaigns. Used by the /campaign-send endpoint and,
 * on hosting without a worker (queue "sync"), by the campaigns:dispatch command every few minutes.
 */
class CampaignDispatchRunner
{
    private const BATCH = 50;

    /**
     * @param  int  $maxBatches  how many batches of messages to send at most
     * @param  int|null  $maxSeconds  stop starting new batches after this many seconds
     * @return int messages processed
     */
    public function run(int $maxBatches = 3, ?int $maxSeconds = null): int
    {
        $startedAt = microtime(true);
        $this->markCompletedCampaigns();

        app(ProcessCampaignMessagesJob::class)->handle();

        $processed = 0;
        for ($batch = 0; $batch < $maxBatches; $batch++) {
            if ($maxSeconds !== null && microtime(true) - $startedAt > $maxSeconds) {
                break;
            }
            $sent = $this->processPendingCampaignMessages(self::BATCH);
            $processed += $sent;
            if ($sent === 0) {
                break;
            }
        }

        $this->markCompletedCampaigns();

        return $processed;
    }

    public function processPendingCampaignMessages($limit = 50)
    {
        $processedCount = 0;
        $now = Carbon::now('UTC');

        $pendingLogs = CampaignLog::with(['campaign', 'contact'])
            ->whereHas('campaign', static function ($query) {
                $query->where('status', 'ongoing')
                    ->whereNull('deleted_at');
            })
            ->whereIn('status', ['pending', 'retrying'])
            ->where(static function ($query) use ($now) {
                $query->whereNull('scheduled_at')
                    ->orWhereRaw('scheduled_at <= ?', [$now->toDateTimeString()]);
            })
            ->orderByRaw('COALESCE(scheduled_at, "1970-01-01 00:00:00") ASC')
            ->limit($limit)
            ->get();

        foreach ($pendingLogs as $log) {
            $rawScheduledAt = $log->getAttributes()['scheduled_at'] ?? null;

            if ($rawScheduledAt !== null && Carbon::parse($rawScheduledAt, 'UTC')->gt($now)) {
                continue;
            }

            try {
                $log->refresh(['campaign', 'contact']);

                if (! in_array($log->status, ['pending', 'retrying'], true) || $log->campaign->status !== 'ongoing') {
                    continue;
                }

                (new SendCampaignMessageJob($log->id))->handle();
                $log->refresh();

                if (in_array($log->status, ['success', 'failed'], true)) {
                    $processedCount++;
                }
            } catch (\Throwable $e) {
                Log::warning('Synchronous campaign message processing failed.', [
                    'campaign_log_id' => $log->id,
                    'campaign_id' => $log->campaign_id,
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                ]);

                try {
                    $failedLog = CampaignLog::find($log->id);
                    if ($failedLog && in_array($failedLog->status, ['pending', 'retrying'], true)) {
                        $failedLog->status = 'failed';
                        $failedLog->metadata = json_encode([
                            'error' => $e->getMessage(),
                            'failed_at' => now()->toDateTimeString(),
                            'sync_processing_error' => true,
                        ]);
                        $failedLog->save();
                    }
                } catch (\Throwable $saveError) {
                    Log::warning('Unable to persist failed campaign log state during synchronous dispatch.', [
                        'campaign_log_id' => $log->id,
                        'message' => $saveError->getMessage(),
                        'exception' => get_class($saveError),
                    ]);
                }
            }
        }

        return $processedCount;
    }

    private function markCompletedCampaigns()
    {
        $ongoingCampaignIds = Campaign::where('status', 'ongoing')->pluck('id')->toArray();

        if ($ongoingCampaignIds === []) {
            return [];
        }

        $campaignsWithPending = CampaignLog::whereIn('campaign_id', $ongoingCampaignIds)
            ->whereIn('status', ['pending', 'ongoing', 'retrying'])
            ->distinct()
            ->pluck('campaign_id')
            ->toArray();

        $campaignsToComplete = array_diff($ongoingCampaignIds, $campaignsWithPending);

        if ($campaignsToComplete === []) {
            return [];
        }

        Campaign::whereIn('id', $campaignsToComplete)
            ->where('status', 'ongoing')
            ->update(['status' => 'completed']);

        return $campaignsToComplete;
    }
}
