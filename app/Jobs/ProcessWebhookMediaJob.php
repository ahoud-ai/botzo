<?php

namespace App\Jobs;

use App\Events\NewChatEvent;
use App\Models\Chat;
use App\Models\ChatLog;
use App\Models\ChatMedia;
use App\Models\Organization;
use App\Services\SettingValueService;
use App\Services\Whatsapp\WhatsappAccessTokenRefreshService;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Str;

class ProcessWebhookMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    protected $messageData;
    protected $chatId;
    protected $organizationId;

    /**
     * Create a new job instance.
     */
    public function __construct(array $messageData, int $chatId, int $organizationId)
    {
        $this->messageData = $messageData;
        $this->chatId = $chatId;
        $this->organizationId = $organizationId;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            $chat = Chat::find($this->chatId);
            $organization = Organization::find($this->organizationId);

            if (!$chat || !$organization) {
                Log::warning('Chat or Organization not found in media job', [
                    'chat_id' => $this->chatId,
                    'organization_id' => $this->organizationId
                ]);
                return;
            }

            $type = $this->messageData['type'];
            $mediaId = $this->messageData[$type]['id'];

            // Get & Download media file
            $media = $this->getMedia($mediaId, $organization);
            
            if (is_array($media) && isset($media['url'])) {
                $downloadedFile = $this->downloadMedia($media, $organization);

                // Upload media
                $chatMedia = new ChatMedia;
                $chatMedia->name = $type === 'document' ? ($this->messageData[$type]['filename'] ?? 'N/A') : 'N/A';
                $chatMedia->path = $downloadedFile['media_url'];
                $chatMedia->type = $media['mime_type'];
                $chatMedia->size = $media['file_size'];
                $chatMedia->location = $downloadedFile['location'];
                $chatMedia->created_at = now();
                $chatMedia->save();

                // Update chat
                Chat::where('id', $chat->id)->update([
                    'media_id' => $chatMedia->id
                ]);

                $updatedChat = $chat->fresh();

                if ($updatedChat) {
                    $this->broadcastChatUpdate($updatedChat, (int) $organization->id);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error processing webhook media job', [
                'chat_id' => $this->chatId,
                'organization_id' => $this->organizationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function broadcastChatUpdate(Chat $chat, int $organizationId): void
    {
        $chatLog = ChatLog::where('entity_type', 'chat')
            ->where('entity_id', $chat->id)
            ->where('deleted_at', null)
            ->latest('id')
            ->first();

        $chatArray = [
            [
                'type' => 'chat',
                'value' => $chatLog?->relatedEntities ?: $chat->fresh(['media', 'user', 'logs']),
            ],
        ];

        event(new NewChatEvent($chatArray, $organizationId));
    }

    private function downloadMedia($mediaInfo, Organization $organization)
    {
        $accessToken = app(WhatsappAccessTokenRefreshService::class)
            ->resolveTokenForOrganization((int) $organization->id, true);

        if (empty($accessToken)) {
            throw new \Exception(__('Missing organization metadata or access token'));
        }

        $client = new Client();

        $requestOptions = [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ],
        ];

        $response = $client->request('GET', $mediaInfo['url'], $requestOptions);

        $fileContent = $response->getBody();
        $mimeType = $mediaInfo['mime_type'] ?? 'application/octet-stream';
        $fileName = $this->generateFilename($fileContent, $mediaInfo['mime_type']);

        $storage = app(SettingValueService::class)->getString('storage_system', 'local');

        if ($storage === 'local') {
            $location = 'local';
            $file = Storage::disk('local')->put('public/' . $fileName, $fileContent);
            $mediaFilePath = $file;
            $mediaUrl = rtrim(config('app.url'), '/') . '/media/' . 'public/' . $fileName;
        } else if ($storage === 'aws') {
            $location = 'amazon';
            $filePath = 'uploads/media/received/' . $organization->id . '/' . Str::random(40) . time();
            $file = Storage::disk('s3')->put($filePath, $fileContent, [
                'ContentType' => $mimeType
            ]);
            $mediaUrl = Storage::disk('s3')->url($filePath);
        }

        $mediaData = [
            'media_url' => $mediaUrl,
            'location' => $location,
        ];

        return $mediaData;
    }

    private function generateFilename($fileContent, $mimeType)
    {
        // Generate a unique filename based on the file content
        $hash = sha1($fileContent);

        // Get the file extension from the media type
        $extension = explode('/', $mimeType)[1];

        // Combine the hash, timestamp, and extension to create a unique filename
        $filename = "{$hash}_" . time() . ".{$extension}";

        return $filename;
    }

    private function getMedia($mediaId, Organization $organization)
    {
        $accessToken = app(WhatsappAccessTokenRefreshService::class)
            ->resolveTokenForOrganization((int) $organization->id, true);

        if (empty($accessToken)) {
            throw new \Exception(__('Missing organization metadata or access token'));
        }

        $client = new Client();

        try {
            $requestOptions = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
            ];

            $response = $client->request('GET', "https://graph.facebook.com/v18.0/{$mediaId}", $requestOptions);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('Error fetching media from Facebook', [
                'media_id' => $mediaId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
