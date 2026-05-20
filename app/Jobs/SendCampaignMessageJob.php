<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\Contact;
use App\Models\Organization;
use App\Services\Whatsapp\WhatsappAccessTokenRefreshService;
use App\Services\WhatsappService;
use App\Services\WhatsappRateLimiter;
use App\Traits\TemplateTrait;
use Carbon\Carbon;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendCampaignMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TemplateTrait;

    public $timeout = 60; // 1 minute per message
    public $tries = 2;

    private $campaignLogId;

    public function __construct(int $campaignLogId)
    {
        $this->campaignLogId = $campaignLogId;
    }

    public function handle()
    {
        try {
            // Load log with relationships
            $log = CampaignLog::with(['campaign.organization', 'contact'])->find($this->campaignLogId);
            
            if (!$log) {
                Log::warning('Campaign log not found', ['campaign_log_id' => $this->campaignLogId]);
                return;
            }

            // Check if log needs to be sent
            // Only process 'pending' and 'retrying' logs
            if (!in_array($log->status, ['pending', 'retrying'])) {
                Log::debug('Campaign log already processed', [
                    'campaign_log_id' => $this->campaignLogId,
                    'status' => $log->status
                ]);
                return;
            }

            // Handle scheduled_at: null means "send immediately", otherwise check if time has arrived
            // IMPORTANT: Get raw UTC value from database (bypass model accessor that converts to org timezone)
            $rawScheduledAt = $log->getAttributes()['scheduled_at'] ?? null;
            if (!is_null($rawScheduledAt)) {
                // Check if scheduled_at time has arrived - parse as UTC
                if (Carbon::parse($rawScheduledAt, 'UTC')->gt(Carbon::now('UTC'))) {
                    Log::debug('Campaign log scheduled for future', [
                        'campaign_log_id' => $this->campaignLogId,
                        'scheduled_at_display' => $log->scheduled_at, // Timezone converted
                        'scheduled_at_utc' => $rawScheduledAt, // Raw UTC from database
                        'current_utc' => Carbon::now('UTC')->toDateTimeString()
                    ]);
                    return;
                }
            }
            // If scheduled_at is null, treat as "send immediately" and continue processing

            // Check if campaign is still active
            if ($log->campaign->status !== 'ongoing') {
                Log::warning('Campaign not ongoing', [
                    'campaign_log_id' => $this->campaignLogId,
                    'campaign_id' => $log->campaign_id,
                    'campaign_status' => $log->campaign->status
                ]);
                return;
            }

            // Check if contact exists
            if (!$log->contact) {
                Log::warning('Contact not found for campaign log', [
                    'campaign_log_id' => $this->campaignLogId,
                    'contact_id' => $log->contact_id
                ]);
                $log->status = 'failed';
                $log->metadata = json_encode([
                    'error' => __('Contact not found or deleted'),
                    'failed_at' => now()->toDateTimeString(),
                    'auto_failed' => true
                ]);
                $log->save();
                return;
            }

            $organizationId = (int) $log->campaign->organization_id;

            if (!WhatsappRateLimiter::waitUntilCanSend(10, $organizationId)) {
                Log::warning('Campaign dispatch rate limit wait timeout', [
                    'campaign_log_id' => $this->campaignLogId,
                    'organization_id' => $organizationId,
                    'rate_limit_status' => WhatsappRateLimiter::getStatus($organizationId)
                ]);

                self::dispatch($this->campaignLogId)
                    ->onQueue('campaign-messages')
                    ->delay(now()->addSeconds(1));

                return;
            }

            if (!WhatsappRateLimiter::recordSent($organizationId)) {
                Log::warning('Campaign dispatch rate limit exceeded', [
                    'campaign_log_id' => $this->campaignLogId,
                    'organization_id' => $organizationId,
                    'rate_limit_status' => WhatsappRateLimiter::getStatus($organizationId)
                ]);

                self::dispatch($this->campaignLogId)
                    ->onQueue('campaign-messages')
                    ->delay(now()->addSeconds(1));

                return;
            }

            // Lock and update log status to 'ongoing'
            $log = DB::transaction(function() use ($log) {
                $locked = CampaignLog::where('id', $log->id)
                    ->whereIn('status', ['pending', 'retrying'])
                    ->lockForUpdate()
                    ->first();
                
                if (!$locked) {
                    return null; // Already processed by another worker
                }

                // Increment retry_count if this was a retry
                if ($locked->status === 'retrying') {
                    $locked->increment('retry_count');
                }

                // Update status to 'ongoing' and clear scheduled_at
                $locked->update([
                    'status' => 'ongoing',
                    'scheduled_at' => null,
                    'updated_at' => now()
                ]);

                return $locked->fresh(['campaign.organization', 'contact']);
            });

            if (!$log) {
                return; // Already processed
            }

            // Load organization metadata
            $orgMetadata = json_decode($log->campaign->organization->metadata ?? '{}', true);
            $retryEnabled = $orgMetadata['campaigns']['enable_resend'] ?? false;
            $retryIntervals = $orgMetadata['campaigns']['resend_intervals'] ?? [];
            $maxRetries = count($retryIntervals);

            // Initialize WhatsApp service
            $whatsappService = $this->initializeWhatsappService($orgMetadata, $log->campaign->organization_id);

            // Load template and campaign metadata
            $campaignTemplate = \App\Models\Template::find($log->campaign->template_id);
            $campaignMetadata = json_decode($log->campaign->metadata ?? '{}');

            // Build template with contact-specific data using TemplateTrait
            $template = $this->buildTemplateRequest($log->campaign_id, $log->contact);

            // Send WhatsApp message using async method for better performance
            // We'll wait for the promise to resolve synchronously here
            $promise = $whatsappService->sendTemplateMessageAsync(
                $log->contact->uuid,
                $template,
                $log->campaign->created_by,
                $log->campaign_id
            );

            // Wait for async response (non-blocking for other jobs, but blocking for this job)
            $response = $promise->wait();

            // Process response
            // Note: sendTemplateMessageAsync already creates Chat object and adds it to response->data->chat
            if ($response && isset($response->success) && $response->success === true) {
                // Success
                $log->status = 'success';
                $log->chat_id = isset($response->data->chat) && is_object($response->data->chat) 
                    ? ($response->data->chat->id ?? null) 
                    : null;
                
                // Clean metadata
                unset($response->success);
                if (property_exists($response, 'data') && property_exists($response->data, 'chat')) {
                    unset($response->data->chat);
                }
                $log->metadata = json_encode($response);
                $log->save();

                Log::info('Campaign message sent successfully', [
                    'campaign_log_id' => $this->campaignLogId,
                    'campaign_id' => $log->campaign_id,
                    'chat_id' => $log->chat_id
                ]);
            } else {
                // Failed
                $log->status = 'failed';
                
                // Clean metadata
                unset($response->success);
                if (property_exists($response, 'data') && property_exists($response->data, 'chat')) {
                    unset($response->data->chat);
                }
                $log->metadata = json_encode($response);
                $log->save();

                $this->handleRetryOrFinalFailure($log, $orgMetadata);

                Log::warning('Campaign message failed', [
                    'campaign_log_id' => $this->campaignLogId,
                    'campaign_id' => $log->campaign_id,
                    'retry_count' => $log->retry_count,
                    'max_retries' => $maxRetries,
                    'retry_enabled' => $retryEnabled
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error sending campaign message', [
                'campaign_log_id' => $this->campaignLogId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Mark as failed if log exists
            try {
                $log = CampaignLog::with(['campaign.organization', 'contact'])->find($this->campaignLogId);
                if ($log && in_array($log->status, ['pending', 'retrying', 'ongoing'])) {
                    $log->status = 'failed';
                    $log->metadata = json_encode([
                        'error' => $e->getMessage(),
                        'failed_at' => now()->toDateTimeString(),
                        'exception' => true
                    ]);
                    $log->save();

                    $orgMetadata = json_decode($log->campaign->organization->metadata ?? '{}', true);
                    $this->handleRetryOrFinalFailure($log, $orgMetadata);
                }
            } catch (\Exception $saveException) {
                Log::error('Failed to update log status after error', [
                    'campaign_log_id' => $this->campaignLogId,
                    'error' => $saveException->getMessage()
                ]);
            }

            throw $e;
        }
    }

    /**
     * Schedule retry for failed log
     */
    protected function scheduleRetry(CampaignLog $log, array $retryIntervals, int $maxRetries)
    {
        // Determine which interval to use based on retry_count
        $intervalIndex = $log->retry_count;
        $intervalHours = $retryIntervals[$intervalIndex] ?? null;

        if ($intervalHours === null) {
            // No more intervals available, mark as permanently failed
            Log::warning('No more retry intervals for log, marking as failed', [
                'campaign_log_id' => $log->id,
                'retry_count' => $log->retry_count,
                'max_retries' => $maxRetries
            ]);
            return;
        }

        // Settings store retry intervals in hours, matching the workspace UI copy.
        $scheduledAt = Carbon::now('UTC')->addHours((int) $intervalHours);
        
        // Mark log as 'retrying' and set scheduled_at timestamp
        $log->status = 'retrying';
        $log->scheduled_at = $scheduledAt->toDateTimeString();
        $log->save();

        // Dispatch job for retry with available_at set
        self::dispatch($log->id)
            ->onQueue('campaign-messages')
            ->delay($scheduledAt);

        Log::info('Scheduled retry for failed log', [
            'campaign_log_id' => $log->id,
            'campaign_id' => $log->campaign_id,
            'retry_attempt' => $log->retry_count + 1,
            'interval_index' => $intervalIndex,
            'interval_hours' => (int) $intervalHours,
            'scheduled_for' => $scheduledAt->toDateTimeString(),
        ]);
    }

    protected function handleRetryOrFinalFailure(CampaignLog $log, array $organizationMetadata): void
    {
        $retryEnabled = (bool) ($organizationMetadata['campaigns']['enable_resend'] ?? false);
        $retryIntervals = (array) ($organizationMetadata['campaigns']['resend_intervals'] ?? []);
        $maxRetries = count($retryIntervals);

        if ($retryEnabled && $log->retry_count < $maxRetries) {
            $this->scheduleRetry($log, $retryIntervals, $maxRetries);

            return;
        }

        $this->moveFailedContactToConfiguredGroup($log, $organizationMetadata);
    }

    protected function moveFailedContactToConfiguredGroup(CampaignLog $log, array $organizationMetadata): void
    {
        $log->loadMissing(['campaign', 'contact']);

        $campaignSettings = (array) ($organizationMetadata['campaigns'] ?? []);
        $groupUuid = trim((string) ($campaignSettings['failed_campaign_group'] ?? ''));
        $shouldMoveContact = (bool) ($campaignSettings['move_failed_contacts_to_group'] ?? false);

        if (!$shouldMoveContact || $groupUuid === '' || !$log->contact || !$log->campaign) {
            return;
        }

        $contactGroupId = DB::table('contact_groups')
            ->where('uuid', $groupUuid)
            ->where('organization_id', $log->campaign->organization_id)
            ->whereNull('deleted_at')
            ->value('id');

        if (!$contactGroupId) {
            Log::warning('Failed campaign group was not found in the current workspace scope.', [
                'campaign_log_id' => $log->id,
                'campaign_id' => $log->campaign_id,
                'contact_group_uuid' => $groupUuid,
            ]);

            return;
        }

        $existingAssignment = DB::table('contact_contact_group')
            ->where('contact_id', $log->contact_id)
            ->where('contact_group_id', $contactGroupId)
            ->exists();

        if ($existingAssignment) {
            return;
        }

        DB::table('contact_contact_group')->insert([
            'contact_id' => $log->contact_id,
            'contact_group_id' => $contactGroupId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('Moved failed campaign contact to the configured fallback group.', [
            'campaign_log_id' => $log->id,
            'campaign_id' => $log->campaign_id,
            'contact_id' => $log->contact_id,
            'contact_group_id' => $contactGroupId,
        ]);
    }

    /**
     * Initialize WhatsApp service
     */
    protected function initializeWhatsappService(array $orgMetadata, int $organizationId)
    {
        $accessToken = app(WhatsappAccessTokenRefreshService::class)
            ->resolveTokenForOrganization((int) $organizationId, true);
        $apiVersion = 'v18.0';
        $appId = $orgMetadata['whatsapp']['app_id'] ?? null;
        $phoneNumberId = $orgMetadata['whatsapp']['phone_number_id'] ?? null;
        $wabaId = $orgMetadata['whatsapp']['waba_id'] ?? null;

        return new WhatsappService(
            $accessToken,
            $apiVersion,
            $appId,
            $phoneNumberId,
            $wabaId,
            $organizationId
        );
    }
}
