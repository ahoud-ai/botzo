<?php

namespace Modules\IntelliReply\Services;

use App\Helpers\CustomHelper;
use App\Models\Chat;
use App\Models\ChatMedia;
use App\Models\ChatTicketLog;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Setting;
use App\Services\Ai\AiProviderManager;
use App\Services\Ai\Data\AiChatRequest;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\IntelliReply\AiDisclosureService;
use App\Services\IntelliReply\AiKeyResolver;
use App\Services\IntelliReply\AiKnowledgeRetriever;
use App\Services\IntelliReply\AiPersonaPromptBuilder;
use App\Services\IntelliReply\AiReplyFallbackService;
use App\Services\IntelliReply\AiReplySafetyGuard;
use App\Services\IntelliReply\AiUsageLimiterService;
use App\Services\IntelliReply\AiUsageLogService;
use App\Services\Whatsapp\WhatsappTokenVault;
use App\Services\WhatsappService;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Mp3;
use Illuminate\Support\Facades\Log;

class AIResponseService
{
    public function suggestReply(int $organizationId, Contact $contact, ?array $apiKeyBundle = null): array
    {
        $organizationConfig = $this->getOrganizationConfig($organizationId);
        $apiKeyBundle = is_array($apiKeyBundle)
            ? $apiKeyBundle
            : $this->resolveAiKeyBundle($organizationId, $organizationConfig);
        $apiKey = $apiKeyBundle['key'] ?? null;

        if (!$apiKey) {
            return [
                'success' => false,
                'message' => __('AI assistant is not configured.'),
                'code' => 'AI_KEY_MISSING',
            ];
        }

        $lastCustomerMessage = $this->latestCustomerMessageForSuggestion($organizationId, (int) $contact->id);
        if (!$lastCustomerMessage || trim((string) ($lastCustomerMessage['message'] ?? '')) === '') {
            return [
                'success' => false,
                'message' => __('No recent customer message is available for an AI suggestion.'),
                'code' => 'NO_CUSTOMER_MESSAGE',
            ];
        }

        $closestDocument = $this->findClosestDocumentByQuery($organizationId, (string) $lastCustomerMessage['message']);

        $messages = $this->buildSuggestionPrompt(
            $organizationConfig,
            Organization::where('id', $organizationId)->value('name'),
            $contact,
            ($closestDocument['success'] ?? false) === true ? (string) ($closestDocument['document'] ?? '') : '',
            $this->conversationHistoryForSuggestion($organizationId, (int) $contact->id)
        );

        $text = $this->requestTextCompletion($organizationId, $organizationConfig, $messages, $apiKey, $apiKeyBundle, (int) $contact->id);
        if ($text === null || trim($text) === '') {
            return [
                'success' => false,
                'message' => __('Unable to generate a useful suggestion for this conversation.'),
                'code' => 'SUGGESTION_EMPTY',
            ];
        }

        return [
            'success' => true,
            'text' => trim($text),
        ];
    }

    public function handleAIResponse($chat, $receivedMessage)
    {
        // Check organization metadata
        $organizationId = $chat->organization_id;
        $aimodule = CustomHelper::isModuleEnabled('AI Assistant', $organizationId);
        $metadataArray = $this->getOrganizationConfig($organizationId);
        if (empty($metadataArray)) {
            return false;
        }

        $is_ai_active = $metadataArray['ai']['active'] ?? false;
        $enable_ai_to_respond_automatically = $metadataArray['ai']['enable_automatic_responses'] ?? false;
        $chat_ticketing = $metadataArray['tickets']['active'] ?? false;
        $contact = Contact::find($chat->contact_id);
        if (!$contact) {
            return false;
        }

        $ai_assistance_enabled = $contact->ai_assistance_enabled;
        $start_words = $metadataArray['ai']['start_keywords'] ?? "";
        $stop_words = $metadataArray['ai']['stop_keywords'] ?? "";
        
        if($aimodule){
            $lastMessage = $this->peekLastMessage((int) $chat->contact_id);
            if (!$lastMessage) {
                return false;
            }

            // Voice notes are only transcribed after every check below passes, so nothing is sent
            // to the AI provider (or paid for) when the assistant would not reply anyway.
            $isVoiceNote = $lastMessage['type'] === 'audio';
            $receivedMessage = (string) ($lastMessage['message'] ?? '');
            if (!$isVoiceNote && trim($receivedMessage) === '') {
                return false;
            }

            // Start keywords work on text only; stop keywords are checked again after transcription.
            if (!$isVoiceNote) {
                if ($this->containsKeyword($receivedMessage, $stop_words)) {
                    $contact->update(['ai_assistance_enabled' => 0]);
                    return false; // Stop further AI processing
                }

                if ($this->containsKeyword($receivedMessage, $start_words)) {
                    $ai_assistance_enabled = 1;
                    $contact->update(['ai_assistance_enabled' => $ai_assistance_enabled]);
                }
            }

                // Verify if the user has enabled automatic AI responses and
                // ensure that the AI response feature is currently inactive for this user

                if($enable_ai_to_respond_automatically && !$ai_assistance_enabled){
                    if ($chat_ticketing) {
                        // Define the parameters with which the AI should respond if ticketing workflows have been enabled
                        // Retrieve the most recent chat ticket log for the contact
                        $chatTicketLog = ChatTicketLog::where('contact_id', $chat->contact_id)
                            ->where(function($query) {
                                $query->where('description', 'Conversation was opened')
                                    ->orWhere('description', 'Conversation was moved from closed to open');
                            })
                            ->orderBy('id', 'desc')
                            ->first();

                        // Count the number of outbound chats created after the last chat ticket log
                        if ($chatTicketLog) {
                            $outboundChatsCount = Chat::where('contact_id', $chat->contact_id)
                                ->where('created_at', '>', $chatTicketLog->created_at)
                                ->where('type', 'outbound')
                                ->count();
                        } else {
                            // Handle the case where no chat ticket log exists
                            $outboundChatsCount = 0;
                        }

                        // If no outbound chats are found, enable AI assistance for the contact
                        if ($outboundChatsCount == 0) {
                            $ai_assistance_enabled = 1;
                            Contact::where('id', $chat->contact_id)
                                ->update(['ai_assistance_enabled' => $ai_assistance_enabled]);
                        }
                    } else {
                        //Define the parameters with which the AI should respond if ticketing workflows have not been enabled
                        //Check if the user has other previous messages during the last 24 hours
                        // Replies are debounced, so ignore the current burst of messages when counting.
                        $burstStart = now()->subSeconds((int) config('auto_replies.burst_window_seconds', 120));
                        $inboundChatsCount = Chat::where('contact_id', $chat->contact_id)
                            ->where('type', 'inbound')
                            ->where('created_at', '>', now()->subDay())
                            ->where('created_at', '<', $burstStart)
                            ->count();

                        // No earlier messages in the last day: a new conversation, so enable AI assistance for the contact
                        if ($inboundChatsCount === 0) {
                            $ai_assistance_enabled = 1;
                            Contact::where('id', $chat->contact_id)
                                ->update(['ai_assistance_enabled' => $ai_assistance_enabled]);
                        }
                    }
                }

                // Check if AI is active and automatic responses are enabled
                if ($is_ai_active && $ai_assistance_enabled) {
                    $usageLimiter = app(AiUsageLimiterService::class);
                    $apiKeyBundle = $this->resolveAiKeyBundle($organizationId, $metadataArray);
                    $apiKey = $apiKeyBundle['key'] ?? null;
                    $keySource = $apiKeyBundle['source'] ?? null;
                    if (!$apiKey) {
                        Log::warning('intellireply.chat.request_failed', [
                            'organization_id' => $organizationId,
                            'contact_id' => $chat->contact_id,
                            'error_code' => 'AI_KEY_MISSING',
                            'key_source' => $keySource,
                        ]);
                        return false;
                    }

                    $expectsAudioResponse = $isVoiceNote
                        && data_get($metadataArray, 'ai.allow_audio_response', false);

                    if ($expectsAudioResponse && !$usageLimiter->canUseAudio($organizationId, $keySource)) {
                        Log::warning('intellireply.limit.reached', [
                            'organization_id' => $organizationId,
                            'contact_id' => $chat->contact_id,
                            'error_code' => 'AI_LIMIT_REACHED',
                            'message_type' => 'audio',
                            'key_source' => $keySource,
                        ]);
                        return false;
                    }

                    if (!$expectsAudioResponse && !$usageLimiter->canUseText($organizationId, $keySource)) {
                        Log::warning('intellireply.limit.reached', [
                            'organization_id' => $organizationId,
                            'contact_id' => $chat->contact_id,
                            'error_code' => 'AI_LIMIT_REACHED',
                            'message_type' => 'text',
                            'key_source' => $keySource,
                        ]);
                        return false;
                    }

                    $safetyGuard = app(AiReplySafetyGuard::class);
                    if (!$safetyGuard->allowsReplyToContact((int) $organizationId, (int) $contact->id)) {
                        Log::warning('intellireply.limit.reached', [
                            'organization_id' => $organizationId,
                            'contact_id' => $chat->contact_id,
                            'error_code' => 'AI_CONTACT_HOURLY_LIMIT',
                        ]);
                        return false;
                    }

                    if ($isVoiceNote) {
                        $receivedMessage = (string) $this->transcribeVoiceNote($lastMessage['chat'], (int) $organizationId);
                        if (trim($receivedMessage) === '') {
                            return false;
                        }

                        if ($this->containsKeyword($receivedMessage, $stop_words)) {
                            $contact->update(['ai_assistance_enabled' => 0]);
                            return false;
                        }
                    }

                    // Documents are optional: without a match the assistant answers from the persona
                    // and hands the chat to the team when it cannot answer.
                    $closestDocument = $this->findClosestDocumentByQuery($organizationId, $receivedMessage);
                    $documentation = ($closestDocument['success'] ?? false) === true ? (string) ($closestDocument['document'] ?? '') : null;
                    $context = $this->buildReplyContext($chat, $metadataArray, (int) $organizationId, $documentation);

                    // Pass the context and received message to the chat method
                    try {
                        $res = $this->chat($organizationId, $lastMessage['type'], $context, $apiKeyBundle);
                    } catch (\Throwable $exception) {
                        report($exception);
                        $res = null;
                    }

                    // The model failed, timed out or returned nothing: apologize and hand over instead of going silent.
                    if (!$res || trim((string) ($res['text'] ?? '')) === '') {
                        return app(AiReplyFallbackService::class)->handle($contact, 'model_unavailable');
                    }

                    $isHandoff = app(AiPersonaPromptBuilder::class)->asksForHandoff((string) $res['text']);
                    app(AiUsageLogService::class)->record(
                        (int) $organizationId,
                        (int) $contact->id,
                        AiUsageLogService::PURPOSE_AUTO_REPLY,
                        $isHandoff ? 'handoff' : 'replied',
                        (array) ($res['usage'] ?? []),
                        $keySource
                    );

                    if ($isHandoff) {
                        $expectsAudioResponse
                            ? $usageLimiter->consumeAudio($organizationId, $keySource)
                            : $usageLimiter->consumeText($organizationId, $keySource);
                        Log::info('intellireply.knowledge_gap.handoff', [
                            'organization_id' => $organizationId,
                            'contact_id' => $contact->id,
                            'had_documentation' => $documentation !== null,
                        ]);

                        return app(AiReplyFallbackService::class)->handOff(
                            $contact,
                            (string) (data_get($metadataArray, 'ai.unknown_answer_text') ?: config('intellireply.unknown_answer.text', '')),
                            (int) config('intellireply.unknown_answer.pause_minutes', 720),
                            'ai_knowledge_gap'
                        );
                    }

                    if ($res) {
                        $responseType = $expectsAudioResponse && !empty(data_get($res, 'audio.data'))
                            ? 'audio'
                            : 'text';

                        // Bot disclosure, once per conversation (WhatsApp policy for AI replies).
                        $disclosureService = app(AiDisclosureService::class);
                        $disclosure = $disclosureService->pendingFor(
                            (int) $organizationId,
                            (int) $contact->id,
                            $metadataArray,
                            Organization::where('id', $organizationId)->value('name')
                        );

                        if ($responseType === 'text') {
                            $reply = $disclosure !== null ? $disclosure."\n\n".$res['text'] : $res['text'];
                            $sent = $this->initializeWhatsappService($organizationId)->sendMessage($contact->uuid, $reply);
                            if ($disclosure !== null && ($sent->success ?? false) === true) {
                                $disclosureService->markDisclosed((int) $organizationId, (int) $contact->id);
                            }
                            $usageLimiter->consumeText($organizationId, $keySource);
                        } else if ($responseType === 'audio') {
                            if ($disclosure !== null
                                && ($this->initializeWhatsappService($organizationId)->sendMessage($contact->uuid, $disclosure)->success ?? false) === true) {
                                $disclosureService->markDisclosed((int) $organizationId, (int) $contact->id);
                            }
                            $file = $this->saveBase64Audio($res['audio']['data'], $organizationId, $res['audio']['id']);
                            $this->initializeWhatsappService($organizationId)->sendMedia($contact->uuid, 'audio', $res['audio']['id'], $file['filePath'], $file['mediaUrl'], $file['location']);
                            $usageLimiter->consumeAudio($organizationId, $keySource);
                        }
                        $safetyGuard->recordReplyToContact((int) $organizationId, (int) $contact->id);
                        return true; // Indicates that an AI response was sent
                    }
                }
            //}
        }

        return false; // Indicates that no AI response was sent
    }

    public function chat($organizationId, $type, $context, ?array $apiKeyBundle = null)
    {
        $organizationConfig = $this->getOrganizationConfig($organizationId);
        $apiKeyBundle = is_array($apiKeyBundle)
            ? $apiKeyBundle
            : $this->resolveAiKeyBundle($organizationId, $organizationConfig);
        $apiKey = $apiKeyBundle['key'] ?? null;
        if (!$apiKey) {
            Log::warning('intellireply.chat.request_failed', [
                'organization_id' => $organizationId,
                'error_code' => 'AI_KEY_MISSING',
                'key_source' => $apiKeyBundle['source'] ?? null,
            ]);
            return null;
        }

        $model = (string) data_get($organizationConfig, 'ai.model', 'gpt-4o-mini');
        $wantsAudio = in_array($model, $this->audioResponseModels(), true);
        $timeout = max(5, (int) config('intellireply.request_timeout_seconds', 20)) + ($wantsAudio ? 15 : 0);

        try {
            $result = app(AiProviderManager::class)
                ->chat(data_get($organizationConfig, 'ai.provider'), $apiKey)
                ->chat(new AiChatRequest(
                    model: $model,
                    messages: $context,
                    maxTokens: app(AiReplySafetyGuard::class)->maxOutputTokens((int) data_get($organizationConfig, 'ai.max_tokens', 512)),
                    temperature: (float) data_get($organizationConfig, 'ai.temperature', 0.7),
                    timeoutSeconds: $timeout,
                    audioVoice: $wantsAudio ? (string) data_get($organizationConfig, 'ai.voice') : null,
                ));
        } catch (AiProviderException $exception) {
            Log::warning('intellireply.chat.request_failed', [
                'organization_id' => $organizationId,
                'error_code' => 'AI_PROVIDER_REQUEST_FAILED',
                'status' => $exception->status,
                'provider_error' => $exception->errorCode,
                'key_source' => $apiKeyBundle['source'] ?? null,
            ]);
            return null;
        }

        return [
            'type' => $result->hasAudio() ? 'audio' : 'text',
            'text' => $result->text,
            'audio' => [
                'id' => $result->audioId,
                'data' => $result->audioData,
                'transcript' => $result->text,
            ],
            'usage' => [
                'provider' => $result->provider,
                'model' => $result->model,
                'input_tokens' => $result->inputTokens,
                'output_tokens' => $result->outputTokens,
            ],
        ];
    }

    private function latestCustomerMessageForSuggestion(int $organizationId, int $contactId): ?array
    {
        $chat = Chat::query()
            ->where('organization_id', $organizationId)
            ->where('contact_id', $contactId)
            ->where('type', 'inbound')
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->first();

        if (!$chat) {
            return null;
        }

        $metadata = json_decode($chat->metadata, true);
        if (!is_array($metadata)) {
            return null;
        }

        $text = $this->chatTextFromMetadata($metadata);
        if ($text !== '') {
            return [
                'type' => 'text',
                'message' => $text,
            ];
        }

        if (($metadata['type'] ?? null) === 'audio') {
            $audio = ChatMedia::where('id', $chat->media_id)->first();
            if (!$audio) {
                return null;
            }

            $transcriptionResponse = $this->transcribeAudioToText($organizationId, (string) $audio->path);
            if (($transcriptionResponse['success'] ?? false) === true && trim((string) ($transcriptionResponse['text'] ?? '')) !== '') {
                return [
                    'type' => 'audio',
                    'message' => trim((string) $transcriptionResponse['text']),
                ];
            }
        }

        return null;
    }

    private function conversationHistoryForSuggestion(int $organizationId, int $contactId): array
    {
        return Chat::query()
            ->where('organization_id', $organizationId)
            ->where('contact_id', $contactId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->take(10)
            ->get()
            ->reverse()
            ->map(function (Chat $chat) {
                $metadata = json_decode($chat->metadata, true);
                if (!is_array($metadata)) {
                    return null;
                }

                $text = $this->chatTextFromMetadata($metadata);
                if ($text === '') {
                    return null;
                }

                return [
                    'role' => $chat->type === 'outbound' ? 'agent' : 'customer',
                    'message' => $text,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function chatTextFromMetadata(array $metadata): string
    {
        $type = (string) ($metadata['type'] ?? '');
        if ($type === 'text') {
            return trim((string) data_get($metadata, 'text.body', ''));
        }

        if ($type === 'button') {
            return trim((string) data_get($metadata, 'button.payload', ''));
        }

        if ($type === 'interactive') {
            $interactiveType = (string) data_get($metadata, 'interactive.type', '');
            if ($interactiveType === 'button_reply') {
                return trim((string) data_get($metadata, 'interactive.button_reply.title', ''));
            }

            if ($interactiveType === 'list_reply') {
                return trim((string) data_get($metadata, 'interactive.list_reply.title', ''));
            }
        }

        return '';
    }

    private function buildSuggestionPrompt(array $organizationConfig, ?string $organizationName, Contact $contact, string $document, array $history): array
    {
        $conversation = collect($history)
            ->map(fn (array $line) => strtoupper((string) $line['role']).': '.(string) $line['message'])
            ->implode("\n");

        $customerName = trim((string) ($contact->full_name ?: $contact->first_name ?: $contact->phone ?: __('customer')));

        return [
            [
                'role' => 'system',
                'content' => app(AiPersonaPromptBuilder::class)->suggestionSystemPrompt($organizationConfig, $organizationName),
            ],
            [
                'role' => 'user',
                'content' => "Customer: {$customerName}\n\nDocumentation:\n"
                    .(trim($document) !== '' ? mb_substr($document, 0, 8000) : 'No documents were provided.')
                    ."\n\nRecent conversation:\n"
                    .($conversation !== '' ? $conversation : 'No previous text messages.')
                    ."\n\nDraft the next reply for the agent to review.",
            ],
        ];
    }

    private function requestTextCompletion(int $organizationId, array $organizationConfig, array $messages, string $apiKey, array $apiKeyBundle, ?int $contactId = null): ?string
    {
        try {
            $result = app(AiProviderManager::class)
                ->chat(data_get($organizationConfig, 'ai.provider'), $apiKey)
                ->chat(new AiChatRequest(
                    model: (string) data_get($organizationConfig, 'ai.model', 'gpt-4o-mini'),
                    messages: $messages,
                    maxTokens: app(AiReplySafetyGuard::class)->maxOutputTokens((int) data_get($organizationConfig, 'ai.max_tokens', 512)),
                    temperature: (float) data_get($organizationConfig, 'ai.temperature', 0.7),
                    timeoutSeconds: 30,
                ));
        } catch (AiProviderException $exception) {
            Log::warning('intellireply.chat_suggestion.request_failed', [
                'organization_id' => $organizationId,
                'error_code' => 'AI_PROVIDER_REQUEST_FAILED',
                'status' => $exception->status,
                'key_source' => $apiKeyBundle['source'] ?? null,
            ]);

            return null;
        }

        app(AiUsageLogService::class)->record($organizationId, $contactId, AiUsageLogService::PURPOSE_SUGGESTION, 'drafted', [
            'provider' => $result->provider,
            'model' => $result->model,
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
        ], $apiKeyBundle['source'] ?? null);

        return trim($result->text) !== '' ? trim($result->text) : null;
    }

    protected function saveBase64Audio($base64Data, $organizationId, $fileName)
    {
        $storage = Setting::where('key', 'storage_system')->first()->value;
        $audioData = base64_decode($base64Data);

        // Create a temporary stream and write the audio data into it
        $stream = fopen('php://temp', 'rb+');
        fwrite($stream, $audioData);
        rewind($stream);

        if($storage === 'local'){
            // Get the public URL for accessing the audio file
            $filePath = 'public/audios/' . $fileName . '.mp3';
            $file = \Storage::disk('local')->put($filePath, $stream);
            $mediaUrl = rtrim(config('app.url'), '/') . '/media/public/audios/' . $fileName . '.mp3';
        } else if($storage === 'aws') {
            $filePath = 'uploads/media/sent/'  . $organizationId . '/' . $fileName . '.mp3';
            $file = \Storage::disk('s3')->put($filePath, $stream);
            $mediaUrl = \Storage::disk('s3')->url($filePath);
        }

        // Close the temporary stream
        fclose($stream);

        return [
            'filePath' => $storage == 'aws' ? $mediaUrl : $filePath,
            'mediaUrl' => $mediaUrl,
            'location' => $storage == 'aws' ? 'amazon' : 'local'
        ];
    }

    protected function convertToMp3($filePath)
    {
        // Get the file extension
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);

        // Check if the file is already in mp3, mpeg, or wav format
        if (in_array(strtolower($fileExtension), ['mp3', 'mpeg', 'wav'])) {
            $base64Data = base64_encode(file_get_contents($filePath));

            return [
                'data' => $base64Data,
                'format' => $fileExtension == 'mpeg' || $fileExtension == 'mp3' ? 'mp3' : 'wav',
            ];
        }

        // Initialize FFmpeg
        //$ffmpeg = FFMpeg::create();
        $ffmpeg = FFMpeg::create(array(
            'ffmpeg.binaries' => config('ffmpeg.ffmpeg.binaries'),
            'ffprobe.binaries' => config('ffmpeg.ffprobe.binaries'),
            'timeout' => config('ffmpeg.timeout'),
            'threads' => config('ffmpeg.threads'),
        ));
        $audio = $ffmpeg->open($filePath);
        $format = new Mp3();
        $tempFile = tempnam(sys_get_temp_dir(), 'audio') . '.mp3';
        $audio->save($format, $tempFile);
        $base64Data = base64_encode(file_get_contents($tempFile));
        unlink($tempFile);

        return [
            'data' => $base64Data,
            'format' => 'mp3',
        ];
    }

    protected function transcribeAudioToText($organizationId, $audioPath)
    {
        if (!is_string($audioPath) || trim($audioPath) === '') {
            return [
                'success' => false,
                'message' => __('Invalid audio path'),
                'text' => null,
            ];
        }

        $audioBinary = $this->readAudioBinary($audioPath);
        if ($audioBinary === null) {
            Log::warning('intellireply.audio.path_unreadable', [
                'organization_id' => $organizationId,
                'error_code' => 'AUDIO_PATH_UNREADABLE',
                'audio_path' => $audioPath,
            ]);

            return [
                'success' => false,
                'message' => __('Invalid audio path'),
                'text' => null,
            ];
        }

        $organizationConfig = $this->getOrganizationConfig($organizationId);
        $apiKeyBundle = $this->resolveAiKeyBundle($organizationId, $organizationConfig);
        $apiKey = $apiKeyBundle['key'] ?? null;
        if (!$apiKey) {
            return [
                'success' => false,
                'message' => __('AI key missing'),
                'text' => null,
            ];
        }

        $lastStatus = null;
        $lastError = null;
        foreach ($this->transcriptionModelCandidates() as $transcriptionModel) {
            try {
                $response = \Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey
                ])->attach('file', $audioBinary, 'audio.mp3')
                ->timeout(45)->retry(1, 250)->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => $transcriptionModel,
                    'language' => 'en'
                ]);
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::notice('intellireply.audio.transcription_model_failed', [
                    'organization_id' => $organizationId,
                    'error_code' => 'OPENAI_TRANSCRIPTION_FAILED',
                    'model' => $transcriptionModel,
                    'key_source' => $apiKeyBundle['source'] ?? null,
                    'error' => $lastError,
                ]);
                continue;
            }

            if ($response->successful()) {
                $responseArray = $response->json();
                if (isset($responseArray['text']) && is_string($responseArray['text'])) {
                    return [
                        'success' => true,
                        'message' => __('Transcription successful'),
                        'text' => $responseArray['text']
                    ];
                }
            }

            $lastStatus = $response->status();
            Log::notice('intellireply.audio.transcription_model_failed', [
                'organization_id' => $organizationId,
                'error_code' => 'OPENAI_TRANSCRIPTION_FAILED',
                'model' => $transcriptionModel,
                'status' => $lastStatus,
                'key_source' => $apiKeyBundle['source'] ?? null,
            ]);
        }

        Log::warning('intellireply.audio.transcription_failed', [
            'organization_id' => $organizationId,
            'error_code' => 'OPENAI_TRANSCRIPTION_FAILED',
            'status' => $lastStatus,
            'key_source' => $apiKeyBundle['source'] ?? null,
            'error' => $lastError,
        ]);

        return [
            'success' => false,
            'message' => __('Unable to transcribe audio'),
            'text' => null,
        ];
    }

    /**
     * The contact's latest message without any paid processing: text is returned as is,
     * a voice note is returned with its chat so it can be transcribed later.
     *
     * @return array{type:string,message?:string,chat?:Chat}|null
     */
    protected function peekLastMessage(int $contactId): ?array
    {
        $chat = Chat::where('contact_id', $contactId)
            ->orderBy('created_at', 'desc')
            ->first();
        if (!$chat) {
            return null;
        }

        $metadata = json_decode($chat->metadata, true);
        if (!is_array($metadata) || !isset($metadata['type'])) {
            return null;
        }

        if ($metadata['type'] === 'text') {
            return [
                'type' => 'text',
                'message' => $this->recentCustomerText($contactId) ?: ($metadata['text']['body'] ?? null),
            ];
        }

        if ($metadata['type'] === 'button') {
            return [
                'type' => 'text',
                'message' => $metadata['button']['payload'] ?? null,
            ];
        }

        if ($metadata['type'] === 'audio') {
            return [
                'type' => 'audio',
                'chat' => $chat,
            ];
        }

        return null;
    }

    protected function transcribeVoiceNote(Chat $voiceChat, int $organizationId): ?string
    {
        $audio = ChatMedia::where('id', $voiceChat->media_id)->first();
        if (!$audio) {
            return null;
        }

        $transcription = $this->transcribeAudioToText($organizationId, (string) $audio->path);

        return ($transcription['success'] ?? false) === true ? (string) $transcription['text'] : null;
    }

    private function containsKeyword(string $text, ?string $keywords): bool
    {
        foreach (array_map('trim', explode(',', (string) $keywords)) as $keyword) {
            if ($keyword !== '' && stripos($text, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    private function buildReplyContext(Chat $chat, array $metadataArray, int $organizationId, ?string $documentation): array
    {
        // Retrieve the last 5 messages for the contact
        $messages = Chat::where('contact_id', $chat->contact_id)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        // Extract the message body from the metadata column and convert to an array
        $conversationHistory = $messages->map(function ($message) use ($organizationId, $metadataArray) {
            // Decode the JSON in the metadata column
            $metadata = json_decode($message->metadata, true);

            // Check if type is 'text' and return the body if it exists
            if (isset($metadata['type']) && ($metadata['type'] === 'text' || $metadata['type'] === 'audio')) {
                $role = ($message->type === 'outbound') ? 'assistant' : 'user'; //outbound => user, inbound => assistant

                if($metadata['type'] === 'text'){
                    return [
                        "role" => $role,
                        "content" => $metadata['text']['body'] ?? null
                    ];
                } else if($metadata['type'] === 'audio' && data_get($metadataArray, 'ai.allow_audio_response', false)){
                    $audio = ChatMedia::where('id', $message->media_id)->first();

                    if($role == 'user'){
                          if($audio->location == 'local'){
                            $filePath = storage_path("app/{$audio->path}");

                            if(file_exists($filePath)){
                                $audioFile = $this->convertToMp3($filePath);

                                return [
                                    "role" => $role,
                                    "content" => [
                                        [
                                            "type" => "input_audio",
                                            "input_audio" => [
                                                "data" => $audioFile['data'],
                                                "format" => $audioFile['format'],
                                            ]
                                        ]
                                    ]
                                ];
                            }
                        } else if($audio->location == 'amazon'){
                            $fileUrl = $audio->path;
                              $parsedUrl = parse_url($audio->path);
                            $filePath = ltrim($parsedUrl['path'], '/');

                            if (\Storage::disk('s3')->exists($filePath)) {
                                $audioFile = $this->convertToMp3($fileUrl);

                                return [
                                    "role" => $role,
                                    "content" => [
                                        [
                                            "type" => "input_audio",
                                            "input_audio" => [
                                                "data" => $audioFile['data'],
                                                "format" => $audioFile['format'],
                                            ]
                                        ]
                                    ]
                                ];
                            }
                        }
                    } else {
                        //Check if audio name starts with audio_
                        if(isset($audio->name)){
                            if (str_starts_with($audio->name, 'audio_')) {
                                return [
                                    'role' => $role,
                                    'audio' => [
                                        'id' => $audio->name,
                                    ],
                                ];
                            }
                        }
                    }
                }
            }

            return null;
        })->filter()
        ->toArray();

        $conversationHistory = array_reverse($conversationHistory); // Inverte a ordem para manter a cronologia

        // Build the context by looping through the conversation history and concatenating the messages
        $context = [
            [
                "role" => "system", 
                "content" => app(AiPersonaPromptBuilder::class)->replySystemPrompt(
                    $metadataArray,
                    Organization::where('id', $organizationId)->value('name'),
                    $documentation
                )
            ]
        ]; // Initialize context with a system message

        // Append the user and assistant messages to the context
        foreach ($conversationHistory as $message) {
            if (!empty($message['content'])) {
                $context[] = [
                    "role" => $message['role'],
                    "content" => $message['content']
                ];
            }
        }

        return $context;
    }

    /**
     * The customer's trailing text messages since our last reply (replies are debounced,
     * so "hi" + "how much is X?" must be answered together).
     */
    private function recentCustomerText(int $contactId): string
    {
        $lines = [];

        foreach (Chat::where('contact_id', $contactId)->orderByDesc('id')->take(5)->get(['type', 'metadata']) as $recent) {
            $recentMetadata = json_decode((string) $recent->metadata, true);
            if ($recent->type !== 'inbound' || ($recentMetadata['type'] ?? null) !== 'text') {
                break;
            }

            array_unshift($lines, trim((string) data_get($recentMetadata, 'text.body', '')));
        }

        return trim(implode("\n", array_filter($lines)));
    }

    private function readAudioBinary(string $audioPath): ?string
    {
        $path = trim($audioPath);
        if ($path === '') {
            return null;
        }

        // Normalize malformed local paths that accidentally prefix storage path before an URL.
        $httpPos = strpos($path, 'http://');
        $httpsPos = strpos($path, 'https://');
        $urlPos = $httpPos !== false ? $httpPos : $httpsPos;
        if ($urlPos !== false && $urlPos > 0) {
            $path = substr($path, $urlPos);
        }

        // Resolve local media URL format: https://domain/media/public/<file>
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $resolvedLocal = $this->resolveStoragePathFromMediaUrl($path);
            if ($resolvedLocal !== null && file_exists($resolvedLocal)) {
                $contents = @file_get_contents($resolvedLocal);
                return $contents === false ? null : $contents;
            }

            if (! $this->isTrustedAudioUrl($path)) {
                Log::warning('intellireply.audio_url_rejected', [
                    'host' => parse_url($path, PHP_URL_HOST),
                ]);

                return null;
            }

            $response = \Http::timeout(30)->retry(1, 250)->get($path);
            if ($response->successful()) {
                return $response->body();
            }

            return null;
        }

        // Absolute path
        if (file_exists($path)) {
            $contents = @file_get_contents($path);
            return $contents === false ? null : $contents;
        }

        // Relative storage path (e.g., public/..., uploads/...)
        $storagePath = storage_path('app/' . ltrim($path, '/'));
        if (file_exists($storagePath)) {
            $contents = @file_get_contents($storagePath);
            return $contents === false ? null : $contents;
        }

        return null;
    }

    private function resolveStoragePathFromMediaUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }

        // Local media controller serves files under /media/<storage-relative-path>
        if (str_starts_with($path, '/media/')) {
            $relative = ltrim(substr($path, strlen('/media/')), '/');
            if ($relative !== '') {
                return storage_path('app/' . $relative);
            }
        }

        return null;
    }

    private function isTrustedAudioUrl(string $url): bool
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https' || $host === '') {
            return false;
        }

        $trustedHosts = [];
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (is_string($appHost) && $appHost !== '') {
            $trustedHosts[] = strtolower($appHost);
        }

        foreach ([
            config('filesystems.disks.s3.url'),
            config('filesystems.disks.s3.endpoint'),
        ] as $configuredUrl) {
            $configuredHost = parse_url((string) $configuredUrl, PHP_URL_HOST);
            if (is_string($configuredHost) && $configuredHost !== '') {
                $trustedHosts[] = strtolower($configuredHost);
            }
        }

        $bucket = trim((string) config('filesystems.disks.s3.bucket'));
        $region = trim((string) config('filesystems.disks.s3.region'));
        if ($bucket !== '' && $region !== '') {
            $trustedHosts[] = strtolower($bucket . '.s3.' . $region . '.amazonaws.com');
        }
        if ($bucket !== '') {
            $trustedHosts[] = strtolower($bucket . '.s3.amazonaws.com');
        }

        return in_array($host, array_values(array_unique(array_filter($trustedHosts))), true);
    }

    protected function findClosestDocumentByQuery($organizationId, $query){
        $organizationConfig = $this->getOrganizationConfig($organizationId);
        $apiKeyBundle = $this->resolveAiKeyBundle($organizationId, $organizationConfig);
        $knowledge = app(AiKnowledgeRetriever::class)->retrieve((int) $organizationId, $organizationConfig, $apiKeyBundle['key'] ?? null, (string) $query);

        return [
            'success' => $knowledge !== null,
            'document' => $knowledge,
        ];
    }



    protected function initializeWhatsappService($organizationId)
    {
        $config = $this->getOrganizationConfig($organizationId);
        $accessToken = (new WhatsappTokenVault())->resolveTokenFromMetadata($config);
        $apiVersion = Setting::where('key', 'graph_api_version')->value('value')
            ?: env('GRAPH_API_VERSION', 'v21.0');
        $appId = $config['whatsapp']['app_id'] ?? null;
        $phoneNumberId = $config['whatsapp']['phone_number_id'] ?? null;
        $wabaId = $config['whatsapp']['waba_id'] ?? null;

        return new WhatsappService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $organizationId);
    }

    private function getOrganizationConfig(int $organizationId): array
    {
        $metadata = Organization::where('id', $organizationId)->value('metadata');
        $decoded = $metadata ? json_decode($metadata, true) : [];
        return is_array($decoded) ? $decoded : [];
    }

    private function resolveAiKeyBundle(int $organizationId, array $organizationConfig): array
    {
        return app(AiKeyResolver::class)->resolveForOrganization(
            $organizationConfig,
            data_get($organizationConfig, 'ai.key_source', 'auto'),
            $organizationId
        );
    }

    private function audioResponseModels(): array
    {
        $models = config('intellireply.audio_response_models', ['gpt-audio-1.5']);
        if (!is_array($models)) {
            return ['gpt-audio-1.5'];
        }

        $normalized = collect($models)
            ->map(fn ($model) => is_string($model) ? trim($model) : '')
            ->filter(fn ($model) => $model !== '')
            ->unique()
            ->values()
            ->all();

        return empty($normalized) ? ['gpt-audio-1.5'] : $normalized;
    }

    private function transcriptionModelCandidates(): array
    {
        $default = config('intellireply.default_transcription_model', 'gpt-4o-mini-transcribe');
        $fallbacks = config('intellireply.transcription_model_fallbacks', [$default, 'whisper-1']);
        if (!is_array($fallbacks)) {
            $fallbacks = [$default, 'whisper-1'];
        }

        $candidates = [$default];
        foreach ($fallbacks as $fallbackModel) {
            $candidates[] = $fallbackModel;
        }

        $normalized = collect($candidates)
            ->map(fn ($model) => is_string($model) ? trim($model) : '')
            ->filter(fn ($model) => $model !== '')
            ->unique()
            ->values()
            ->all();

        if (empty($normalized)) {
            return ['gpt-4o-mini-transcribe', 'whisper-1'];
        }

        if (!in_array('whisper-1', $normalized, true)) {
            $normalized[] = 'whisper-1';
        }

        return $normalized;
    }





}
