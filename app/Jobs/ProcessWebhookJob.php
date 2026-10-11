<?php

namespace App\Jobs;

use App\Events\NewChatEvent;
use App\Models\AutoReply;
use App\Models\Contact;
use App\Models\Chat;
use App\Models\ChatLog;
use App\Models\ChatStatusLog;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\Template;
use App\Services\AutoReplyService;
use App\Services\ChatService;
use App\Services\PhoneService;
use App\Services\SubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    protected $payload;
    protected $organizationId;

    /**
     * Create a new job instance.
     */
    public function __construct(array $payload, int $organizationId)
    {
        $this->payload = $payload;
        $this->organizationId = $organizationId;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            $organization = Organization::find($this->organizationId);
            
            if (!$organization) {
                Log::warning('Organization not found in webhook job', [
                    'organization_id' => $this->organizationId
                ]);
                return;
            }

            // Meta can batch several entries, each with several changes, into one delivery.
            foreach ((array) ($this->payload['entry'] ?? []) as $entry) {
                foreach ((array) ($entry['changes'] ?? []) as $res) {
                    if (is_array($res)) {
                        $this->handleChange($res, $organization);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Error processing webhook job', [
                'organization_id' => $this->organizationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function handleChange(array $res, Organization $organization): void
    {
        $field = $res['field'] ?? null;

        if ($field === 'messages') {
            $statuses = $res['value']['statuses'] ?? null;
            $messages = $res['value']['messages'] ?? null;

            if ($statuses) {
                $this->handleStatuses($statuses, $organization);
            }

            if ($messages) {
                $this->handleMessages($messages, $res, $organization);
            }
        } else if ($field === 'message_template_status_update') {
            $this->handleTemplateStatusUpdate($res['value'] ?? null, $organization);
        } else if ($field === 'account_review_update') {
            $this->handleAccountReviewUpdate($organization, $res['value'] ?? null);
        } else if ($field === 'phone_number_name_update') {
            $this->handlePhoneNumberNameUpdate($organization, $res['value'] ?? null);
        } else if ($field === 'phone_number_quality_update') {
            $this->handlePhoneNumberQualityUpdate($organization, $res['value'] ?? null);
        } else if ($field === 'business_capability_update') {
            $this->handleBusinessCapabilityUpdate($organization, $res['value'] ?? null);
        }
    }

    protected function handleStatuses(array $statuses, Organization $organization)
    {
        foreach ($statuses as $response) {
            $chatWamId = $response['id'];
            $status = $response['status'];

            $chat = Chat::where('wam_id', $chatWamId)
                ->where('organization_id', $organization->id)
                ->first();

            if ($chat) {
                $statusChanged = $this->shouldApplyStatus($chat->status, $status);

                if ($statusChanged) {
                    $chat->status = $status;
                    $chat->save();
                }

                $chatStatusLog = new ChatStatusLog;
                $chatStatusLog->chat_id = $chat->id;
                $chatStatusLog->metadata = json_encode($response);
                $chatStatusLog->save();

                if ($statusChanged) {
                    $this->broadcastChatUpdate($chat, (int) $organization->id);
                }
            }
        }
    }

    protected function handleMessages(array $messages, array $res, Organization $organization)
    {
        // Customer messages are always stored and shown; an expired subscription only stops automatic replies.
        $autoReplyAllowed = !SubscriptionService::isSubscriptionLimitReachedForInboundMessages($organization->id);

        foreach ($messages as $response) {
            if (!is_array($response) || empty($response['id'])) {
                continue;
            }

            // Meta retries deliveries; make sure the same message is handled once even under concurrency.
            $lock = Cache::lock(sprintf('whatsapp-inbound:%d:%s', $organization->id, $response['id']), 120);
            if (!$lock->get()) {
                continue;
            }

            try {
                $this->processInboundMessage($response, $res, $organization, $autoReplyAllowed);
            } finally {
                $lock->release();
            }
        }
    }

    protected function processInboundMessage(array $response, array $res, Organization $organization, bool $autoReplyAllowed = true): void
    {
        $phone = (string) ($response['from'] ?? '');

        if (substr($phone, 0, 1) !== '+') {
            $phone = '+' . $phone;
        }

        $phone = PhoneService::getE164Format($phone);

        // Check if contact exists in organization
        $contact = Contact::where('organization_id', $organization->id)
            ->where('phone', $phone)
            ->whereNull('deleted_at')
            ->first();

        $isNewContact = false;

        if (!$contact) {
            if (SubscriptionService::isSubscriptionFeatureLimitReached($organization->id, 'contacts_limit')) {
                // Still keep the conversation visible; the limit only blocks automatic replies here.
                Log::notice('Inbound WhatsApp contact created over the contact limit; automatic replies skipped.', [
                    'organization_id' => $organization->id,
                    'wam_id' => $response['id'] ?? null,
                ]);
                $autoReplyAllowed = false;
            }

            // Create a contact - get contact data from res array
            $contactData = $this->contactProfileForMessage($res, $response);

            $contact = Contact::create([
                'first_name' => $contactData['name'] ?? null,
                'last_name' => null,
                'email' => null,
                'phone' => $phone,
                'organization_id' => $organization->id,
                'created_by' => 0,
                'created_at' => \Carbon\Carbon::now('UTC'),
                'updated_at' => \Carbon\Carbon::now('UTC'),
            ]);
            $isNewContact = true;
        }

        $contactData = $this->contactProfileForMessage($res, $response);

        if ($contact->first_name == NULL && $contactData) {
            $contact->update([
                'first_name' => $contactData['name'],
            ]);
        }

        $alreadyStored = Chat::where('wam_id', $response['id'])
            ->where('organization_id', $organization->id)
            ->exists();

        if ($alreadyStored) {
            return;
        }

        // First open the chat
        (new ChatService($organization->id))->handleTicketAssignment($contact->id);

        $chat = new Chat;
        $chat->organization_id = $organization->id;
        $chat->wam_id = $response['id'];
        $chat->contact_id = $contact->id;
        $chat->type = 'inbound';
        $response['channel'] = 'whatsapp';
        $response['provider'] = 'whatsapp_cloud';
        $chat->metadata = json_encode($response);
        $chat->status = 'delivered';
        $chat->created_at = isset($response['timestamp'])
            ? \Carbon\Carbon::createFromTimestamp((int) $response['timestamp'], 'UTC')
            : \Carbon\Carbon::now('UTC');

        try {
            $chat->save();
        } catch (UniqueConstraintViolationException) {
            // Another delivery of the same message won the race (organization_id + wam_id is unique).
            return;
        }

        $isMediaMessage = in_array($response['type'] ?? null, ['image', 'video', 'audio', 'document', 'sticker'], true);

        $chat = Chat::with('contact', 'media')->where('id', $chat->id)->first();

        $chatlogId = ChatLog::insertGetId([
            'contact_id' => $contact->id,
            'entity_type' => 'chat',
            'entity_id' => $chat->id,
            'created_at' => \Carbon\Carbon::now('UTC')
        ]);

        // Load ChatLog and broadcast event (event is queued automatically via ShouldQueue)
        $chatLogArray = ChatLog::where('id', $chatlogId)
            ->where('deleted_at', null)
            ->first();

        if ($isMediaMessage) {
            $this->dispatchMediaDownload($response, (int) $chat->id, (int) $organization->id);
        } elseif ($chatLogArray) {
            $chatArray = [
                [
                    'type' => 'chat',
                    'value' => $chatLogArray->relatedEntities
                ]
            ];
            event(new NewChatEvent($chatArray, $organization->id));
        }

        if (!$autoReplyAllowed) {
            return;
        }

        $isMessageLimitReached = SubscriptionService::isSubscriptionFeatureLimitReached(
            $organization->id,
            'message_limit'
        );

        if (!$isMessageLimitReached) {
            if (in_array($response['type'] ?? null, ['text', 'button', 'audio', 'interactive'], true)) {
                app(AutoReplyService::class)->checkAutoReply($chat, $isNewContact);
            }
        }
    }

    protected function dispatchMediaDownload(array $response, int $chatId, int $organizationId): void
    {
        // Without a queue worker the media job would never run, so download in-process unless queue mode is on.
        if (config('graph.webhook_processing') === 'queue') {
            ProcessWebhookMediaJob::dispatch($response, $chatId, $organizationId)->onQueue('webhook-media');

            return;
        }

        try {
            ProcessWebhookMediaJob::dispatchSync($response, $chatId, $organizationId);
        } catch (\Throwable $e) {
            Log::error('Inbound media download failed.', [
                'organization_id' => $organizationId,
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function shouldApplyStatus(?string $currentStatus, string $incomingStatus): bool
    {
        if ($currentStatus === $incomingStatus) {
            return false;
        }

        $priority = [
            'sent' => 1,
            'delivered' => 2,
            'read' => 3,
            'failed' => 4,
        ];

        $currentPriority = $priority[$currentStatus] ?? 0;
        $incomingPriority = $priority[$incomingStatus] ?? 0;

        if ($incomingPriority === 0 || $currentPriority === 0) {
            return true;
        }

        return $incomingPriority >= $currentPriority;
    }

    protected function broadcastChatUpdate(Chat $chat, int $organizationId): void
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

    protected function contactProfileForMessage(array $res, array $message): ?array
    {
        $contacts = $res['value']['contacts'] ?? [];
        $messageSender = preg_replace('/\D+/', '', (string) ($message['from'] ?? ''));

        foreach ($contacts as $contact) {
            $waId = preg_replace('/\D+/', '', (string) ($contact['wa_id'] ?? ''));

            if ($waId !== '' && $waId === $messageSender) {
                return $contact['profile'] ?? null;
            }
        }

        return $contacts[0]['profile'] ?? null;
    }

    protected function handleTemplateStatusUpdate($response, Organization $organization)
    {
        if (!$response) {
            return;
        }

        $template = Template::where('meta_id', $response['message_template_id'])
            ->where('organization_id', $organization->id)
            ->first();

        if ($template) {
            $template->status = $response['event'];
            $template->save();
        }
    }

    protected function handleAccountReviewUpdate(Organization $organization, $response)
    {
        if (!$response) {
            return;
        }

        $organizationConfig = Organization::where('id', $organization->id)->first();
        $metadataArray = $organizationConfig->metadata ? json_decode($organizationConfig->metadata, true) : [];

        $metadataArray['whatsapp']['account_review_status'] = $response['decision'] ?? NULL;

        $updatedMetadataJson = json_encode($metadataArray);
        $organizationConfig->metadata = $updatedMetadataJson;
        $organizationConfig->save();
    }

    protected function handlePhoneNumberNameUpdate(Organization $organization, $response)
    {
        if (!$response || ($response['decision'] ?? null) !== 'APPROVED') {
            return;
        }

        $organizationConfig = Organization::where('id', $organization->id)->first();
        $metadataArray = $organizationConfig->metadata ? json_decode($organizationConfig->metadata, true) : [];

        $metadataArray['whatsapp']['verified_name'] = $response['requested_verified_name'] ?? NULL;

        $updatedMetadataJson = json_encode($metadataArray);
        $organizationConfig->metadata = $updatedMetadataJson;
        $organizationConfig->save();
    }

    protected function handlePhoneNumberQualityUpdate(Organization $organization, $response)
    {
        if (!$response) {
            return;
        }

        $organizationConfig = Organization::where('id', $organization->id)->first();
        $metadataArray = $organizationConfig->metadata ? json_decode($organizationConfig->metadata, true) : [];

        $metadataArray['whatsapp']['messaging_limit_tier'] = $response['current_limit'] ?? NULL;

        $updatedMetadataJson = json_encode($metadataArray);
        $organizationConfig->metadata = $updatedMetadataJson;
        $organizationConfig->save();
    }

    protected function handleBusinessCapabilityUpdate(Organization $organization, $response)
    {
        if (!$response) {
            return;
        }

        $organizationConfig = Organization::where('id', $organization->id)->first();
        $metadataArray = $organizationConfig->metadata ? json_decode($organizationConfig->metadata, true) : [];

        $metadataArray['whatsapp']['max_daily_conversation_per_phone'] = $response['max_daily_conversation_per_phone'] ?? NULL;
        $metadataArray['whatsapp']['max_phone_numbers_per_business'] = $response['max_phone_numbers_per_business'] ?? NULL;

        $updatedMetadataJson = json_encode($metadataArray);
        $organizationConfig->metadata = $updatedMetadataJson;
        $organizationConfig->save();
    }
}
