<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Jobs\SendCampaignMessageJob;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessCampaignMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    private const REDISPATCH_BATCH_SIZE = 200;
    public $timeout = 600;
    public $tries = 3;

    public function handle()
    {
        try {
            // Only activate scheduled campaigns that are ready to send
            // Individual jobs are created when logs are created, so we don't need to dispatch jobs here
            $this->activateScheduledCampaigns();
            
            // Re-dispatch jobs for stuck pending logs (logs that should have been processed but weren't)
            // This handles cases where jobs failed, were lost, or never dispatched
            $this->redispatchStuckPendingLogs();
            
            // Check for campaign completions - get all ongoing campaigns
            $ongoingCampaignIds = Campaign::where('status', 'ongoing')
                ->whereNull('deleted_at')
                ->pluck('id')
                ->toArray();
            
            if (!empty($ongoingCampaignIds)) {
                $this->batchCheckCampaignCompletions($ongoingCampaignIds);
            }
        } catch (\Exception $e) {
            Log::error('Error in ProcessCampaignMessagesJob', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Batch check multiple campaigns for completion (more efficient)
     */
    private function batchCheckCampaignCompletions(array $campaignIds)
    {
        if (empty($campaignIds)) {
            return;
        }

        // Check all campaigns for pending/ongoing/retrying logs
        // OPTIMIZATION: Chunk campaign IDs to avoid MySQL IN clause limits (typically 1000-10000 items)
        // With 100k+ campaigns, we need to process in batches
        // 'retrying' logs are scheduled retries that will be processed
        $campaignsWithPending = [];
        $chunkSize = 1000; // Safe chunk size for MySQL IN clause
        
        foreach (array_chunk($campaignIds, $chunkSize) as $chunk) {
            $chunkResults = CampaignLog::whereIn('campaign_id', $chunk)
                ->whereIn('status', ['pending', 'ongoing', 'retrying'])
            ->distinct()
            ->pluck('campaign_id')
            ->toArray();

            $campaignsWithPending = array_merge($campaignsWithPending, $chunkResults);
            }

        // Remove duplicates (in case a campaign appears in multiple chunks)
        $campaignsWithPending = array_unique($campaignsWithPending);

        // Get campaign IDs that should be marked as completed (no pending logs)
        $campaignsToComplete = array_diff($campaignIds, $campaignsWithPending);

        // OPTIMIZATION: Batch update all campaigns to completed in one query instead of N individual saves
        if (!empty($campaignsToComplete)) {
            Campaign::whereIn('id', $campaignsToComplete)
                ->where('status', 'ongoing')
                ->update(['status' => 'completed']);
        }
    }

    /**
     * Re-dispatch jobs for stuck pending logs
     * Efficient approach: Only re-dispatch if queue is empty
     * This handles cases where:
     * - All jobs were processed/failed and queue is empty
     * - Jobs were lost from the queue
     * - Jobs were never dispatched due to errors
     */
    private function redispatchStuckPendingLogs()
    {
        try {
            if (!$this->campaignQueueCanBeCheckedViaDatabase()) {
                Log::debug('Skipping stuck campaign redispatch because the active queue driver is not database-backed.', [
                    'queue_connection' => config('queue.default'),
                    'queue' => 'campaign-messages',
                ]);

                return;
            }

            $jobsInQueue = DB::table('jobs')
                ->where('queue', 'campaign-messages')
                ->count();

            if ($jobsInQueue > 0) {
                return;
            }
            
            $now = Carbon::now('UTC');
            
            // Find a bounded batch of pending logs that should already be processed.
            $stuckLogs = CampaignLog::whereHas('campaign', function ($query) {
                    $query->where('status', 'ongoing')
                          ->whereNull('deleted_at');
                })
                ->where('status', 'pending')
                ->whereNotNull('scheduled_at')
                ->whereRaw('scheduled_at <= ?', [$now->toDateTimeString()])
                ->orderBy('id')
                ->limit(self::REDISPATCH_BATCH_SIZE)
                ->select('id', 'campaign_id', 'scheduled_at', 'retry_count')
                ->get();

            if ($stuckLogs->isEmpty()) {
                return;
            }

            $redispatchCount = 0;
            $failedCount = 0;
            
            foreach ($stuckLogs as $log) {
                // Claim row first to prevent multiple workers from dispatching the same log.
                $claimed = CampaignLog::where('id', $log->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'retrying', 'updated_at' => now()]);

                if ($claimed === 0) {
                    continue; // Already claimed by another process.
                }

                // Get raw scheduled_at value (bypass accessor that converts to org timezone)
                $rawScheduledAt = $log->getAttributes()['scheduled_at'] ?? null;
                
                if (!$rawScheduledAt) {
                    continue; // Skip if somehow null
                }
                
                // Check if log has been retried 3 or more times
                // If so, mark as failed and don't re-dispatch
                if ($log->retry_count >= 3) {
                    CampaignLog::where('id', $log->id)->update([
                        'status' => 'failed',
                        'metadata' => json_encode([
                        'error' => __('Max retry attempts exceeded (3)'),
                        'failed_at' => now()->toDateTimeString(),
                        'retry_count' => $log->retry_count,
                        'auto_failed' => true
                        ]),
                        'updated_at' => now(),
                    ]);
                    $failedCount++;
                    continue; // Skip re-dispatch for this log
                }
                
                // Increment retry_count before re-dispatching
                // This tracks that we're re-dispatching a stuck log
                CampaignLog::where('id', $log->id)->increment('retry_count');
                
                // Re-dispatch job for this stuck log
                $scheduledAtUtc = Carbon::parse($rawScheduledAt, 'UTC');
                $job = SendCampaignMessageJob::dispatch($log->id)
                    ->onQueue('campaign-messages');
                
                // Set delay based on scheduled_at (should be immediate or past, so delay to now or past)
                if ($scheduledAtUtc->gt($now)) {
                    $job->delay($scheduledAtUtc);
                } else {
                    // scheduled_at is in the past, make job available immediately
                    $job->delay($now);
                }
                
                $redispatchCount++;
            }

            if ($redispatchCount > 0 || $failedCount > 0) {
                Log::info('Re-dispatched jobs for stuck pending logs (queue was empty)', [
                    'redispatched' => $redispatchCount,
                    'failed_max_retries' => $failedCount,
                    'batch_size' => self::REDISPATCH_BATCH_SIZE,
                    'total_scanned' => $stuckLogs->count()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error re-dispatching stuck pending logs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function campaignQueueCanBeCheckedViaDatabase(): bool
    {
        $connection = (string) config('queue.default', 'database');
        $driver = (string) config("queue.connections.{$connection}.driver", $connection);

        return $driver === 'database';
    }


    /**
     * Check for scheduled campaigns that are ready to send and activate them
     * Handles timezone-aware activation of scheduled campaigns
     */
    private function activateScheduledCampaigns()
    {
        try {
            // Find campaigns that are scheduled and ready to send
            $scheduledCampaigns = Campaign::where('status', 'scheduled')
                ->with('organization')
                ->whereNull('deleted_at')
                ->cursor();

            foreach ($scheduledCampaigns as $campaign) {
                $organization = $campaign->organization;
                $timezone = 'UTC';

                if ($organization) {
                    $metadata = $organization->metadata;
                    $metadata = isset($metadata) ? json_decode($metadata, true) : null;

                    if ($metadata && isset($metadata['timezone'])) {
                        $timezone = $metadata['timezone'];
                        }
                    }

                $scheduledAt = Carbon::parse($campaign->scheduled_at, 'UTC')->timezone($timezone);

                // Check if scheduled time has passed
                if ($scheduledAt->lte(Carbon::now($timezone))) {
                    // Update campaign status to ongoing
                    $campaign->status = 'ongoing';
                    $campaign->save();
                }
            }
        } catch (\Exception $e) {
            Log::error('Error activating scheduled campaigns', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
