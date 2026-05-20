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

            $res = $this->payload['entry'][0]['changes'][0];

            if ($res['field'] === 'messages') {
                $messages = $res['value']['messages'] ?? null;
                $statuses = $res['value']['statuses'] ?? null;

                if ($statuses) {
                    $this->handleStatuses($statuses, $organization);
                    
                } else if ($messages) {
                    $this->handleMessages($messages, $res, $organization);
                }
            } else if ($res['field'] === 'message_template_status_update') {
                $this->handleTemplateStatusUpdate($res['value'] ?? null, $organization);
            } else if ($res['field'] === 'account_review_update') {
                $this->handleAccountReviewUpdate($organization, $res['value'] ?? null);
            } else if ($res['field'] === 'phone_number_name_update') {
                $this->handlePhoneNumberNameUpdate($organization, $res['value'] ?? null);
            } else if ($res['field'] === 'phone_number_quality_update') {
                $this->handlePhoneNumberQualityUpdate($organization, $res['value'] ?? null);
            } else if ($res['field'] === 'business_capability_update') {
                $this->handleBusinessCapabilityUpdate($organization, $res['value'] ?? null);
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
        $isLimitReached = SubscriptionService::isSubscriptionLimitReachedForInboundMessages($organization->id);

        if (!$isLimitReached) {
            foreach ($messages as $response) {
                $phone = $response['from'];

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
                        Log::warning('Inbound WhatsApp message skipped because contact limit is reached.', [
                            'organization_id' => $organization->id,
                            'phone' => $phone,
                            'wam_id' => $response['id'] ?? null,
                        ]);
                        continue;
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
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $isNewContact = true;
                }

                if ($contact) {
                    $contactData = $this->contactProfileForMessage($res, $response);

                    if ($contact->first_name == NULL && $contactData) {
                        $contact->update([
                            'first_name' => $contactData['name'],
                        ]);
                    }

                    $chat = Chat::where('wam_id', $response['id'])
                        ->where('organization_id', $organization->id)
                        ->first();

                    if (!$chat) {
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
                        $chat->created_at = \Carbon\Carbon::now('UTC');
                        $chat->save();

                        $isMediaMessage = in_array($response['type'], ['image', 'video', 'audio', 'document', 'sticker'], true);

                        $chat = Chat::with('contact', 'media')->where('id', $chat->id)->first();

                        $chatlogId = ChatLog::insertGetId([
                            'contact_id' => $contact->id,
                            'entity_type' => 'chat',
                            'entity_id' => $chat->id,
                            'created_at' => now()
                        ]);

                        // Load ChatLog and broadcast event (event is queued automatically via ShouldQueue)
                        $chatLogArray = ChatLog::where('id', $chatlogId)
                            ->where('deleted_at', null)
                            ->first();
                        
                        if ($isMediaMessage) {
                            ProcessWebhookMediaJob::dispatch(
                                $response,
                                $chat->id,
                                $organization->id
                            )->onQueue('webhook-media');
                        } elseif ($chatLogArray) {
                            $chatArray = [
                                [
                                    'type' => 'chat',
                                    'value' => $chatLogArray->relatedEntities
                                ]
                            ];
                            event(new NewChatEvent($chatArray, $organization->id));
                        }

                        $isMessageLimitReached = SubscriptionService::isSubscriptionFeatureLimitReached(
                            $organization->id, 
                            'message_limit'
                        );

                        if (!$isMessageLimitReached) {
                            if ($response['type'] === 'text' || 
                                $response['type'] === 'button' || 
                                $response['type'] === 'audio' || 
                                $response['type'] === 'interactive') {
                                (new AutoReplyService)->checkAutoReply($chat, $isNewContact);
                            }
                        }
                    }
                }
            }
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
