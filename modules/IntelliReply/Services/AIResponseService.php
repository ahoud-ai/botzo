<?php

namespace Modules\IntelliReply\Services;

use App\Helpers\CustomHelper;
use App\Models\Chat;
use App\Models\ChatMedia;
use App\Models\ChatTicketLog;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Setting;
use App\Services\IntelliReply\AiKeyResolver;
use App\Services\IntelliReply\AiUsageLimiterService;
use App\Services\Whatsapp\WhatsappTokenVault;
use App\Services\WhatsappService;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Mp3;
use Illuminate\Support\Facades\Log;
use Modules\IntelliReply\Models\Document;
use OpenAI;

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
        if (($closestDocument['success'] ?? false) !== true || empty($closestDocument['document'])) {
            return [
                'success' => false,
                'message' => __('No knowledge base document matched this conversation.'),
                'code' => 'KNOWLEDGE_NOT_FOUND',
            ];
        }

        $messages = $this->buildSuggestionPrompt(
            $contact,
            (string) $closestDocument['document'],
            $this->conversationHistoryForSuggestion($organizationId, (int) $contact->id)
        );

        $text = $this->requestTextCompletion($organizationId, $organizationConfig, $messages, $apiKey, $apiKeyBundle);
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
            $lastMessage = $this->extractLastMessage($organizationId, $chat->contact_id);
            if (!$lastMessage || empty($lastMessage['message'])) {
                return false;
            }

            $receivedMessage = $lastMessage['message'];
            $closestDocument = $this->findClosestDocumentByQuery($organizationId, $receivedMessage);
            if (($closestDocument['success'] ?? false) !== true || empty($closestDocument['document'])) {
                return false;
            }
            
            /*if($closestDocument['success'] === false){
                return 'Sorry but I don\'t have any information about this.';
            } else {*/
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
                        "content" => "You are a customer support service AI Chatbot. You only provide answers that can be strictly found in context or documentation. If the user asks a question that lacks sufficient information or if it is not covered in the documentation, reply with 'Sorry, I don't have information about this. Could you specify what you’d like more information about?'. Here is the documentation: " . $closestDocument['document']
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

                //\Log::info($context);

                // Check if the received message contains any stop words
                if ($stop_words) {
                    $stopWordsArray = array_map('trim', explode(',', $stop_words));
                    // Loop through each stop word and check if it exists in the received message
                    foreach ($stopWordsArray as $stopWord) {
                        if (stripos($receivedMessage, $stopWord) !== false) {
                            $ai_assistance_enabled = 0;
                            $contact->update(['ai_assistance_enabled' => $ai_assistance_enabled]);
                            return false; // Stop further AI processing
                        }
                    }
                }

                // Check if the received message contains any start words
                if ($start_words) {
                    $startWordsArray = array_map('trim', explode(',', $start_words));
                    // Loop through each stop word and check if it exists in the received message
                    foreach ($startWordsArray as $startWord) {
                        if (stripos($receivedMessage, $startWord) !== false) {
                            $ai_assistance_enabled = 1;
                            $contact->update(['ai_assistance_enabled' => $ai_assistance_enabled]);
                            //return true;
                        }
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
                        $inboundChatsCount = Chat::where('contact_id', $chat->contact_id)
                            ->where('type', 'inbound')
                            ->where('created_at', '>', now()->subDay())
                            ->count();

                        // If no outbound chats are found, enable AI assistance for the contact
                        if ($inboundChatsCount <= 1) {
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

                    $expectsAudioResponse = $lastMessage['type'] == 'audio'
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

                    // Pass the context and received message to the chat method
                    $res = $this->chat($organizationId, $lastMessage['type'], $context, $apiKeyBundle);
            
                    if ($res) {
                        $responseType = $expectsAudioResponse && !empty(data_get($res, 'audio.data'))
                            ? 'audio'
                            : 'text';

                        if ($responseType === 'text') {
                            $this->initializeWhatsappService($organizationId)->sendMessage($contact->uuid, $res['text']);
                            $usageLimiter->consumeText($organizationId, $keySource);
                        } else if ($responseType === 'audio') {
                            $file = $this->saveBase64Audio($res['audio']['data'], $organizationId, $res['audio']['id']);
                            $this->initializeWhatsappService($organizationId)->sendMedia($contact->uuid, 'audio', $res['audio']['id'], $file['filePath'], $file['mediaUrl'], $file['location']);
                            $usageLimiter->consumeAudio($organizationId, $keySource);
                        }
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

        $model = data_get($organizationConfig, 'ai.model', 'gpt-4o-mini');
        $maxTokens = (int) data_get($organizationConfig, 'ai.max_tokens', 512);
        $temperature = (float) data_get($organizationConfig, 'ai.temperature', 0.7);
        $audioResponseModels = $this->audioResponseModels();

        if (!in_array((string) $model, $audioResponseModels, true)) {
            $response = \Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey
            ])->timeout(30)->retry(1, 250)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => $context,
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
            ]);
        } else {
            $response = \Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey
            ])->timeout(45)->retry(1, 250)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                "modalities" => ["text", "audio"],
                "audio" => [
                    "voice" => data_get($organizationConfig, 'ai.voice'),
                    "format" => "mp3"
                ],
                'messages' => $context,
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
            ]);
        }
        
        if (!$response->successful()) {
            Log::warning('intellireply.chat.request_failed', [
                'organization_id' => $organizationId,
                'error_code' => 'OPENAI_REQUEST_FAILED',
                'status' => $response->status(),
                'key_source' => $apiKeyBundle['source'] ?? null,
            ]);
            return null;
        }

        $responseArray = $response->json();

        //\Log::info($responseArray);

        // Access the first choice in the response (choices array)
        if (isset($responseArray['choices']) && count($responseArray['choices']) > 0) {
            $audioId = NULL;
            $encodedAudioFile = NULL;

            if(isset($responseArray['choices'][0]['message']['content'])){
                $chatResponse = $responseArray['choices'][0]['message']['content'];
                $type = 'text';
            }

            if(isset($responseArray['choices'][0]['message']['audio']['transcript'])){
                $chatResponse = $responseArray['choices'][0]['message']['audio']['transcript'];
                $audioId = $responseArray['choices'][0]['message']['audio']['id'];
                $encodedAudioFile = $responseArray['choices'][0]['message']['audio']['data'];
                $type = 'audio';
            }

            return [
                'type' => $type,
                'text' => $chatResponse,
                'audio' => [
                    'id' => $audioId,
                    'data' => $encodedAudioFile,
                    'transcript' => $chatResponse
                ]
            ];
        }
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

    private function buildSuggestionPrompt(Contact $contact, string $document, array $history): array
    {
        $conversation = collect($history)
            ->map(fn (array $line) => strtoupper((string) $line['role']).': '.(string) $line['message'])
            ->implode("\n");

        $customerName = trim((string) ($contact->full_name ?: $contact->first_name ?: $contact->phone ?: __('customer')));

        return [
            [
                'role' => 'system',
                'content' => 'You draft WhatsApp replies for a human support agent. Use only the provided documentation and conversation context. Match the customer language. Return one concise editable draft only, with no greetings unless appropriate and no explanations.',
            ],
            [
                'role' => 'user',
                'content' => "Customer: {$customerName}\n\nDocumentation:\n"
                    .mb_substr($document, 0, 8000)
                    ."\n\nRecent conversation:\n"
                    .($conversation !== '' ? $conversation : 'No previous text messages.')
                    ."\n\nDraft the next reply for the agent to review.",
            ],
        ];
    }

    private function requestTextCompletion(int $organizationId, array $organizationConfig, array $messages, string $apiKey, array $apiKeyBundle): ?string
    {
        $model = data_get($organizationConfig, 'ai.model', 'gpt-4o-mini');
        $maxTokens = (int) data_get($organizationConfig, 'ai.max_tokens', 512);
        $temperature = (float) data_get($organizationConfig, 'ai.temperature', 0.7);

        $response = \Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$apiKey,
        ])->timeout(30)->retry(1, 250)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
        ]);

        if (!$response->successful()) {
            Log::warning('intellireply.chat_suggestion.request_failed', [
                'organization_id' => $organizationId,
                'error_code' => 'OPENAI_REQUEST_FAILED',
                'status' => $response->status(),
                'key_source' => $apiKeyBundle['source'] ?? null,
            ]);

            return null;
        }

        $content = data_get($response->json(), 'choices.0.message.content');

        return is_string($content) ? trim($content) : null;
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

    protected function extractLastMessage($organizationId, $contactId){
        $chat = Chat::where('contact_id', $contactId)
                ->orderBy('created_at', 'desc') // Order by creation time
                ->first();
        if (!$chat) {
            return null;
        }

        $metadata = json_decode($chat->metadata, true);
        if (!is_array($metadata) || !isset($metadata['type'])) {
            return null;
        }

        if($metadata['type'] === 'text'){
            return [
                'type' => 'text',
                'message' => $metadata['text']['body'] ?? null
            ];
        }

        if($metadata['type'] === 'button'){
            return [
                'type' => 'text',
                'message' => $metadata['button']['payload'] ?? null
            ];
        }

        if($metadata['type'] === 'audio'){
            $audio = ChatMedia::where('id', $chat->media_id)->first();
            if (!$audio) {
                return null;
            }

            if($audio->location === 'local'){
                $transcriptionResponse = $this->transcribeAudioToText($organizationId, (string) $audio->path);
            } else if($audio->location === 'amazon') {
                $transcriptionResponse = $this->transcribeAudioToText($organizationId, (string) $audio->path);
            }

            if ($transcriptionResponse['success']) {
                return [
                    'type' => 'audio',
                    'message' => $transcriptionResponse['text']
                ];
            }
        }

        return null;
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
        $api_key = $apiKeyBundle['key'] ?? null;
        if (!$api_key) {
            Log::warning('intellireply.retrieval.failed', [
                'organization_id' => $organizationId,
                'error_code' => 'AI_KEY_MISSING',
                'key_source' => $apiKeyBundle['source'] ?? null,
            ]);
            return [
                'success' => false,
                'message' => __('AI key missing'),
            ];
        }

        $client = OpenAI::client($api_key);

        try {
            // Generate embedding for query
            [$queryEmbedding] = $this->createEmbeddingWithFallback($client, (string) $query, $organizationConfig);

            // Find the closest document
            $documents = Document::where('organization_id', $organizationId)->get();
            $closestDocument = null;
            $closestDistance = PHP_FLOAT_MAX;

            foreach ($documents as $document) {
                $documentEmbeddings = json_decode($document->embeddings, true);
                if (!is_array($documentEmbeddings)) {
                    continue;
                }

                foreach ($documentEmbeddings as $documentEmbedding) {
                    if (!is_array($documentEmbedding)) {
                        continue;
                    }

                    $distance = $this->cosineSimilarity($queryEmbedding, $documentEmbedding);

                    if ($distance < $closestDistance) {
                        $closestDistance = $distance;
                        $closestDocument = $document;
                    }
                }
            }

            if ($closestDocument) {
                return [
                    'success' => true,
                    'document' => $closestDocument->content
                ];
            }
        
            return [
                'success' => false,
                'document' => null,
            ];
        } catch (\Exception $e) {
            // Handle errors and exceptions
            Log::warning('intellireply.retrieval.failed', [
                'organization_id' => $organizationId,
                'error_code' => $this->isEmbeddingModelAccessError($e)
                    ? 'EMBEDDING_MODEL_UNAVAILABLE'
                    : 'RETRIEVAL_FAILED',
                'key_source' => $apiKeyBundle['source'] ?? null,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function cosineSimilarity($vecA, $vecB)
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $length = min(count($vecA), count($vecB));

        for ($i = 0; $i < $length; $i++) {
            $a = (float) $vecA[$i];
            $b = (float) $vecB[$i];
            $dotProduct += $a * $b;
            $normA += $a ** 2;
            $normB += $b ** 2;
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 1.0;
        }

        return 1 - ($dotProduct / (sqrt($normA) * sqrt($normB)));
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

    private function resolveEmbeddingModel(array $organizationConfig): string
    {
        $model = data_get($organizationConfig, 'ai.embedding_model');
        if (is_string($model) && trim($model) !== '') {
            return $model;
        }

        $default = config('intellireply.default_embedding_model', 'text-embedding-3-small');
        return is_string($default) && trim($default) !== ''
            ? $default
            : 'text-embedding-3-small';
    }

    private function embeddingModelCandidates(array $organizationConfig): array
    {
        $preferred = $this->resolveEmbeddingModel($organizationConfig);
        $allowlist = config('intellireply.embedding_models', ['text-embedding-3-small']);
        if (!is_array($allowlist)) {
            $allowlist = ['text-embedding-3-small'];
        }

        $fallbackMap = config('intellireply.embedding_model_fallbacks', []);
        if (!is_array($fallbackMap)) {
            $fallbackMap = [];
        }

        $candidates = [$preferred];
        $preferredFallbacks = $fallbackMap[$preferred] ?? [];
        if (is_array($preferredFallbacks)) {
            foreach ($preferredFallbacks as $fallbackModel) {
                if (is_string($fallbackModel) && trim($fallbackModel) !== '') {
                    $candidates[] = trim($fallbackModel);
                }
            }
        }

        foreach ($allowlist as $allowedModel) {
            if (is_string($allowedModel) && trim($allowedModel) !== '') {
                $candidates[] = trim($allowedModel);
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @return array{0:array,1:string}
     */
    private function createEmbeddingWithFallback($client, string $input, array $organizationConfig): array
    {
        $lastError = null;

        foreach ($this->embeddingModelCandidates($organizationConfig) as $model) {
            try {
                $response = $client->embeddings()->create([
                    'input' => $input,
                    'model' => $model,
                ]);

                return [$response->embeddings[0]->embedding, $model];
            } catch (\Throwable $e) {
                $lastError = $e;
                continue;
            }
        }

        throw $lastError ?? new \RuntimeException('Unable to generate embeddings');
    }

    private function isEmbeddingModelAccessError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'does not have access to model')
            || str_contains($message, 'model_not_found')
            || str_contains($message, 'invalid model');
    }
}
