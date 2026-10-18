<?php

namespace App\Jobs;

use App\Events\NewChatEvent;
use App\Models\Chat;
use App\Models\ChatLog;
use App\Models\ChatMedia;
use App\Models\Organization;
use App\Services\AutoReplyDispatchService;
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

class ProcessWebhookMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    protected $messageData;
    protected $chatId;
    protected $organizationId;
    protected bool $replyAfterDownload;

    /**
     * Create a new job instance.
     *
     * @param  bool  $replyAfterDownload  queue mode: schedule the automatic reply to this voice note once the file is in
     */
    public function __construct(array $messageData, int $chatId, int $organizationId, bool $replyAfterDownload = false)
    {
        $this->messageData = $messageData;
        $this->chatId = $chatId;
        $this->organizationId = $organizationId;
        $this->replyAfterDownload = $replyAfterDownload;
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

                // Customer media is private: the chat view opens it through the authenticated /chat-media route.
                $chatMedia = new ChatMedia;
                $chatMedia->name = $type === 'document' ? ($this->messageData[$type]['filename'] ?? 'N/A') : 'N/A';
                $chatMedia->storage_path = $downloadedFile['storage_path'];
                $chatMedia->visibility = ChatMedia::VISIBILITY_PRIVATE;
                $chatMedia->type = $media['mime_type'];
                $chatMedia->size = $media['file_size'];
                $chatMedia->location = $downloadedFile['location'];
                $chatMedia->created_at = now();
                $chatMedia->save();

                $chatMedia->path = ChatMedia::privateUrl((int) $chatMedia->id);
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

        $this->replyIfDeferred();
    }

    /**
     * Every attempt failed: still answer the voice note, so the customer is asked to type instead of waiting forever.
     */
    public function failed(\Throwable $exception): void
    {
        $this->replyIfDeferred();
    }

    private function replyIfDeferred(): void
    {
        if (!$this->replyAfterDownload) {
            return;
        }

        $chat = Chat::find($this->chatId);
        if ($chat) {
            app(AutoReplyDispatchService::class)->dispatchDeferred($chat);
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

        return $this->storeReceivedMedia(
            (string) $response->getBody(),
            (string) ($mediaInfo['mime_type'] ?? 'application/octet-stream'),
            (int) $organization->id
        );
    }

    /**
     * Store a file received from a customer where no public URL can reach it.
     *
     * @return array{storage_path:string,location:string}
     */
    protected function storeReceivedMedia(string $fileContent, string $mimeType, int $organizationId): array
    {
        $fileName = $this->generateFilename($fileContent, $mimeType);
        $storagePath = ChatMedia::PRIVATE_FOLDER.'/'.$organizationId.'/'.$fileName;

        if (app(SettingValueService::class)->getString('storage_system', 'local') === 'aws') {
            Storage::disk('s3')->put($storagePath, $fileContent, [
                'ContentType' => $mimeType,
                'visibility' => 'private',
            ]);

            return ['storage_path' => $storagePath, 'location' => 'amazon'];
        }

        // storage/app/private is outside storage/app/public, which the public /media route serves.
        if (! Storage::disk('local')->put($storagePath, $fileContent)) {
            throw new \RuntimeException('Unable to store received media.');
        }

        return ['storage_path' => $storagePath, 'location' => 'local'];
    }

    private function generateFilename($fileContent, $mimeType)
    {
        // Generate a unique filename based on the file content
        $hash = sha1($fileContent);

        // Extension from the base media type: "audio/ogg; codecs=opus" -> "ogg"
        $subtype = explode('/', explode(';', (string) $mimeType)[0])[1] ?? '';
        $extension = preg_replace('/[^a-z0-9]/', '', strtolower($subtype)) ?: 'bin';

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
