<?php

namespace App\Services;

use App\Http\Resources\AutoReplyResource;
use App\Models\AutoReply;
use App\Models\Chat;
use App\Models\Contact;
use App\Models\Organization;
use App\Services\MediaService;
use App\Services\AutomationFlows\AutomationFlowAccessService;
use App\Services\AutomationFlows\AutomationFlowRuntimeService;
use App\Services\Whatsapp\WhatsappAccessTokenRefreshService;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use DB;
use Validator;

class AutoReplyService
{
    private function currentOrganizationId(): int
    {
        return (int) session()->get('current_organization');
    }

    private function scopedAutoReply(string $uuid): AutoReply
    {
        return AutoReply::where('organization_id', $this->currentOrganizationId())
            ->where('uuid', $uuid)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    public function getRows(object $request)
    {
        $organizationId = session()->get('current_organization');
        $model = new AutoReply;
        $searchTerm = $request->query('search');

        return AutoReplyResource::collection($model->listAll($organizationId, $searchTerm));
    }

    public function store(object $request, $uuid = null)
    {
        $model = $uuid == null ? new AutoReply : $this->scopedAutoReply($uuid);
        $model['name'] = $request->name;
        $model['trigger'] = $request->trigger;
        $model['match_criteria'] = $request->match_criteria;

        $metadata['type'] = $request->response_type;
        if($request->response_type === 'image' || $request->response_type === 'audio'){
            if($request->hasFile('response')){
                $storage = app(SettingValueService::class)->getString('storage_system', 'local');
                $fileName = $request->file('response')->getClientOriginalName();
                $fileContent = $request->file('response');

                if($storage === 'local'){
                    $file = Storage::disk('local')->put('public', $fileContent);
                    $mediaFilePath = $file;
                    $mediaUrl = rtrim(config('app.url'), '/') . '/media/' . ltrim($mediaFilePath, '/');
                } else if($storage === 'aws') {
                    $filePath = 'uploads/media/received'  . session()->get('current_organization') . '/' . $fileName;
                    $file = Storage::disk('s3')->put($filePath, $fileContent, 'public');
                    $mediaFilePath = Storage::disk('s3')->url($filePath);
                    $mediaUrl = $mediaFilePath;
                }

                $uploadedMedia = MediaService::upload($request->file('response'));

                $metadata['data']['file']['name'] = $fileName;
                $metadata['data']['file']['location'] = $mediaFilePath;
                $metadata['data']['file']['url'] = $mediaUrl;
            } else {
                $media = json_decode($model->metadata)->data;
                $metadata['data']['file']['name'] = $media->file->name;
                $metadata['data']['file']['location'] = $media->file->location;
                $metadata['data']['file']['url'] = $media->file->url;
            }
        } else if($request->response_type === 'text') {
            $metadata['data']['text'] = $request->response;
        } else {
            $metadata['data']['template'] = $request->response;
        }

        $model['metadata'] = json_encode($metadata);
        $model['updated_at'] = now();

        if($uuid === null){
            $model['organization_id'] = session()->get('current_organization');
            $model['created_by'] = auth()->user()->id;
            $model['created_at'] = now();
        }

        $model->save();

    }

    public function destroy($uuid)
    {
        $autoReply = $this->scopedAutoReply($uuid);
        $autoReply->update([
            'deleted_by' => auth()->user()->id,
            'deleted_at' => now()
        ]);

    }

    public function checkAutoReply(Chat $chat, $isNewContact)
    {
        $organizationId = $chat->organization_id;

        $this->replySequence($organizationId, $chat, $isNewContact);
    }

    private function replySequence($organizationId, $chat, $isNewContact)
    {
        $organizationConfig = Organization::where('id', $organizationId)->first();
        $metadataArray = $organizationConfig->metadata ? json_decode($organizationConfig->metadata, true) : [];
        $addonState = app(AddonStateService::class);
        $isAiAssistantEnabled = $addonState->isModuleEnabledForOrganization('AI Assistant', (int) $organizationId);
        $isAutomationFlowEnabled = app(AutomationFlowAccessService::class)->availableForOrganization((int) $organizationId);
        $response_sequence = app(AutomationResponseSequenceService::class)->sanitize(
            (array) ($metadataArray['automation']['response_sequence'] ?? []),
            (int) $organizationId
        );

        // Define mapping of sequence items to functions
        $sequenceFunctions = [
            'Automation Flows' => function() use ($chat, $isAutomationFlowEnabled) {
                return $this->handleAutomationFlows($chat, $isAutomationFlowEnabled);
            },
            'Basic Replies' => function() use ($chat) {
                return $this->handleBasicReplies($chat);
            },
            'AI Reply Assistant' => function() use ($chat, $organizationId, $isAiAssistantEnabled) {
                if (!$isAiAssistantEnabled) {
                    return false;
                }

                return $this->handleAIReplyAssistant($chat, $organizationId);
            },
        ];

        // Initialize a variable to hold the response (or handle chaining, etc.)
        $response = null;

        // Iterate through the sequence, applying each function in order
        foreach ($response_sequence as $sequenceItem) {
            if (isset($sequenceFunctions[$sequenceItem])) {
                $response = $sequenceFunctions[$sequenceItem]();

                if ($response) {
                    // If a response is found, exit the loop
                    break;
                }
            }
        }

        return $response;
    }

    private function handleBasicReplies($chat)
    {
        $organizationId = $chat->organization_id;
        $receivedMessage = " " . $this->extractInboundMessageText($chat);

        //Check basic reply flow
        $autoReplies = AutoReply::where('organization_id', $organizationId)
            ->where('deleted_at', null)
            ->get();

        foreach ($autoReplies as $autoReply) {
            $triggerValues = $this->getTriggerValues($autoReply->trigger);

            foreach ($triggerValues as $trigger) {
                if ($this->checkMatch($receivedMessage, $trigger, $autoReply->match_criteria)) {
                    $this->sendReply($chat, $autoReply);
                    return true;
                }
            }
        }

        return false; // No reply was sent
    }

    private function handleAutomationFlows($chat, bool $isAutomationFlowEnabled): bool
    {
        if (!$isAutomationFlowEnabled) {
            return false;
        }

        try {
            return app(AutomationFlowRuntimeService::class)->handleInbound($chat);
        } catch (\Throwable $exception) {
            report($exception);
            return false;
        }
    }

    private function handleAIReplyAssistant($chat, $organizationId)
    {
        $receivedMessage = " " . $this->extractInboundMessageText($chat);

        if (class_exists(\Modules\IntelliReply\Services\AIResponseService::class)) {
            $query = new \Modules\IntelliReply\Services\AIResponseService();
            if ($query->handleAIResponse($chat, $receivedMessage)) {
                return true;
            }
        }

        return false; // No reply was sent
    }

    private function extractInboundMessageText(Chat $chat): string
    {
        return $this->extractInboundMessageTextFromMetadata($chat->metadata);
    }

    private function extractInboundMessageTextFromMetadata(array|string|null $metadata): string
    {
        $payload = is_array($metadata)
            ? $metadata
            : (is_string($metadata) ? json_decode($metadata, true) : null);

        if (!is_array($payload)) {
            return '';
        }

        $type = (string) ($payload['type'] ?? '');
        if ($type === 'text') {
            return trim((string) data_get($payload, 'text.body', ''));
        }

        if ($type === 'button') {
            return trim((string) data_get($payload, 'button.payload', ''));
        }

        if ($type === 'interactive') {
            $interactiveType = (string) data_get($payload, 'interactive.type', '');
            if ($interactiveType === 'button_reply') {
                return trim((string) data_get($payload, 'interactive.button_reply.title', ''));
            }

            if ($interactiveType === 'list_reply') {
                return trim((string) data_get($payload, 'interactive.list_reply.title', ''));
            }
        }

        return '';
    }

    private function getTriggerValues($trigger)
    {
        return is_string($trigger) && strpos($trigger, ',') !== false
            ? explode(',', $trigger)
            : (array) $trigger;
    }

    private function checkMatch($receivedMessage, $trigger, $criteria)
    {
        $normalizedTrigger = trim($trigger);

        if ($criteria === 'exact match') {
            // Check if the text contains Arabic characters
            $hasArabic = preg_match('/[\p{Arabic}]/u', $receivedMessage . $normalizedTrigger);
            
            if ($hasArabic) {
                // For Arabic text, use exact case-sensitive matching
                return $receivedMessage === " " . $normalizedTrigger;
            } else {
                // For English text, use case-insensitive matching
                return strtolower($receivedMessage) === " " . strtolower($normalizedTrigger);
            }
        } else if ($criteria === 'contains') {
            $triggerWords = explode(' ', $normalizedTrigger);
            
            // Check if the text contains Arabic characters
            $hasArabic = preg_match('/[\p{Arabic}]/u', $receivedMessage . $normalizedTrigger);
            
            if ($hasArabic) {
                // For Arabic text, use simple string matching without word boundaries
                foreach ($triggerWords as $word) {
                    if (strpos($receivedMessage, $word) !== false) {
                        return true;
                    }
                }
                return false;
            } else {
                // For non-Arabic text, use case-insensitive regex approach
                $pattern = '/\b(' . implode('|', array_map('preg_quote', $triggerWords)) . ')\b/i';
                return preg_match($pattern, strtolower($receivedMessage)) === 1;
            }
        }
    
        return false;
    }

    protected function sendReply(Chat $chat, AutoReply $autoreply)
    {
        $contact = Contact::where('id', $chat->contact_id)->first();
        $organization_id = $chat->organization_id;
        $metadata = json_decode($autoreply->metadata);
        $replyType = $metadata->type;

        if($replyType === 'text'){
            $message = $this->replacePlaceholders($organization_id, $contact->uuid, $metadata->data->text);
            $this->initializeWhatsappService($organization_id)->sendMessage($contact->uuid, $message);
        } else if($replyType === 'audio' || $replyType === 'image'){
            $location = strpos($metadata->data->file->location, 'public\\') === 0 ? 'local' : 'amazon';
            $this->initializeWhatsappService($organization_id)->sendMedia($contact->uuid, $replyType, $metadata->data->file->name, $metadata->data->file->location, $metadata->data->file->url, $location);
        }
    }

    private function initializeWhatsappService($organizationId)
    {
        $config = Organization::where('id', $organizationId)->first()->metadata;
        $config = $config ? json_decode($config, true) : [];
        $accessToken = app(WhatsappAccessTokenRefreshService::class)
            ->resolveTokenForOrganization((int) $organizationId, true);
        $apiVersion = config('graph.api_version');
        $appId = $config['whatsapp']['app_id'] ?? null;
        $phoneNumberId = $config['whatsapp']['phone_number_id'] ?? null;
        $wabaId = $config['whatsapp']['waba_id'] ?? null;

        return new WhatsappService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $organizationId);
    }

    private function replacePlaceholders($organizationId, $contactUuid, $message){
        $organization = Organization::where('id', $organizationId)->first();
        $contact = Contact::with('contactGroups')->where('uuid', $contactUuid)->first();
        $address = $contact->address ? json_decode($contact->address, true) : [];
        $metadata = $contact->metadata ? json_decode($contact->metadata, true) : [];
        $full_address = ($address['street'] ?? Null) . ', ' .
                        ($address['city'] ?? Null) . ', ' .
                        ($address['state'] ?? Null) . ', ' .
                        ($address['zip'] ?? Null) . ', ' .
                        ($address['country'] ?? Null);

        $data = [
            'first_name' => $contact->first_name ?? Null,
            'last_name' => $contact->last_name ?? Null,
            'full_name' => $contact->full_name ?? Null,
            'email' => $contact->email ?? Null,
            'phone' => $contact->phone ?? Null,
            'organization_name' => $organization->name,
            'full_address' => $full_address,
            'street' => $address['street'] ?? Null,
            'city' => $address['city'] ?? Null,
            'state' => $address['state'] ?? Null,
            'zip_code' => $address['zip'] ?? Null,
            'country' => $address['country'] ?? Null,
        ];

        $transformedMetadata = [];
        if($metadata){
            foreach ($metadata as $key => $value) {
                $transformedKey = strtolower(str_replace(' ', '_', $key));
                $transformedMetadata[$transformedKey] = $value;
            }
        }

        $mergedData = array_merge($data, $transformedMetadata);

        //Log::info($mergedData);

        // First handle URL-encoded placeholders with {url:placeholder} syntax
        $message = preg_replace_callback('/\{url:(\w+)\}/', function ($matches) use ($mergedData) {
            $key = $matches[1];
            if (isset($mergedData[$key])) {
                return rawurlencode($mergedData[$key]);
            }
            return $matches[0];
        }, $message);
        
        // Then handle regular placeholders
        return preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($mergedData) {
            $key = $matches[1];
            return isset($mergedData[$key]) ? $mergedData[$key] : $matches[0];
        }, $message);
    }
}
