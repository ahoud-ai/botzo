<?php

namespace App\Services;

use App\Contracts\MessagingGatewayContract;
use App\Events\NewChatEvent;
use App\Models\Campaign;
use App\Models\Chat;
use App\Models\ChatLog;
use App\Models\ChatMedia;
use App\Models\ChatStatusLog;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Template;
use App\Modules\WhatsApp\Infrastructure\Contracts\WhatsappTransportContract;
use App\Services\Concerns\InteractsWithWhatsappServiceSupport;
use App\Services\Whatsapp\WhatsappTemplateRequestGuardService;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WhatsappService implements MessagingGatewayContract, WhatsappTransportContract
{
    use InteractsWithWhatsappServiceSupport;

    private $accessToken;

    private $apiVersion;

    private $appId;

    private $phoneNumberId;

    private $organizationId;

    private $wabaId;

    public function __construct($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $organizationId)
    {
        $this->accessToken = $accessToken;
        $this->apiVersion = $apiVersion;
        $this->appId = $appId;
        $this->phoneNumberId = $phoneNumberId;
        $this->wabaId = $wabaId;
        $this->organizationId = $organizationId;

        $settings = app(SettingValueService::class);

        Config::set('broadcasting.connections.pusher', [
            'driver' => 'pusher',
            'key' => $settings->getString('pusher_app_key', ''),
            'secret' => $settings->getString('pusher_app_secret', ''),
            'app_id' => $settings->getString('pusher_app_id', ''),
            'options' => [
                'cluster' => $settings->getString('pusher_app_cluster', ''),
            ],
        ]);
    }

    private function resolveContactForCurrentOrganization(?string $contactUuid): ?Contact
    {
        if ($contactUuid === null || $contactUuid === '') {
            return null;
        }

        return Contact::where('uuid', $contactUuid)
            ->where('organization_id', $this->organizationId)
            ->whereNull('deleted_at')
            ->first();
    }

    private function contactScopeFailureResponse(): \stdClass
    {
        $responseObject = new \stdClass;
        $responseObject->success = false;
        $responseObject->message = __('Contact not found for this organization.');
        $responseObject->data = new \stdClass;
        $responseObject->data->error = new \stdClass;
        $responseObject->data->error->type = 'organization_scope';
        $responseObject->data->error->message = $responseObject->message;

        return $responseObject;
    }

    /**
     * This function sends a text message via a POST request to the specified phone number using Facebook's messaging API.
     *
     * @param  string  $phoneNumber  The phone number of the recipient.
     * @param  string  $messageContent  The content of the message to be sent.
     * @return mixed Returns the response from the HTTP request.
     */
    public function sendMessage($contactUuId, $messageContent, $userId = null, $type = 'text', $buttons = [], $header = [], $footer = null, $buttonLabel = null)
    {
        $contact = $this->resolveContactForCurrentOrganization($contactUuId);

        if (! $contact) {
            return $this->contactScopeFailureResponse();
        }

        if ($blockedResponse = app(OutboundMessageLimitGuardService::class)->blockedResponseForOrganization((int) $contact->organization_id)) {
            return $blockedResponse;
        }
        $this->ensureTicketProvisioned($contact);
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        $headers = $this->setHeaders();

        $requestData['messaging_product'] = 'whatsapp';
        $requestData['recipient_type'] = 'individual';
        $requestData['to'] = $contact->phone;
        $decodedMessageContent = html_entity_decode($messageContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($type == 'text') {
            $requestData['type'] = 'text';
            $requestData['text']['preview_url'] = true; // If you have added url either http or https a preview will be displayed
            $requestData['text']['body'] = $decodedMessageContent;
        } elseif ($type == 'interactive buttons' || $type == 'interactive call to action url' || $type == 'interactive list') {
            $requestData['type'] = 'interactive';

            if ($type == 'interactive buttons') {
                $requestData['interactive']['type'] = 'button';
            } elseif ($type == 'interactive call to action url') {
                $requestData['interactive']['type'] = 'cta_url';
            } elseif ($type == 'interactive list') {
                $requestData['interactive']['type'] = 'list';
            }

            if ($type == 'interactive buttons') {
                foreach ($buttons as $button) {
                    $requestData['interactive']['action']['buttons'][] = [
                        'type' => 'reply',
                        'reply' => [
                            'id' => $button['id'],
                            'title' => $button['title'],
                        ],
                    ];
                }
            } elseif ($type == 'interactive call to action url') {
                $requestData['interactive']['action']['name'] = 'cta_url';
                $requestData['interactive']['action']['parameters'] = $buttons;
            } elseif ($type == 'interactive list') {
                $requestData['interactive']['action']['sections'] = $buttons;
                $requestData['interactive']['action']['button'] = $buttonLabel;
            }

            if (! empty($header)) {
                $requestData['interactive']['header'] = $header;
            }

            $requestData['interactive']['body']['text'] = $decodedMessageContent;

            if ($footer != null) {
                $requestData['interactive']['footer'] = [
                    'text' => html_entity_decode($footer, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                ];
            }
        }

        $responseObject = $this->sendHttpRequest('POST', $url, $requestData, $headers);

        // Prepare response metadata
        $response = $this->formatOutboundMessageMetadata($type, $decodedMessageContent, $requestData);

        if ($responseObject->success === true) {
            // Success case - create chat with delivered status
            $chat = Chat::create([
                'organization_id' => $contact->organization_id,
                'wam_id' => $responseObject->data->messages[0]->id,
                'contact_id' => $contact->id,
                'type' => 'outbound',
                'user_id' => $userId,
                'metadata' => json_encode($response),
                'status' => 'delivered',
                'created_at' => Carbon::now('UTC'),
            ]);

            $chat = Chat::with('contact', 'media')->where('id', $chat->id)->first();
            $responseObject->data->chat = $chat;

            $chatlogId = ChatLog::insertGetId([
                'contact_id' => $contact->id,
                'entity_type' => 'chat',
                'entity_id' => $chat->id,
                'created_at' => Carbon::now('UTC'),
            ]);

            // Load ChatLog and broadcast event (event is queued automatically via ShouldQueue)
            $chatLogArray = ChatLog::where('id', $chatlogId)->where('deleted_at', null)->first();
            if ($chatLogArray) {
                $chatArray = [
                    [
                        'type' => 'chat',
                        'value' => $chatLogArray->relatedEntities,
                    ],
                ];
                event(new NewChatEvent($chatArray, $contact->organization_id));
            }
        } else {
            // Failure case - create chat with failed status and log error
            $chat = Chat::create([
                'organization_id' => $contact->organization_id,
                'wam_id' => null,
                'contact_id' => $contact->id,
                'type' => 'outbound',
                'user_id' => $userId,
                'metadata' => json_encode($response),
                'status' => 'failed',
                'created_at' => Carbon::now('UTC'),
            ]);

            // Create ChatStatusLog with error details
            $errorData = isset($responseObject->data->error) ? $responseObject->data->error : new \stdClass;
            $errorMetadata = [
                'status' => 'failed', // Include status in metadata for frontend detection
                'error' => $errorData,
                'message' => $responseObject->message ?? (isset($errorData->message) ? $errorData->message : 'Unknown error'),
                'error_user_msg' => isset($errorData->error_user_msg) ? $errorData->error_user_msg : null,
                'error_code' => isset($errorData->code) ? $errorData->code : null,
                'error_subcode' => isset($errorData->error_subcode) ? $errorData->error_subcode : null,
                'error_type' => isset($errorData->type) ? $errorData->type : null,
                'fbtrace_id' => isset($errorData->fbtrace_id) ? $errorData->fbtrace_id : null,
                'full_response' => $responseObject->data, // Store full response for debugging
            ];

            $chatStatusLog = new ChatStatusLog;
            $chatStatusLog->chat_id = $chat->id;
            $chatStatusLog->metadata = json_encode($errorMetadata);
            $chatStatusLog->created_at = Carbon::now('UTC');
            $chatStatusLog->save();

            // Also create ChatLog entry for failed message
            $chatlogId = ChatLog::insertGetId([
                'contact_id' => $contact->id,
                'entity_type' => 'chat',
                'entity_id' => $chat->id,
                'created_at' => Carbon::now('UTC'),
            ]);

            // Load ChatLog and broadcast event for failed message
            $chatLogArray = ChatLog::where('id', $chatlogId)->where('deleted_at', null)->first();
            if ($chatLogArray) {
                $relatedChat = $chatLogArray->relatedEntities;

                // Convert created_at to organization timezone before broadcasting
                if ($relatedChat) {
                    $organization = Organization::find($contact->organization_id);
                    $organizationTimezone = 'UTC';
                    if ($organization && $organization->metadata) {
                        $metadataArray = json_decode($organization->metadata, true);
                        if (isset($metadataArray['timezone'])) {
                            $organizationTimezone = $metadataArray['timezone'];
                        }
                    }

                    // Get raw UTC value from database (bypass accessor)
                    $rawCreatedAt = $relatedChat->getOriginal('created_at') ?? $relatedChat->getAttributes()['created_at'] ?? null;

                    if ($rawCreatedAt) {
                        // Convert from UTC to organization timezone
                        $convertedTime = Carbon::parse($rawCreatedAt, 'UTC')->setTimezone($organizationTimezone);
                        // Set the converted time (this will be serialized in the broadcast)
                        $relatedChat->setAttribute('created_at', $convertedTime->toDateTimeString());
                    }
                }

                $chatArray = [
                    [
                        'type' => 'chat',
                        'value' => $relatedChat,
                    ],
                ];
                event(new NewChatEvent($chatArray, $contact->organization_id));
            }
        }

        return $responseObject;
    }

    /**
     * This function sends a text message via a POST request to the specified phone number using Facebook's messaging API.
     *
     * @param  string  $phoneNumber  The phone number of the recipient.
     * @param  string  $messageContent  The content of the message to be sent.
     * @return mixed Returns the response from the HTTP request.
     */
    /**
     * Send template message asynchronously (non-blocking)
     * Returns a promise that resolves with the response
     */
    public function sendTemplateMessageAsync($contactUuId, $templateContent, $userId = null, $campaignId = null, $mediaId = null)
    {
        $contact = $this->resolveContactForCurrentOrganization($contactUuId);

        if (! $contact) {
            return \GuzzleHttp\Promise\Create::promiseFor($this->contactScopeFailureResponse());
        }

        if ($blockedResponse = app(OutboundMessageLimitGuardService::class)->blockedResponseForOrganization((int) $contact->organization_id)) {
            return \GuzzleHttp\Promise\Create::promiseFor($blockedResponse);
        }
        $this->ensureTicketProvisioned($contact);
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        $headers = $this->setHeaders();

        $requestData['messaging_product'] = 'whatsapp';
        $requestData['recipient_type'] = 'individual';
        $requestData['to'] = $contact->phone;
        $requestData['type'] = 'template';
        $requestData['template'] = $templateContent;

        // Send async request
        return $this->sendHttpRequestAsync('POST', $url, $requestData, $headers)
            ->then(function ($responseObject) use ($contact, $userId, $campaignId, $mediaId, $templateContent) {
                // Handle successful response
                if ($responseObject->success === true) {
                    if ($campaignId != null) {
                        $campaign = Campaign::where('id', $campaignId)->first();
                        $templateMetadata = json_decode($campaign->metadata);
                    }

                    $chat = Chat::create([
                        'organization_id' => $contact->organization_id,
                        'wam_id' => $responseObject->data->messages[0]->id,
                        'contact_id' => $contact->id,
                        'type' => 'outbound',
                        'user_id' => $userId,
                        'metadata' => $campaignId != null ? $this->buildCampaignTemplateChatMessage($templateMetadata, $contact) : $this->buildTemplateChatMessage($templateContent, $contact),
                        'media_id' => $campaignId != null ? $this->getMediaIdFromCampaign($campaignId) : $mediaId,
                        'status' => isset($responseObject->data->messages[0]->message_status) ? $responseObject->data->messages[0]->message_status : 'sent',
                        'created_at' => Carbon::now('UTC'),
                    ]);

                    $responseObject->data->chat = $chat;

                    $chatlogId = ChatLog::insertGetId([
                        'contact_id' => $contact->id,
                        'entity_type' => 'chat',
                        'entity_id' => $chat->id,
                        'created_at' => Carbon::now('UTC'),
                    ]);

                    // Load ChatLog and broadcast event (event is queued automatically via ShouldQueue)
                    $chatLogArray = ChatLog::where('id', $chatlogId)->where('deleted_at', null)->first();
                    if ($chatLogArray) {
                        $chatArray = [
                            [
                                'type' => 'chat',
                                'value' => $chatLogArray->relatedEntities,
                            ],
                        ];
                        event(new NewChatEvent($chatArray, $contact->organization_id));
                    }
                } else {
                    // Failure case - create chat with failed status and log error
                    if ($campaignId != null) {
                        $campaign = Campaign::where('id', $campaignId)->first();
                        $templateMetadata = json_decode($campaign->metadata);
                    }

                    $chat = Chat::create([
                        'organization_id' => $contact->organization_id,
                        'wam_id' => null,
                        'contact_id' => $contact->id,
                        'type' => 'outbound',
                        'user_id' => $userId,
                        'metadata' => $campaignId != null ? $this->buildCampaignTemplateChatMessage($templateMetadata, $contact) : $this->buildTemplateChatMessage($templateContent, $contact),
                        'media_id' => $campaignId != null ? $this->getMediaIdFromCampaign($campaignId) : $mediaId,
                        'status' => 'failed',
                        'created_at' => Carbon::now('UTC'),
                    ]);

                    // Create ChatStatusLog with error details
                    $errorData = isset($responseObject->data->error) ? $responseObject->data->error : new \stdClass;
                    $errorMetadata = [
                        'status' => 'failed', // Include status in metadata for frontend detection
                        'error' => $errorData,
                        'message' => $responseObject->message ?? (isset($errorData->message) ? $errorData->message : 'Unknown error'),
                        'error_user_msg' => isset($errorData->error_user_msg) ? $errorData->error_user_msg : null,
                        'error_code' => isset($errorData->code) ? $errorData->code : null,
                        'error_subcode' => isset($errorData->error_subcode) ? $errorData->error_subcode : null,
                        'error_type' => isset($errorData->type) ? $errorData->type : null,
                        'fbtrace_id' => isset($errorData->fbtrace_id) ? $errorData->fbtrace_id : null,
                        'full_response' => $responseObject->data, // Store full response for debugging
                    ];

                    $chatStatusLog = new ChatStatusLog;
                    $chatStatusLog->chat_id = $chat->id;
                    $chatStatusLog->metadata = json_encode($errorMetadata);
                    $chatStatusLog->created_at = Carbon::now('UTC');
                    $chatStatusLog->save();

                    // Also create ChatLog entry for failed message
                    $chatlogId = ChatLog::insertGetId([
                        'contact_id' => $contact->id,
                        'entity_type' => 'chat',
                        'entity_id' => $chat->id,
                        'created_at' => Carbon::now('UTC'),
                    ]);

                    // Load ChatLog and broadcast event for failed message
                    $chatLogArray = ChatLog::where('id', $chatlogId)->where('deleted_at', null)->first();
                    if ($chatLogArray) {
                        $relatedChat = $chatLogArray->relatedEntities;

                        // Convert created_at to organization timezone before broadcasting
                        if ($relatedChat) {
                            $organization = Organization::find($contact->organization_id);
                            $organizationTimezone = 'UTC';
                            if ($organization && $organization->metadata) {
                                $metadataArray = json_decode($organization->metadata, true);
                                if (isset($metadataArray['timezone'])) {
                                    $organizationTimezone = $metadataArray['timezone'];
                                }
                            }

                            // Get raw UTC value from database (bypass accessor)
                            $rawCreatedAt = $relatedChat->getOriginal('created_at') ?? $relatedChat->getAttributes()['created_at'] ?? null;

                            if ($rawCreatedAt) {
                                // Convert from UTC to organization timezone
                                $convertedTime = Carbon::parse($rawCreatedAt, 'UTC')->setTimezone($organizationTimezone);
                                // Set the converted time (this will be serialized in the broadcast)
                                $relatedChat->setAttribute('created_at', $convertedTime->toDateTimeString());
                            }
                        }

                        $chatArray = [
                            [
                                'type' => 'chat',
                                'value' => $relatedChat,
                            ],
                        ];
                        event(new NewChatEvent($chatArray, $contact->organization_id));
                    }
                }

                return $responseObject;
            });
    }

    public function sendTemplateMessage($contactUuId, $templateContent, $userId = null, $campaignId = null, $mediaId = null)
    {
        $contact = $this->resolveContactForCurrentOrganization($contactUuId);

        if (! $contact) {
            return $this->contactScopeFailureResponse();
        }

        if ($blockedResponse = app(OutboundMessageLimitGuardService::class)->blockedResponseForOrganization((int) $contact->organization_id)) {
            return $blockedResponse;
        }
        $this->ensureTicketProvisioned($contact);
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        $headers = $this->setHeaders();

        $requestData['messaging_product'] = 'whatsapp';
        $requestData['recipient_type'] = 'individual';
        $requestData['to'] = $contact->phone;
        $requestData['type'] = 'template';
        $requestData['template'] = $templateContent;

        $responseObject = $this->sendHttpRequest('POST', $url, $requestData, $headers);

        if ($responseObject->success === true) {
            if ($campaignId != null) {
                $campaign = Campaign::where('id', $campaignId)->first();
                $templateMetadata = json_decode($campaign->metadata);
            }

            $chat = Chat::create([
                'organization_id' => $contact->organization_id,
                'wam_id' => $responseObject->data->messages[0]->id,
                'contact_id' => $contact->id,
                'type' => 'outbound',
                'user_id' => $userId,
                'metadata' => $campaignId != null ? $this->buildCampaignTemplateChatMessage($templateMetadata, $contact) : $this->buildTemplateChatMessage($templateContent, $contact),
                'media_id' => $campaignId != null ? $this->getMediaIdFromCampaign($campaignId) : $mediaId,
                'status' => isset($responseObject->data->messages[0]->message_status) ? $responseObject->data->messages[0]->message_status : 'sent',
                'created_at' => Carbon::now('UTC'),
            ]);

            $responseObject->data->chat = $chat;

            $chatlogId = ChatLog::insertGetId([
                'contact_id' => $contact->id,
                'entity_type' => 'chat',
                'entity_id' => $chat->id,
                'created_at' => Carbon::now('UTC'),
            ]);

            // Load ChatLog and broadcast event (event is queued automatically via ShouldQueue)
            $chatLogArray = ChatLog::where('id', $chatlogId)->where('deleted_at', null)->first();
            if ($chatLogArray) {
                $chatArray = [
                    [
                        'type' => 'chat',
                        'value' => $chatLogArray->relatedEntities,
                    ],
                ];
                event(new NewChatEvent($chatArray, $contact->organization_id));
            }
        } else {
            // Failure case - create chat with failed status and log error
            if ($campaignId != null) {
                $campaign = Campaign::where('id', $campaignId)->first();
                $templateMetadata = json_decode($campaign->metadata);
            }

            $chat = Chat::create([
                'organization_id' => $contact->organization_id,
                'wam_id' => null,
                'contact_id' => $contact->id,
                'type' => 'outbound',
                'user_id' => $userId,
                'metadata' => $campaignId != null ? $this->buildCampaignTemplateChatMessage($templateMetadata, $contact) : $this->buildTemplateChatMessage($templateContent, $contact),
                'media_id' => $campaignId != null ? $this->getMediaIdFromCampaign($campaignId) : $mediaId,
                'status' => 'failed',
                'created_at' => Carbon::now('UTC'),
            ]);

            // Create ChatStatusLog with error details
            $errorData = isset($responseObject->data->error) ? $responseObject->data->error : new \stdClass;
            $errorMetadata = [
                'status' => 'failed', // Include status in metadata for frontend detection
                'error' => $errorData,
                'message' => $responseObject->message ?? (isset($errorData->message) ? $errorData->message : 'Unknown error'),
                'error_user_msg' => isset($errorData->error_user_msg) ? $errorData->error_user_msg : null,
                'error_code' => isset($errorData->code) ? $errorData->code : null,
                'error_subcode' => isset($errorData->error_subcode) ? $errorData->error_subcode : null,
                'error_type' => isset($errorData->type) ? $errorData->type : null,
                'fbtrace_id' => isset($errorData->fbtrace_id) ? $errorData->fbtrace_id : null,
                'full_response' => $responseObject->data, // Store full response for debugging
            ];

            $chatStatusLog = new ChatStatusLog;
            $chatStatusLog->chat_id = $chat->id;
            $chatStatusLog->metadata = json_encode($errorMetadata);
            $chatStatusLog->created_at = Carbon::now('UTC');
            $chatStatusLog->save();

            // Also create ChatLog entry for failed message
            $chatlogId = ChatLog::insertGetId([
                'contact_id' => $contact->id,
                'entity_type' => 'chat',
                'entity_id' => $chat->id,
                'created_at' => Carbon::now('UTC'),
            ]);

            // Load ChatLog and broadcast event for failed message
            $chatLogArray = ChatLog::where('id', $chatlogId)->where('deleted_at', null)->first();
            if ($chatLogArray) {
                $relatedChat = $chatLogArray->relatedEntities;

                // Convert created_at to organization timezone before broadcasting
                if ($relatedChat) {
                    $organization = Organization::find($contact->organization_id);
                    $organizationTimezone = 'UTC';
                    if ($organization && $organization->metadata) {
                        $metadataArray = json_decode($organization->metadata, true);
                        if (isset($metadataArray['timezone'])) {
                            $organizationTimezone = $metadataArray['timezone'];
                        }
                    }

                    // Get raw UTC value from database (bypass accessor)
                    $rawCreatedAt = $relatedChat->getOriginal('created_at') ?? $relatedChat->getAttributes()['created_at'] ?? null;

                    if ($rawCreatedAt) {
                        // Convert from UTC to organization timezone
                        $convertedTime = Carbon::parse($rawCreatedAt, 'UTC')->setTimezone($organizationTimezone);
                        // Set the converted time (this will be serialized in the broadcast)
                        $relatedChat->setAttribute('created_at', $convertedTime->toDateTimeString());
                    }
                }

                $chatArray = [
                    [
                        'type' => 'chat',
                        'value' => $relatedChat,
                    ],
                ];
                event(new NewChatEvent($chatArray, $contact->organization_id));
            }
        }

        return $responseObject;
    }

    private function getMediaIdFromCampaign($campaignId)
    {
        $campaign = Campaign::where('id', $campaignId)->first();
        $templateMetadata = json_decode($campaign->metadata);
        $mediaId = null;

        if (isset($templateMetadata->media)) {
            $mediaId = $templateMetadata->media;
        }

        return $mediaId;
    }

    private function buildCampaignTemplateChatMessage($templateMetadata, Contact $contact)
    {
        $array = [
            'channel' => 'whatsapp',
            'provider' => 'whatsapp_cloud',
        ];

        if ($templateMetadata->header->format == 'IMAGE' || $templateMetadata->header->format == 'VIDEO' || $templateMetadata->header->format == 'DOCUMENT' || $templateMetadata->header->format == 'LOCATION') {
            $array['type'] = strtolower($templateMetadata->header->format);
        } else {
            $array['type'] = 'text';
        }

        // HEADER
        if (isset($templateMetadata->header->text)) {
            $headerText = $templateMetadata->header->text;

            if (isset($templateMetadata->header->parameters) && ! empty($templateMetadata->header->parameters)) {
                $headerParameters = $templateMetadata->header->parameters;

                if ($headerParameters && count($headerParameters) >= 1) {
                    foreach ($headerParameters as $index => $parameter) {
                        $placeholder = '{{'.($index + 1).'}}';
                        $value = $parameter->selection === 'static' ? $parameter->value : $this->getParameters($contact, $parameter->value);

                        $headerText = str_replace($placeholder, $value, $headerText);
                    }
                }
            }

            $array['header']['text'] = $headerText;
        }

        // BODY
        if (isset($templateMetadata->body->text)) {
            $bodyText = $templateMetadata->body->text;

            if (isset($templateMetadata->body->parameters) && ! empty($templateMetadata->body->parameters)) {
                $bodyParameters = $templateMetadata->body->parameters;

                if ($bodyParameters && count($bodyParameters) >= 1) {
                    foreach ($bodyParameters as $index => $parameter) {
                        $placeholder = '{{'.($index + 1).'}}';
                        $value = $parameter->selection === 'static' ? $parameter->value : $this->getParameters($contact, $parameter->value);

                        $bodyText = str_replace($placeholder, $value, $bodyText);
                    }
                }

            }

            if ($array['type'] == 'text') {
                $array[$array['type']]['body'] = $bodyText;
            } else {
                $array[$array['type']]['caption'] = $bodyText;
            }
        }

        // FOOTER
        if (isset($templateMetadata->footer->text)) {
            $array[$array['type']]['footer'] = $templateMetadata->footer->text;
        }

        // BUTTONS
        if (isset($templateMetadata->buttons)) {
            foreach ($templateMetadata->buttons as $key => $button) {
                $array['buttons'][$key]['type'] = $button->type;
                $array['buttons'][$key]['text'] = $button->text;
                $array['buttons'][$key]['value'] = $button->value;

                if (isset($button->parameters)) {
                    $array['buttons'][$key]['parameters'] = $button->parameters;
                }
            }
        }

        // Debug statement intentionally removed.
        return json_encode($array);
    }

    private function buildTemplateChatMessage($templateContent, $contact)
    {
        // Get the template
        $template = Template::where('organization_id', $contact->organization_id)
            ->where('name', $templateContent['name'])
            ->where('language', $templateContent['language']['code'])
            ->first();

        $template = json_decode($template->metadata);
        $templateMetadatas = $template->components;
        $array = [
            'channel' => 'whatsapp',
            'provider' => 'whatsapp_cloud',
        ];
        $array['type'] = 'text';

        foreach ($templateMetadatas as $templateMetadata) {
            if ($templateMetadata->type == 'HEADER') {
                if ($templateMetadata->format == 'IMAGE' || $templateMetadata->format == 'VIDEO' || $templateMetadata->format == 'DOCUMENT' || $templateMetadata->format == 'LOCATION') {
                    $array['type'] = strtolower($templateMetadata->format);
                } elseif ($templateMetadata->format == 'TEXT' && isset($templateMetadata->text)) {
                    $headerText = $templateMetadata->text;

                    // Check for parameters in templateContent components
                    if (isset($templateContent['components']) && ! empty($templateContent['components'])) {
                        foreach ($templateContent['components'] as $component) {
                            if ($component['type'] === 'header' && isset($component['parameters'])) {
                                $headerParameters = $component['parameters'];

                                if ($headerParameters && count($headerParameters) >= 1) {
                                    foreach ($headerParameters as $index => $parameter) {
                                        $placeholder = '{{'.($index + 1).'}}';
                                        $value = $parameter['type'] === 'text' ? $parameter['text'] : $this->getParameters($contact, $parameter['text']);

                                        $headerText = str_replace($placeholder, $value, $headerText);
                                    }
                                }

                                break; // Found header parameters, no need to continue
                            }
                        }
                    }

                    $array['header']['text'] = $headerText;
                }
            }

            // BODY
            if ($templateMetadata->type == 'BODY') {
                if (isset($templateMetadata->text)) {
                    $bodyText = $templateMetadata->text;

                    // Check for parameters in templateContent components
                    if (isset($templateContent['components']) && ! empty($templateContent['components'])) {
                        foreach ($templateContent['components'] as $component) {
                            if ($component['type'] === 'body' && isset($component['parameters'])) {
                                $bodyParameters = $component['parameters'];

                                if ($bodyParameters && count($bodyParameters) >= 1) {
                                    foreach ($bodyParameters as $index => $parameter) {
                                        $placeholder = '{{'.($index + 1).'}}';
                                        $value = $parameter['type'] === 'text' ? $parameter['text'] : $this->getParameters($contact, $parameter['text']);

                                        $bodyText = str_replace($placeholder, $value, $bodyText);
                                    }
                                }

                                break; // Found body parameters, no need to continue
                            }
                        }
                    }

                    if ($array['type'] == 'text') {
                        $array[$array['type']]['body'] = $bodyText;
                    } else {
                        $array[$array['type']]['caption'] = $bodyText;
                    }
                }
            }

            // FOOTER
            if ($templateMetadata->type == 'FOOTER') {
                $array[$array['type']]['footer'] = $templateMetadata->text;
            }

            // BUTTONS
            if ($templateMetadata->type == 'BUTTONS') {
                foreach ($templateMetadata->buttons as $key => $button) {
                    $array['buttons'][$key]['type'] = $button->type;
                    $array['buttons'][$key]['text'] = $button->text;
                    $array['buttons'][$key]['value'] = $button->text;

                    if (isset($button->parameters)) {
                        $array['buttons'][$key]['parameters'] = $button->parameters;
                    }
                }
            }
        }

        return json_encode($array);
    }

    private function getParameters($contact, $parameter)
    {
        if ($parameter === 'first name') {
            return $contact->first_name;
        } elseif ($parameter === 'last name') {
            return $contact->last_name;
        } elseif ($parameter === 'name') {
            return $contact->first_name.' '.$contact->last_name;
        } elseif ($parameter === 'email') {
            return $contact->email;
        } elseif ($parameter === 'phone') {
            return $contact->phone;
        } elseif (strpos($parameter, 'field:') === 0) {
            // Handle custom contact fields: format is "field:field_name"
            $fieldName = substr($parameter, 6); // Remove "field:" prefix
            $metadata = json_decode($contact->metadata, true);

            return $metadata[$fieldName] ?? '';
        }

        return '';
    }

    /**
     * This function sends media content via a POST request and uploads the media to Facebook's resumable API.
     * Note that media types can only be audio, document, image, sticker, or video.
     *
     * @param  string  $phoneNumber  The phone number of the recipient.
     * @param  string  $mediaType  The type of media being uploaded. Valid options are audio, document, image, sticker, or video.
     * @param  string  $mediaFile  The file to be uploaded as media.
     * @return mixed Returns the response from the HTTP request.
     */
    /*public function sendMedia($contactUuid, $mediaType, $mediaFile)
    {
        $contact = Contact::where('uuid', $contactUuId)->first();
        $mediaFilePath = Storage::path("media/{$mediaFileName}");

        $fileUploadResponse = $this->initiateResumableUploadSession($mediaFilePath);

        if(!$fileUploadResponse->success){
            return $fileUploadResponse;
        }

        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";
        $headers = $this->setHeaders();

        $requestData['messaging_product'] = 'whatsapp';
        $requestData['recipient_type'] = 'individual';
        $requestData['to'] = $contact->phone;
        $requestData['type'] = $mediaType;
        $requestData[$mediaType]['id'] = $fileUploadResponse->data->h;

        $responseObject = $this->sendHttpRequest('POST', $url, $requestData, $headers);

        // Debug statement intentionally removed.
    }*/

    /**
     * This function sends a stored image as a media file via a POST request to the specified phone number using Facebook's messaging API.
     *
     * @param  string  $contactUuId  The UUID of the contact to whom the image will be sent.
     * @param  string  $imageUrl  The URL of the stored image.
     * @return mixed Returns the response from the HTTP request.
     */
    public function sendMedia($contactUuId, $mediaType, $mediaFileName, $mediaFilePath, $mediaUrl, $location, $caption = null, $transcription = null, array $mediaMetadata = [], ?int $userId = null)
    {
        $contact = $this->resolveContactForCurrentOrganization($contactUuId);

        if (! $contact) {
            return $this->contactScopeFailureResponse();
        }

        if ($blockedResponse = app(OutboundMessageLimitGuardService::class)->blockedResponseForOrganization((int) $contact->organization_id)) {
            return $blockedResponse;
        }
        $this->ensureTicketProvisioned($contact);

        $resolvedMediaMetadata = $this->resolveOutboundMediaMetadata($mediaUrl, $mediaFilePath, $mediaType, $mediaMetadata);
        $contentType = $resolvedMediaMetadata['content_type'];
        $mediaSize = $resolvedMediaMetadata['size'];

        $media = ChatMedia::create([
            'name' => $mediaFileName,
            'path' => $mediaUrl,
            'location' => $location,
            'type' => $contentType,
            'size' => $mediaSize,
        ]);

        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        $headers = $this->setHeaders();

        $requestData['messaging_product'] = 'whatsapp';
        $requestData['recipient_type'] = 'individual';
        $requestData['to'] = $contact->phone;
        $requestData['type'] = $mediaType;
        $requestData[$mediaType]['link'] = $mediaUrl;

        if ($mediaType == 'document') {
            $requestData[$mediaType]['filename'] = $mediaFileName;
        }

        if ($caption != null && $mediaType != 'audio') {
            $requestData[$mediaType]['caption'] = $caption;
        }

        $responseObject = $this->sendHttpRequest('POST', $url, $requestData, $headers);

        // Log::info(json_encode($responseObject));

        if ($responseObject->success === true) {
            // Log::info($mediaUrl);
            $wamId = $responseObject->data->messages[0]->id;
            $mediaData = $this->formatMediaResponse($wamId, $mediaUrl, $mediaType, $contentType, $transcription);
            $mediaData = $this->enrichMediaResponseMetadata($mediaData, $mediaType, $caption, $mediaMetadata);

            $chat = Chat::create([
                'organization_id' => $contact->organization_id,
                'wam_id' => $wamId,
                'contact_id' => $contact->id,
                'type' => 'outbound',
                'metadata' => json_encode($mediaData),
                'media_id' => $media->id,
                'status' => 'sent',
                'created_at' => Carbon::now('UTC'),
                'user_id' => $userId,
            ]);

            $chatlogId = ChatLog::insertGetId([
                'contact_id' => $contact->id,
                'entity_type' => 'chat',
                'entity_id' => $chat->id,
                'created_at' => Carbon::now('UTC'),
            ]);

            $chat = Chat::with('contact', 'media', 'user')->where('id', $chat->id)->first();
            $responseObject->data->chat = $chat;

            // Load ChatLog and broadcast event (event is queued automatically via ShouldQueue)
            $chatLogArray = ChatLog::where('id', $chatlogId)->where('deleted_at', null)->first();
            if ($chatLogArray) {
                $chatArray = [
                    [
                        'type' => 'chat',
                        'value' => $chatLogArray->relatedEntities,
                    ],
                ];
                event(new NewChatEvent($chatArray, $contact->organization_id));
            }
        } else {
            // Failure case - create chat with failed status and log error
            $mediaData = $this->formatMediaResponse(null, $mediaUrl, $mediaType, $contentType, $transcription);
            $mediaData = $this->enrichMediaResponseMetadata($mediaData, $mediaType, $caption, $mediaMetadata);

            $chat = Chat::create([
                'organization_id' => $contact->organization_id,
                'wam_id' => null,
                'contact_id' => $contact->id,
                'type' => 'outbound',
                'metadata' => json_encode($mediaData),
                'media_id' => $media->id,
                'status' => 'failed',
                'created_at' => Carbon::now('UTC'),
                'user_id' => $userId,
            ]);

            // Create ChatStatusLog with error details
            $errorData = isset($responseObject->data->error) ? $responseObject->data->error : new \stdClass;
            $errorMetadata = [
                'status' => 'failed', // Include status in metadata for frontend detection
                'error' => $errorData,
                'message' => $responseObject->message ?? (isset($errorData->message) ? $errorData->message : 'Unknown error'),
                'error_user_msg' => isset($errorData->error_user_msg) ? $errorData->error_user_msg : null,
                'error_code' => isset($errorData->code) ? $errorData->code : null,
                'error_subcode' => isset($errorData->error_subcode) ? $errorData->error_subcode : null,
                'error_type' => isset($errorData->type) ? $errorData->type : null,
                'fbtrace_id' => isset($errorData->fbtrace_id) ? $errorData->fbtrace_id : null,
                'full_response' => $responseObject->data, // Store full response for debugging
            ];

            $chatStatusLog = new ChatStatusLog;
            $chatStatusLog->chat_id = $chat->id;
            $chatStatusLog->metadata = json_encode($errorMetadata);
            $chatStatusLog->created_at = Carbon::now('UTC');
            $chatStatusLog->save();

            // Also create ChatLog entry for failed message
            $chatlogId = ChatLog::insertGetId([
                'contact_id' => $contact->id,
                'entity_type' => 'chat',
                'entity_id' => $chat->id,
                'created_at' => Carbon::now('UTC'),
            ]);

            // Load ChatLog and broadcast event for failed message
            $chatLogArray = ChatLog::where('id', $chatlogId)->where('deleted_at', null)->first();
            if ($chatLogArray) {
                $relatedChat = $chatLogArray->relatedEntities;

                // Convert created_at to organization timezone before broadcasting
                if ($relatedChat) {
                    $organization = Organization::find($contact->organization_id);
                    $organizationTimezone = 'UTC';
                    if ($organization && $organization->metadata) {
                        $metadataArray = json_decode($organization->metadata, true);
                        if (isset($metadataArray['timezone'])) {
                            $organizationTimezone = $metadataArray['timezone'];
                        }
                    }

                    // Get raw UTC value from database (bypass accessor)
                    $rawCreatedAt = $relatedChat->getOriginal('created_at') ?? $relatedChat->getAttributes()['created_at'] ?? null;

                    if ($rawCreatedAt) {
                        // Convert from UTC to organization timezone
                        $convertedTime = Carbon::parse($rawCreatedAt, 'UTC')->setTimezone($organizationTimezone);
                        // Set the converted time (this will be serialized in the broadcast)
                        $relatedChat->setAttribute('created_at', $convertedTime->toDateTimeString());
                    }
                }

                $chatArray = [
                    [
                        'type' => 'chat',
                        'value' => $relatedChat,
                    ],
                ];
                event(new NewChatEvent($chatArray, $contact->organization_id));
            }

            $chat = Chat::with('contact', 'media', 'user')->where('id', $chat->id)->first();
            if (! isset($responseObject->data) || ! is_object($responseObject->data)) {
                $responseObject->data = new \stdClass;
            }
            $responseObject->data->chat = $chat;
        }

        return $responseObject;
    }

    /**
     * This function allows you to react to a specific message with an emoji via a POST request to Facebook's messaging API.
     *
     * @param  string  $phoneNumber  The phone number of the recipient.
     * @param  string  $wamId  The ID of the message you want to react to.
     * @param  string  $emoji  The emoji you want to use as a reaction.
     * @return mixed Returns the response from the HTTP request.
     */
    public function reactToMessage($phoneNumber, $wamId, $emoji)
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";
        $headers = $this->setHeaders();

        $requestData['messaging_product'] = 'whatsapp';
        $requestData['recipient_type'] = 'individual';
        $requestData['to'] = $phoneNumber;
        $requestData['type'] = 'reaction';
        $requestData['reaction']['message_id'] = $wamId;
        $requestData['reaction']['emoji'] = $emoji;

        $responseObject = $this->sendHttpRequest('POST', $url, $requestData, $headers);

        if (($responseObject->success ?? false) !== true) {
            Log::warning('WhatsApp reaction request failed.', [
                'organization_id' => $this->organizationId,
                'phone_number_id' => $this->phoneNumberId,
                'message_id' => $wamId,
                'error' => $responseObject->data->error->message ?? null,
            ]);
        }

        return $responseObject;
    }

    /**
     * This function sends a location to a specific phone number via a POST request using Facebook's messaging API.
     *
     * @param  string  $phoneNumber  The phone number of the recipient.
     * @param  object  $location  The location object containing longitude, latitude, name, and address.
     * @return mixed Returns the response from the HTTP request.
     */
    public function sendLocation($phoneNumber, $location)
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";
        $headers = $this->setHeaders();

        $requestData['messaging_product'] = 'whatsapp';
        $requestData['to'] = $phoneNumber;
        $requestData['type'] = 'location';
        $requestData['location']['longitude'] = $location->longitude;
        $requestData['location']['latitude'] = $location->latitude;
        $requestData['location']['name'] = $location->name;
        $requestData['location']['address'] = $location->address;

        $responseObject = $this->sendHttpRequest('POST', $url, $requestData, $headers);

        if (($responseObject->success ?? false) !== true) {
            Log::warning('WhatsApp location message request failed.', [
                'organization_id' => $this->organizationId,
                'phone_number_id' => $this->phoneNumberId,
                'recipient' => $phoneNumber,
                'error' => $responseObject->data->error->message ?? null,
            ]);
        }

        return $responseObject;
    }

    public function createTemplate(Request $request)
    {
        $templateRequestGuard = app(WhatsappTemplateRequestGuardService::class);
        $request = $templateRequestGuard->normalizeTemplateRequestPayload($request);
        if ($validationResponse = $templateRequestGuard->validateTemplateRequestPayload($request)) {
            return $validationResponse;
        }
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->wabaId}/message_templates";

        $requestData = [
            'name' => $request->name,
            'language' => $request->language,
            'category' => $request->category,
        ];

        if ($request->customize_ttl && $request->message_send_ttl_seconds) {
            $requestData['message_send_ttl_seconds'] = $request->message_send_ttl_seconds;
        }

        if ($request->category != 'AUTHENTICATION') {
            if ($request->header['format'] === 'TEXT') {
                if (isset($request->header['text'])) {
                    $headerComponent = [];

                    $headerComponent['type'] = 'HEADER';
                    $headerComponent['format'] = $request->header['format'];
                    $headerComponent['text'] = $request->header['text'];

                    if (! empty($request->header['example'])) {
                        $headerComponent['example']['header_text'] = $request->header['example'];
                    }

                    $requestData['components'][] = $headerComponent;
                }
            }

            if (($request->header['format'] === 'IMAGE' || $request->header['format'] === 'VIDEO' || $request->header['format'] === 'DOCUMENT')) {
                if (isset($request->header['example'])) {
                    $fileUploadResponse = $this->initiateResumableUploadSession($request->header['example']);

                    if (! $fileUploadResponse->success) {
                        return $fileUploadResponse;
                    }

                    $requestData['components'][] = [
                        'type' => 'HEADER',
                        'format' => $request->header['format'],
                        'example' => [
                            'header_handle' => [
                                $fileUploadResponse->data->h,
                            ],
                        ],
                    ];
                }
            }
        }

        if ($request->category == 'AUTHENTICATION') {
            $bodyComponent = [];
            $bodyComponent['type'] = 'BODY';
            $bodyComponent['add_security_recommendation'] = $request->body['add_security_recommendation'];

            $requestData['components'][] = $bodyComponent;
        } else {
            $bodyComponent = [];

            if ($request->body['text'] != null) {
                $bodyComponent['type'] = 'BODY';
                $bodyComponent['text'] = $request->body['text'];

                if (! empty($request->body['example'])) {
                    $bodyComponent['example']['body_text'][] = $request->body['example'];
                }

                $requestData['components'][] = $bodyComponent;
            }
        }

        if ($request->has('footer')) {
            if ($request->category != 'AUTHENTICATION') {
                if (isset($request->footer['text']) && $request->footer['text'] != null) {
                    $requestData['components'][] = [
                        'type' => 'FOOTER',
                        'text' => $request->footer['text'],
                    ];
                }
            } else {
                $requestData['components'][] = [
                    'type' => 'FOOTER',
                    'code_expiration_minutes' => $request->footer['code_expiration_minutes'],
                ];
            }
        }

        if ($request->category != 'AUTHENTICATION') {
            $templateButtons = is_array($request->buttons ?? null) ? $request->buttons : [];
            if (! empty($templateButtons)) {
                if (! isset($requestData['components'])) {
                    $requestData['components'] = [];
                }

                $requestData['components'][] = [
                    'type' => 'BUTTONS',
                    'buttons' => [],
                ];

                $quickReplyButtons = [];

                foreach ($templateButtons as $button) {
                    if ($button['type'] === 'QUICK_REPLY') {
                        $quickReplyButtons[] = [
                            'type' => $button['type'],
                            'text' => $button['text'],
                        ];
                    }
                }

                foreach ($templateButtons as $button) {
                    if ($button['type'] !== 'QUICK_REPLY') {
                        if ($button['type'] === 'URL') {
                            $requestData['components'][count($requestData['components']) - 1]['buttons'][] = [
                                'type' => $button['type'],
                                'text' => $button['text'],
                                'url' => $button['url'],
                            ];
                        } elseif ($button['type'] === 'PHONE_NUMBER') {
                            $requestData['components'][count($requestData['components']) - 1]['buttons'][] = [
                                'type' => $button['type'],
                                'text' => $button['text'],
                                'phone_number' => ($button['country'] ?? '').($button['phone_number'] ?? ''),
                            ];
                        } elseif ($button['type'] === 'COPY_CODE') {
                            $requestData['components'][count($requestData['components']) - 1]['buttons'][] = [
                                'type' => $button['type'],
                                'example' => $button['example'],
                            ];
                        }
                    }
                }

                // Add the quick reply buttons at the start
                if (! empty($quickReplyButtons)) {
                    $requestData['components'][count($requestData['components']) - 1]['buttons'] = array_merge($quickReplyButtons, $requestData['components'][count($requestData['components']) - 1]['buttons']);
                }
            }
        } else {
            $button = [
                'type' => $request->authentication_button['type'],
                'otp_type' => $request->authentication_button['otp_type'],
                'text' => $request->authentication_button['text'],
            ];

            if ($request->authentication_button['otp_type'] != 'copy_code') {
                $button['autofill_text'] = $request->authentication_button['autofill_text'];
                $button['supported_apps'] = $request->authentication_button['supported_apps'];
            }

            if ($request->authentication_button['otp_type'] === 'zero_tap') {
                $button['zero_tap_terms_accepted'] = $request->authentication_button['zero_tap_terms_accepted'];
            }

            $requestData['components'][] = [
                'type' => 'BUTTONS',
                'buttons' => [$button],
            ];
        }

        $client = new Client;
        $responseObject = new \stdClass;

        try {
            $response = $client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ]);

            $responseObject->success = true;
            $responseObject->data = json_decode($response->getBody()->getContents());

            // Save Template To Database
            $template = new Template;
            $template->organization_id = session()->get('current_organization');
            $template->meta_id = $responseObject->data->id;
            $template->name = $request->name;
            $template->category = $request->category;
            $template->language = $request->language;
            $template->metadata = json_encode($requestData);
            $template->status = $responseObject->data->status;
            $template->created_by = auth()->user()->id;
            $template->created_at = now();
            $template->updated_at = now();
            $template->save();
        } catch (ConnectException $e) {
            $responseObject->success = false;
            $responseObject->data = new \stdClass;
            $responseObject->data->error = new \stdClass;
            $responseObject->message = $e->getMessage();
            $this->logTemplateRequestFailure('create', $request, $responseObject->data);
        } catch (GuzzleException $e) {
            $response = $e->getResponse();
            $responseObject->success = false;
            $responseObject->data = json_decode($response->getBody()->getContents());
            $responseObject->message = $templateRequestGuard->buildTemplateApiErrorMessage($responseObject->data);
            $responseObject->message = $this->appendTemplateReadinessHint($responseObject->message);
            if (isset($responseObject->data->error)) {
                $responseObject->data->error->message = $responseObject->message;
                $responseObject->data->error->context = $this->buildTemplateReadinessContext();
            }
            $this->logTemplateRequestFailure('create', $request, $responseObject->data);
        } catch (Exception $e) {
            $responseObject->success = false;
            $responseObject->data = new \stdClass;
            $responseObject->data->error = new \stdClass;
            $responseObject->data->error->message = $e->getMessage();
            $this->logTemplateRequestFailure('create', $request, $responseObject->data);
        }

        return $responseObject;
    }

    public function updateTemplate(Request $request, $uuid)
    {
        $templateRequestGuard = app(WhatsappTemplateRequestGuardService::class);
        $request = $templateRequestGuard->normalizeTemplateRequestPayload($request);
        if ($validationResponse = $templateRequestGuard->validateTemplateRequestPayload($request)) {
            return $validationResponse;
        }
        $template = Template::where('organization_id', $this->organizationId)
            ->where('uuid', $uuid)
            ->whereNull('deleted_at')
            ->firstOrFail();
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$template->meta_id}";

        $requestData = [
            // "name" => $request->name,
            // "language" => $request->language,
            'category' => $template->status == 'APPROVED' ? $template->category : $request->category,
        ];

        if ($request->customize_ttl && $request->message_send_ttl_seconds) {
            $requestData['message_send_ttl_seconds'] = $request->message_send_ttl_seconds;
        }

        if ($request->category != 'AUTHENTICATION') {
            if ($request->header['format'] === 'TEXT') {
                if (isset($request->header['text'])) {
                    $headerComponent = [];

                    $headerComponent['type'] = 'HEADER';
                    $headerComponent['format'] = $request->header['format'];
                    $headerComponent['text'] = $request->header['text'];

                    if (! empty($request->header['example'])) {
                        $headerComponent['example']['header_text'] = $request->header['example'];
                    }

                    $requestData['components'][] = $headerComponent;
                }
            }

            if (($request->header['format'] === 'IMAGE' || $request->header['format'] === 'VIDEO' || $request->header['format'] === 'DOCUMENT')) {
                if (isset($request->header['example'])) {
                    $fileUploadResponse = $this->initiateResumableUploadSession($request->header['example']);

                    if (! $fileUploadResponse->success) {
                        return $fileUploadResponse;
                    }

                    $requestData['components'][] = [
                        'type' => 'HEADER',
                        'format' => $request->header['format'],
                        'example' => [
                            'header_handle' => [
                                $fileUploadResponse->data->h,
                            ],
                        ],
                    ];
                } else {
                    // Decode existing metadata
                    $metadata = json_decode($template->metadata, true);

                    // Extract existing header if available
                    $existingHeader = [];
                    if (isset($metadata['components'])) {
                        foreach ($metadata['components'] as $component) {
                            if ($component['type'] === 'HEADER') {
                                $existingHeader = $component;
                                break;
                            }
                        }
                    }

                    $requestData['components'][] = $existingHeader;
                }
            }
        }

        if ($request->category == 'AUTHENTICATION') {
            $bodyComponent = [];
            $bodyComponent['type'] = 'BODY';
            $bodyComponent['add_security_recommendation'] = $request->body['add_security_recommendation'];

            $requestData['components'][] = $bodyComponent;
        } else {
            if ($request->body['text'] != null) {
                $bodyComponent = [];

                $bodyComponent['type'] = 'BODY';
                $bodyComponent['text'] = $request->body['text'];

                if (! empty($request->body['example'])) {
                    $bodyComponent['example']['body_text'][] = $request->body['example'];
                }

                $requestData['components'][] = $bodyComponent;
            }
        }

        if ($request->has('footer')) {
            if ($request->category != 'AUTHENTICATION') {
                if ($request->footer['text'] != null) {
                    $requestData['components'][] = [
                        'type' => 'FOOTER',
                        'text' => $request->footer['text'],
                    ];
                }
            } else {
                $requestData['components'][] = [
                    'type' => 'FOOTER',
                    'code_expiration_minutes' => $request->footer['code_expiration_minutes'],
                ];
            }
        }

        if ($request->category != 'AUTHENTICATION') {
            $templateButtons = is_array($request->buttons ?? null) ? $request->buttons : [];
            if (! empty($templateButtons)) {
                if (! isset($requestData['components'])) {
                    $requestData['components'] = [];
                }

                $requestData['components'][] = [
                    'type' => 'BUTTONS',
                    'buttons' => [],
                ];

                $quickReplyButtons = [];

                foreach ($templateButtons as $button) {
                    if ($button['type'] === 'QUICK_REPLY') {
                        $quickReplyButtons[] = [
                            'type' => $button['type'],
                            'text' => $button['text'],
                        ];
                    }
                }

                foreach ($templateButtons as $button) {
                    if ($button['type'] !== 'QUICK_REPLY') {
                        if ($button['type'] === 'URL') {
                            $requestData['components'][count($requestData['components']) - 1]['buttons'][] = [
                                'type' => $button['type'],
                                'text' => $button['text'],
                                'url' => $button['url'],
                            ];
                        } elseif ($button['type'] === 'PHONE_NUMBER') {
                            $requestData['components'][count($requestData['components']) - 1]['buttons'][] = [
                                'type' => $button['type'],
                                'text' => $button['text'],
                                'phone_number' => ($button['country'] ?? '').($button['phone_number'] ?? ''),
                            ];
                        } elseif ($button['type'] === 'COPY_CODE') {
                            $requestData['components'][count($requestData['components']) - 1]['buttons'][] = [
                                'type' => $button['type'],
                                'example' => $button['example'],
                            ];
                        }
                    }
                }

                // Add the quick reply buttons at the start
                if (! empty($quickReplyButtons)) {
                    $requestData['components'][count($requestData['components']) - 1]['buttons'] = array_merge($quickReplyButtons, $requestData['components'][count($requestData['components']) - 1]['buttons']);
                }
            }
        } else {
            $button = [
                'type' => $request->authentication_button['type'],
                'otp_type' => $request->authentication_button['otp_type'],
                'text' => $request->authentication_button['text'],
            ];

            if ($request->authentication_button['otp_type'] != 'copy_code') {
                $button['autofill_text'] = $request->authentication_button['autofill_text'];
                $button['supported_apps'] = $request->authentication_button['supported_apps'];
            }

            if ($request->authentication_button['otp_type'] === 'zero_tap') {
                $button['zero_tap_terms_accepted'] = $request->authentication_button['zero_tap_terms_accepted'];
            }

            $requestData['components'][] = [
                'type' => 'BUTTONS',
                'buttons' => [$button],
            ];
        }

        $client = new Client;
        $responseObject = new \stdClass;

        try {
            $response = $client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ]);

            $responseObject->success = true;
            $responseObject->data = json_decode($response->getBody()->getContents());

            // Update Template In Database
            if ($template) {
                $template->organization_id = session()->get('current_organization');
                $template->category = $template->status == 'APPROVED' ? $template->category : $request->category;
                // $template->metadata = json_encode($requestData);
                $template->status = 'PENDING';
                $template->created_by = auth()->user()->id;
                $template->updated_at = now(); // No need to set `created_at` when updating
                $template->save();
            } else {
                // Handle case where template is not found (optional)
                throw new \Exception(__('Template not found'));
            }
        } catch (ConnectException $e) {
            $responseObject->success = false;
            $responseObject->data = new \stdClass;
            $responseObject->data->error = new \stdClass;
            $responseObject->message = $e->getMessage();
            $this->logTemplateRequestFailure('update', $request, $responseObject->data, $uuid);
        } catch (GuzzleException $e) {
            $response = $e->getResponse();
            $responseObject->success = false;
            $responseObject->data = json_decode($response->getBody()->getContents());
            $responseObject->message = $templateRequestGuard->buildTemplateApiErrorMessage($responseObject->data);
            $responseObject->message = $this->appendTemplateReadinessHint($responseObject->message);
            if (isset($responseObject->data->error)) {
                $responseObject->data->error->message = $responseObject->message;
                $responseObject->data->error->context = $this->buildTemplateReadinessContext();
            }
            $this->logTemplateRequestFailure('update', $request, $responseObject->data, $uuid);
        } catch (Exception $e) {
            $responseObject->success = false;
            $responseObject->data = new \stdClass;
            $responseObject->data->error = new \stdClass;
            $responseObject->data->error->message = $e->getMessage();
            $this->logTemplateRequestFailure('update', $request, $responseObject->data, $uuid);
        }

        return $responseObject;
    }

    public function syncTemplates()
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->wabaId}/message_templates";

        $client = new Client;
        $responseObject = new \stdClass;

        try {
            do {
                $response = $client->request('GET', $url, [
                    'headers' => [
                        'Authorization' => "OAuth {$this->accessToken}",
                    ],
                ]);

                $responseObject = json_decode($response->getBody()->getContents());

                // Debug statement intentionally removed.

                foreach ($responseObject->data as $templateData) {
                    $template = Template::where('organization_id', session()->get('current_organization'))
                        ->where('meta_id', $templateData->id)->first();

                    if ($template) {
                        $template->metadata = json_encode($templateData);
                        $template->status = $templateData->status;
                        $template->updated_at = now();
                        $template->deleted_at = null;
                        $template->save();
                    } else {
                        $template = new Template;
                        $template->organization_id = session()->get('current_organization');
                        $template->meta_id = $templateData->id;
                        $template->name = $templateData->name;
                        $template->category = $templateData->category;
                        $template->language = $templateData->language;
                        $template->metadata = json_encode($templateData);
                        $template->status = $templateData->status;
                        $template->created_by = auth()->user()->id;
                        $template->created_at = now();
                        $template->updated_at = now();
                        $template->save();
                    }
                }

                if (isset($responseObject->paging) && isset($responseObject->paging->next)) {
                    $url = $responseObject->paging->next;
                } else {
                    $url = null; // Break the loop if no next page URL is available
                }
            } while ($url);
        } catch (ConnectException $e) {
            $responseObject->success = false;
            $responseObject->data = new \stdClass;
            $responseObject->data->error = new \stdClass;
            $responseObject->data->error->message = $e->getMessage();
        } catch (GuzzleException $e) {
            $response = $e->getResponse();
            $responseObject->success = false;
            $responseObject->data = json_decode($response->getBody()->getContents());

            if (isset($responseObject->data->error->error_user_msg)) {
                $responseObject->message = $responseObject->data->error->error_user_msg;
            } else {
                $responseObject->message = $responseObject->data->error->message;
            }
        } catch (\Throwable $e) {
            $responseObject->success = false;
            $responseObject->data = new \stdClass;
            $responseObject->data->error = new \stdClass;
            $responseObject->data->error->message = $e->getMessage();
        }

        return $responseObject;
    }

    /**
     * This function deletes a template by its UUID via a DELETE request to Facebook's messaging API.
     *
     * @param  string  $uuid  The UUID of the template to be deleted.
     * @return mixed Returns the response from the HTTP request.
     */
    public function deleteTemplate($uuid)
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->wabaId}/message_templates";
        $headers = $this->setHeaders();

        $template = Template::where('organization_id', $this->organizationId)
            ->where('uuid', $uuid)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $requestData['hsm_id'] = $template->meta_id;
        $requestData['name'] = $template->name;

        $responseObject = $this->sendHttpRequest('DELETE', $url, $requestData, $headers);

        if ($responseObject->success) {
            $template->deleted_at = now();
            $template->save();
        }

        return $responseObject;
    }

    private function formatOutboundMessageMetadata(string $messageType, string $messageContent, array $requestData): array
    {
        $metadata = [
            'channel' => 'whatsapp',
            'provider' => 'whatsapp_cloud',
            'type' => 'text',
            'text' => [
                'body' => $messageContent,
            ],
        ];

        if (
            in_array($messageType, ['interactive buttons', 'interactive call to action url', 'interactive list'], true)
            && isset($requestData['interactive'])
        ) {
            $metadata['type'] = 'interactive';
            $metadata['interactive'] = $requestData['interactive'];
        }

        return $metadata;
    }

    public function getMedia($mediaId)
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$mediaId}";
        $headers = $this->setHeaders();

        $responseObject = $this->sendHttpRequest('GET', $url, null, $headers);

        return $responseObject;
    }

    public function checkHealth()
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->wabaId}?fields=health_status";
        $headers = $this->setHeaders();

        $responseObject = $this->sendHttpRequest('GET', $url, null, $headers);

        return $responseObject;
    }

    public function subscribeToWaba()
    {
        $responseObject = new \stdClass;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->accessToken,
            ])->post("https://graph.facebook.com/{$this->apiVersion}/{$this->wabaId}/subscribed_apps")->throw()->json();

            $responseObject->success = true;
            $responseObject->data = new \stdClass;
            $responseObject->data = (object) $response;
        } catch (\Exception $e) {
            $responseObject->success = false;
            $responseObject->data = new \stdClass;
            $responseObject->data->error = new \stdClass;
            $responseObject->data->error->message = $e->getMessage();
        }

        return $responseObject;
    }

    public function getWabaSubscriptions()
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->wabaId}/subscribed_apps";
        $headers = $this->setHeaders();

        $responseObject = $this->sendHttpRequest('GET', $url, null, $headers);

        return $responseObject;
    }

    public function overrideCallbackUrl($callbackUrl, $verifyToken)
    {
        $responseObject = new \stdClass;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->accessToken,
            ])->post("https://graph.facebook.com/{$this->apiVersion}/{$this->wabaId}/subscribed_apps", [
                'override_callback_uri' => $callbackUrl,
                'verify_token' => $verifyToken,
            ])->throw()->json();

            $responseObject->success = true;
            $responseObject->data = new \stdClass;
            $responseObject->data = (object) $response;
        } catch (\Exception $e) {
            $responseObject->success = false;
            $responseObject->data = new \stdClass;
            $responseObject->data->error = new \stdClass;
            $responseObject->data->error->message = $e->getMessage();
        }

        return $responseObject;
    }

    public function unSubscribeToWaba()
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->wabaId}/subscribed_apps";
        $headers = $this->setHeaders();

        $responseObject = $this->sendHttpRequest('DELETE', $url, null, $headers);

        return $responseObject;
    }

    public function getBusinessProfile()
    {
        return $this->accountInspectionService()->getBusinessProfile();
    }

    public function updateBusinessProfile(Request $request)
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/whatsapp_business_profile";

        $headers = $this->setHeaders();

        $requestData['messaging_product'] = 'whatsapp';
        $requestData['about'] = $request->about;
        $requestData['address'] = $request->address;
        $requestData['description'] = $request->description;
        $requestData['vertical'] = $request->industry;
        $requestData['email'] = $request->email;

        $profile_picture_url = null;

        if ($request->hasFile('profile_picture_url')) {
            $storage = app(SettingValueService::class)->getString('storage_system', 'local');
            $fileContent = $request->file('profile_picture_url');

            if ($storage === 'local') {
                $file = Storage::disk('local')->put('public', $fileContent);
                $mediaFilePath = $file;
                $profile_picture_url = rtrim(config('app.url'), '/').'/media/'.ltrim($mediaFilePath, '/');
            } elseif ($storage === 'aws') {
                $file = $request->file('profile_picture_url');
                $uploadedFile = $file->store('uploads/media/sent/'.$this->organizationId, 's3');
                $mediaFilePath = Storage::disk('s3')->url($uploadedFile);
                $profile_picture_url = $mediaFilePath;
            }

            $fileUploadResponse = $this->initiateResumableUploadSession($request->file('profile_picture_url'));

            if ($fileUploadResponse->success) {
                $requestData['profile_picture_handle'] = $fileUploadResponse->data->h;
            }
        }

        $responseObject = $this->sendHttpRequest('POST', $url, $requestData, $headers);

        if ($responseObject->success === true) {
            $organizationConfig = Organization::where('id', $this->organizationId)->first();
            $metadataArray = $organizationConfig->metadata ? json_decode($organizationConfig->metadata, true) : [];

            $metadataArray['whatsapp']['business_profile']['about'] = $request->about;
            $metadataArray['whatsapp']['business_profile']['address'] = $request->address;
            $metadataArray['whatsapp']['business_profile']['description'] = $request->description;
            $metadataArray['whatsapp']['business_profile']['industry'] = $request->industry;
            $metadataArray['whatsapp']['business_profile']['email'] = $request->email;
            if ($profile_picture_url != null) {
                $metadataArray['whatsapp']['business_profile']['profile_picture_url'] = $profile_picture_url;
            }

            $updatedMetadataJson = json_encode($metadataArray);

            $organizationConfig->metadata = $updatedMetadataJson;
            $organizationConfig->save();
        }

        return $responseObject;
    }

    public function deRegisterPhone()
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/deregister";

        $headers = $this->setHeaders();

        $responseObject = $this->sendHttpRequest('POST', $url, null, $headers);

        if (($responseObject->success ?? false) !== true) {
            Log::warning('WhatsApp phone deregistration failed.', [
                'organization_id' => $this->organizationId,
                'phone_number_id' => $this->phoneNumberId,
                'error' => $responseObject->data->error->message ?? null,
            ]);
        }

        return $responseObject;
    }

    public function getPhoneNumberId()
    {
        return $this->accountInspectionService()->getPhoneNumberId();
    }

    public function getPhoneNumberStatus()
    {
        return $this->accountInspectionService()->getPhoneNumberStatus();
    }

    public function getAccountReviewStatus()
    {
        return $this->accountInspectionService()->getAccountReviewStatus();
    }

    public function viewMedia($mediaId)
    {
        $response = $this->getMedia($mediaId);

        if (! $response->success) {
            return $response;
        }

        $url = $response->data->url;
        $headers = $this->setHeaders();

        $responseObject = $this->sendHttpRequest('GET', $url, null, $headers);

        if (($responseObject->success ?? false) !== true) {
            Log::warning('WhatsApp media fetch failed.', [
                'organization_id' => $this->organizationId,
                'phone_number_id' => $this->phoneNumberId,
                'media_id' => $mediaId,
                'error' => $responseObject->data->error->message ?? null,
            ]);
        }

        return $responseObject;
    }

    public function initiateResumableUploadSession($file)
    {
        $sessionResponse = $this->createResumableUploadSession($file);

        if (! $sessionResponse->success) {
            return $sessionResponse;
        }

        $uploadSessionId = $sessionResponse->data->id;
        $fileName = $file->getPathname();
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$uploadSessionId}";

        $client = new Client;
        $responseObject = new \stdClass;

        try {
            $response = $client->request('POST', $url, [
                'headers' => [
                    'Authorization' => "OAuth {$this->accessToken}",
                    'file_offset' => 0,
                ],
                'body' => fopen($fileName, 'r'),
                'timeout' => 2,
            ]);

            $responseObject->success = true;
            $responseObject->data = json_decode($response->getBody()->getContents());
        } catch (ConnectException $e) {
            $responseObject->success = false;
            $responseObject->data = new \stdClass;
            $responseObject->data->error = new \stdClass;
            $responseObject->data->error->message = $e->getMessage();
        } catch (GuzzleException $e) {
            $response = $e->getResponse();
            $responseObject->success = false;
            $responseObject->data = json_decode($response->getBody()->getContents());

            if (isset($responseObject->data->error->error_user_msg)) {
                $responseObject->message = $responseObject->data->error->error_user_msg;
            } else {
                $responseObject->message = $responseObject->data->error->message;
            }
        } catch (Exception $e) {
            $responseObject->success = false;
            $responseObject->data = new \stdClass;
            $responseObject->data->error = new \stdClass;
            $responseObject->data->error->message = $e->getMessage();
        }

        return $responseObject;
    }

    public function createResumableUploadSession($file)
    {
        $fileLength = $file->getSize();
        $fileType = $file->getMimeType();
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->appId}/uploads";

        $client = new Client;
        $responseObject = new \stdClass;

        try {
            $response = $client->request('POST', $url, [
                'form_params' => [
                    'file_length' => $fileLength,
                    'file_type' => $fileType,
                    'access_token' => $this->accessToken,
                ],
            ]);

            $responseObject->success = true;
            $responseObject->data = json_decode($response->getBody()->getContents());
        } catch (ConnectException $e) {
            $responseObject = $this->buildFailureResponseObjectFromThrowable($e);
        } catch (GuzzleException $e) {
            $responseObject = $this->buildFailureResponseObjectFromThrowable($e);
        } catch (\Throwable $e) {
            $responseObject = $this->buildFailureResponseObjectFromThrowable($e);
        }

        return $responseObject;
    }

    /**
     * Send HTTP request asynchronously (non-blocking)
     * Returns a promise that resolves when the request completes
     */
    private function sendHttpRequestAsync($method, $url, $data = [], $headers = [])
    {
        $client = new Client;
        $startTime = microtime(true);

        $requestOptions = [
            'headers' => $headers,
            'timeout' => 30,
            'connect_timeout' => 10,
        ];

        if (isset($data) && ($method === 'POST' || $method === 'PUT' || $method === 'DELETE')) {
            $requestOptions['json'] = $data;
        }

        // Return async promise
        return $client->requestAsync($method, $url, $requestOptions)
            ->then(function ($response) use ($startTime, $url, $method) {
                $responseObject = new \stdClass;
                $responseObject->success = true;
                $responseObject->data = json_decode($response->getBody()->getContents());

                $duration = microtime(true) - $startTime;
                if ($duration > 2.0) {
                    Log::warning('Slow WhatsApp API request', [
                        'url' => $url,
                        'method' => $method,
                        'duration' => round($duration, 2).'s',
                    ]);
                }

                return $responseObject;
            })
            ->otherwise(function ($exception) {
                $responseObject = new \stdClass;
                $responseObject->success = false;

                if ($exception instanceof ConnectException) {
                    $responseObject->data = new \stdClass;
                    $responseObject->data->error = new \stdClass;
                    $responseObject->data->error->message = $exception->getMessage();
                } elseif ($exception instanceof GuzzleException) {
                    $response = $exception->getResponse();
                    if ($response) {
                        $responseObject->data = json_decode($response->getBody()->getContents());
                        if (isset($responseObject->data->error->error_user_msg)) {
                            $responseObject->message = $responseObject->data->error->error_user_msg;
                        } else {
                            $responseObject->message = $responseObject->data->error->message ?? $exception->getMessage();
                        }
                    } else {
                        $responseObject->data = new \stdClass;
                        $responseObject->data->error = new \stdClass;
                        $responseObject->data->error->message = $exception->getMessage();
                    }
                } else {
                    $responseObject->data = new \stdClass;
                    $responseObject->data->error = new \stdClass;
                    $responseObject->data->error->message = $exception->getMessage();
                }

                return $responseObject;
            });
    }

    // Private method to send an HTTP request (synchronous - kept for backward compatibility)
    private function sendHttpRequest($method, $url, $data = [], $headers = [])
    {
        $client = new Client;
        $responseObject = new \stdClass;

        // Track timing for performance monitoring
        $startTime = microtime(true);

        try {
            $requestOptions = [
                'headers' => $headers,
                'timeout' => 30, // 30 second timeout to prevent hanging
                'connect_timeout' => 10, // 10 second connection timeout
            ];

            if (isset($data) && $method === 'POST' || $method === 'PUT' || $method === 'DELETE') {
                $requestOptions['json'] = $data;
            }

            $response = $client->request($method, $url, $requestOptions);
            $responseObject->success = true;
            $responseObject->data = json_decode($response->getBody()->getContents());

            // Log slow requests (>2 seconds) for performance monitoring
            $duration = microtime(true) - $startTime;
            if ($duration > 2.0) {
                Log::warning('Slow WhatsApp API request', [
                    'url' => $url,
                    'method' => $method,
                    'duration' => round($duration, 2).'s',
                ]);
            }
        } catch (ConnectException $e) {
            $responseObject->success = false;
            $responseObject->data = new \stdClass;
            $responseObject->data->error = new \stdClass;
            $responseObject->data->error->message = $e->getMessage();
        } catch (GuzzleException $e) {
            $response = $e->getResponse();
            $responseObject->success = false;
            $responseObject->data = json_decode($response->getBody()->getContents());

            if (isset($responseObject->data->error->error_user_msg)) {
                $responseObject->message = $responseObject->data->error->error_user_msg;
            } else {
                $responseObject->message = $responseObject->data->error->message;
            }
        } catch (Exception $e) {
            $responseObject->success = false;
            $responseObject->data = new \stdClass;
            $responseObject->data->error = new \stdClass;
            $responseObject->data->error->message = $e->getMessage();
        }

        return $responseObject;
    }
}
