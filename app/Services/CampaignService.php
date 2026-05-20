<?php

namespace App\Services;

use Carbon\Carbon;
use App\Jobs\SendCampaignMessageJob;
use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\ChatMedia;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Organization;
use App\Models\Template;
use App\Services\WhatsappService;
use App\Traits\TemplateTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Validator;

class CampaignService
{
    use TemplateTrait;

    public function store(object $request){
        $organizationId = session()->get('current_organization');

        $settings = app(SettingValueService::class);
        $timezone = $settings->getString('timezone', 'UTC');
        $organization = Organization::find($organizationId);
        $organizationMetadata = json_decode($organization->metadata ?? '{}', true);
        $timezone = $organizationMetadata['timezone'] ?? $timezone;

        $template = Template::where('organization_id', $organizationId)
            ->where('uuid', $request->template)
            ->whereNull('deleted_at')
            ->first();

        if (!$template) {
            throw ValidationException::withMessages([
                'template' => __('Template not found.'),
            ]);
        }

        $contactGroup = null;
        if ($request->contacts !== 'all') {
            $contactGroup = ContactGroup::where('organization_id', $organizationId)
                ->where('uuid', $request->contacts)
                ->whereNull('deleted_at')
                ->first();

            if (!$contactGroup) {
                throw ValidationException::withMessages([
                    'contacts' => __('Contact group not found.'),
                ]);
            }
        }

        try {
            DB::transaction(function () use ($request, $organizationId, $template, $contactGroup, $timezone, $settings) {
                //Request metadata
                $mediaId = null;
                if(in_array($request->header['format'], ['IMAGE', 'DOCUMENT', 'VIDEO'])){
                    $header = $request->header;
                    
                    if ($request->header['parameters']) {
                        $metadata['header']['format'] = $header['format'];
                        $metadata['header']['parameters'] = [];
                
                        foreach ($request->header['parameters'] as $key => $parameter) {
                            if ($parameter['selection'] === 'upload') {
                                //$path = $parameter['value']->store('public');
                                //$imageUrl = config('app.url') . '/media/' . $path;

                                $storage = $settings->getString('storage_system', 'local');
                                $fileName = $parameter['value']->getClientOriginalName();
                                $fileContent = $parameter['value'];

                                if($storage === 'local'){
                                    $file = Storage::disk('local')->put('public', $fileContent);
                                    $mediaFilePath = $file;
                    
                                    $mediaUrl = rtrim(config('app.url'), '/') . '/media/' . ltrim($mediaFilePath, '/');
                                } else if($storage === 'aws') {
                                    $file = $parameter['value'];
                                    $uploadedFile = $file->store('uploads/media/sent/' . $organizationId, 's3');
                                    $mediaFilePath = Storage::disk('s3')->url($uploadedFile);
                    
                                    $mediaUrl = $mediaFilePath;
                                }

                                $contentType = $fileContent->getMimeType()
                                    ?: $fileContent->getClientMimeType()
                                    ?: $this->getContentTypeFromUrl($mediaUrl);
                                $mediaSize = $fileContent->getSize() ?? $this->getMediaSizeInBytesFromUrl($mediaUrl);

                                //save media
                                $chatMedia = new ChatMedia;
                                $chatMedia->name = $fileName;
                                $chatMedia->location = $storage == 'aws' ? 'amazon' : 'local';
                                $chatMedia->path = $mediaUrl;
                                $chatMedia->type = $contentType;
                                $chatMedia->size = $mediaSize;
                                $chatMedia->created_at = now();
                                $chatMedia->save();

                                $mediaId = $chatMedia->id;
                            } else {
                                $mediaUrl = $parameter['value'];
                            }
                
                            $metadata['header']['parameters'][] = [
                                'type' => $parameter['type'],
                                'selection' => $parameter['selection'],
                                'value' => $mediaUrl,
                            ];
                        }
                    }
                } else {
                    $metadata['header'] = $request->header;
                }

                $metadata['body'] = $request->body;
                $metadata['footer'] = $request->footer;
                $metadata['buttons'] = $request->buttons;
                $metadata['media'] = $mediaId;

                // Convert $request->time from organization's timezone to UTC
                $scheduledAt = $request->skip_schedule ? Carbon::now('UTC') : Carbon::parse($request->time, $timezone)->setTimezone('UTC');
                // Determine if campaign is ready to send:
                // - skip_schedule = true: ready immediately
                // - scheduled time <= now: ready immediately
                // - scheduled time > now: scheduled for future
                $isReadyToSend = $request->skip_schedule || $scheduledAt->lte(now());

                //Create campaign
                $campaign = new Campaign;
                $campaign['organization_id'] = $organizationId;
                $campaign['name'] = $request->name;
                $campaign['template_id'] = $template->id;
                $campaign['contact_group_id'] = $request->contacts === 'all' ? 0 : $contactGroup->id;
                $campaign['metadata'] = json_encode($metadata);
                $campaign['created_by'] = auth()->user()->id;
                $campaign['status'] = $isReadyToSend ? 'ongoing' : 'scheduled'; // Set to ongoing if ready to send
                $campaign['scheduled_at'] = $scheduledAt;
                $campaign->save();
                
                // CRITICAL: Create campaign logs immediately
                // This eliminates delays and speeds up processing significantly
                $logsCreated = $this->createCampaignLogsImmediately($campaign, $organizationId, $isReadyToSend, $scheduledAt);

                if ($logsCreated === 0) {
                    throw ValidationException::withMessages([
                        'contacts' => __('No eligible contacts were found for this campaign.'),
                    ]);
                }
            });
        } catch (\Exception $e) {
            // Handle the exception here if needed.
            // The transaction has already been rolled back automatically.
            Log::error('Failed to store campaign', [
                'error_message' => $e->getMessage(),
                'organization_id' => $organizationId,
                'template' => $request->template,
                'contacts' => $request->contacts,
                'user_id' => auth()->user()->id,
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function getMediaInfo($path)
    {
        $fullPath = storage_path('app/public/' . $path);

        return [
            'name' => pathinfo($fullPath, PATHINFO_FILENAME),
            'type' => File::extension($fullPath),
            'size' => Storage::size($path), // Size in bytes
        ];
    }

    public function destroy($uuid)
    {
        Campaign::where('organization_id', session()->get('current_organization'))
            ->where('uuid', $uuid)
            ->update([
                'deleted_by' => auth()->user()->id,
                'deleted_at' => now(),
            ]);
    }

    private function getContentTypeFromUrl($url) {
        try {
            // Make a HEAD request to fetch headers only
            $response = Http::timeout(10)->connectTimeout(5)->head($url);
    
            // Check if the Content-Type header is present
            if ($response->hasHeader('Content-Type')) {
                return $response->header('Content-Type');
            }
    
            return 'application/octet-stream';
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Error fetching headers: ' . $e->getMessage());
            return 'application/octet-stream';
        }
    }

    private function getMediaSizeInBytesFromUrl($url) {
        try {
            $response = Http::timeout(10)->connectTimeout(5)->head($url);
            $contentLength = $response->header('Content-Length');

            if (is_array($contentLength)) {
                $contentLength = reset($contentLength);
            }

            $contentLength = trim((string) $contentLength);
            if ($contentLength !== '' && ctype_digit($contentLength)) {
                return (int) $contentLength;
            }
        } catch (\Exception $e) {
            Log::error('Error fetching media size: ' . $e->getMessage());
        }
    
        return 0;
    }

    /**
     * Create campaign logs immediately when campaign is created
     * This eliminates the need for CreateCampaignLogsJob and speeds up processing
     * 
     * @param Campaign $campaign
     * @param int $organizationId
     * @param bool $isReadyToSend Whether the campaign is ready to send immediately
     * @param Carbon|null $scheduledAt The scheduled time for the campaign (Carbon instance)
     * @return int
     */
    private function createCampaignLogsImmediately(Campaign $campaign, int $organizationId, bool $isReadyToSend = false, $scheduledAt = null)
    {
        try {
            // Get contacts in chunks to avoid memory issues
            $contactQuery = null;
            if (empty($campaign->contact_group_id) || $campaign->contact_group_id === '0') {
                $contactQuery = Contact::where('organization_id', $organizationId)
                    ->whereNull('deleted_at')
                    ->select('id');
            } else {
                $contactQuery = Contact::whereHas('contactGroups', function ($q) use ($campaign) {
                    $q->where('contact_groups.id', $campaign->contact_group_id);
                })
                ->where('organization_id', $organizationId)
                ->whereNull('deleted_at')
                ->select('id');
            }

            // Pre-load campaign and organization data once
            $campaignData = Campaign::with('organization')->find($campaign->id);
            $totalLogsCreated = 0;
            $now = now();
            
            // Use the same scheduled_at from campaigns table for campaign logs
            // Reload campaign to ensure we have the saved scheduled_at value
            $campaign->refresh();
            // Get raw scheduled_at value (bypass accessor that converts to org timezone)
            $logScheduledAt = $campaign->getAttributes()['scheduled_at'] ?? null;

            // Process contacts in chunks
            $contactQuery->chunk(1000, function ($contacts) use ($campaign, $campaignData, $organizationId, &$totalLogsCreated, $now, $isReadyToSend, $logScheduledAt) {
                $contactIds = $contacts->pluck('id')->toArray();
                
                // Check for existing logs (avoid duplicates)
                $existingLogs = CampaignLog::where('campaign_id', $campaign->id)
                    ->whereIn('contact_id', $contactIds)
                    ->pluck('contact_id')
                    ->toArray();
                
                $newContacts = array_diff($contactIds, $existingLogs);
                
                if (empty($newContacts)) {
                    return; // Skip if all contacts already have logs
                }

                // Pre-load contacts for this chunk
                $contactMap = Contact::whereIn('id', $newContacts)
                    ->where('organization_id', $organizationId)
                    ->get()
                    ->keyBy('id');

                // Prepare campaign logs (metadata and chat_id are null as requested)
                // scheduled_at is set based on campaign schedule:
                // - NULL if ready to send immediately
                // - campaign's scheduled_at if scheduled for future
                $campaignLogs = [];
                foreach ($contactMap->keys() as $contactId) {
                    $logData = [
                        'campaign_id' => $campaign->id,
                        'contact_id' => $contactId,
                        'chat_id' => null,  // As requested - null initially
                        'metadata' => null, // As requested - null initially
                        'status' => 'pending',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    
                    // Explicitly set scheduled_at (even if null) to ensure it's included in insert
                    if ($logScheduledAt !== null) {
                        $logData['scheduled_at'] = $logScheduledAt;
                    } else {
                        $logData['scheduled_at'] = null; // Explicitly set to null
                    }
                    
                    $campaignLogs[] = $logData;
                }

                // Insert logs in smaller chunks (1000 at a time)
                $insertChunks = array_chunk($campaignLogs, 1000);
                foreach ($insertChunks as $insertChunk) {
                    // Use DB::table()->insert() to ensure scheduled_at is included
                    \Illuminate\Support\Facades\DB::table('campaign_logs')->insert($insertChunk);
                    $totalLogsCreated += count($insertChunk);
                    
                    // Fetch the actual log IDs we just inserted
                        $contactIds = array_column($insertChunk, 'contact_id');
                    $insertedLogs = CampaignLog::where('campaign_id', $campaign->id)
                            ->whereIn('contact_id', $contactIds)
                        ->select('id', 'scheduled_at')
                        ->get();
                    
                    // Dispatch individual jobs for each log with available_at based on scheduled_at
                    foreach ($insertedLogs as $log) {
                        $job = SendCampaignMessageJob::dispatch($log->id)
                                ->onQueue('campaign-messages');
                        
                        // Set available_at based on scheduled_at (must be UTC)
                        // Use getAttributes() to bypass accessor that converts to org timezone
                        $rawScheduledAt = $log->getAttributes()['scheduled_at'] ?? null;
                        if ($rawScheduledAt) {
                            // Parse raw scheduled_at as UTC explicitly
                            $scheduledAtUtc = Carbon::parse($rawScheduledAt, 'UTC');
                            $job->delay($scheduledAtUtc);
                        }
                        // If scheduled_at is NULL, job is available immediately
                    }
                }
            });

            return $totalLogsCreated;
        } catch (\Exception $e) {
            Log::error('Failed to create campaign logs immediately', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
