<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreContact;
use App\Http\Resources\AutoReplyResource;
use App\Http\Resources\ContactResource;
use App\Http\Resources\ContactGroupResource;
use App\Http\Resources\TemplateResource;
use App\Jobs\SendCampaignMessageJob;
use App\Models\AutoReply;
use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\Template;
use App\Rules\CannedReplyLimit;
use App\Rules\ContactLimit;
use App\Rules\UniquePhone;
use App\Services\ChatService;
use App\Services\ContactService;
use App\Services\MediaService;
use App\Services\OrganizationApiService;
use App\Services\PhoneService;
use App\Services\SubscriptionService;
use App\Services\Whatsapp\WhatsappAccessTokenRefreshService;
use App\Services\WhatsappService;
use App\Support\DeveloperApiResponse;
use App\Traits\TemplateTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\PhoneNumber;
use Symfony\Component\HttpKernel\Exception\HttpException;
use DB;

class ApiController extends Controller
{
    use TemplateTrait;

    private $whatsappService;

    /**
     * List all contacts.
     *
     * @return \Illuminate\Http\Response
     */
    public function listContacts(Request $request){
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100', // Adjust max per_page limit as needed
        ]);

        if ($validator->fails()) {
            return DeveloperApiResponse::validation($validator->errors());
        }

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);

        $contacts = Contact::where('organization_id', $request->organization)
            ->where('deleted_at', NULL)
            ->paginate($perPage, ['*'], 'page', $page);

        return ContactResource::collection($contacts);
    }

    /**
     * Create a new contact.
     *
     * @param  \App\Http\Requests\CreateContactRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function storeContact(Request $request, $uuid = NULL){
        $validator = Validator::make($request->all(), [
            'first_name' => $request->isMethod('post') ? 'required' : 'required|sometimes',
            //'last_name' => 'required',
            'phone' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (!PhoneService::isValid($value)) {
                        $fail(__('The phone number is not valid.'));
                    }
                },
                new UniquePhone($request->organization, $uuid),
            ],
            //'email' => 'required|string|email|max:255',
        ]);

        if ($validator->fails()) {
            return DeveloperApiResponse::validation($validator->errors());
        }

        if(!SubscriptionService::isSubscriptionActive($request->organization)){
            return DeveloperApiResponse::forbidden(
                __('Please renew or subscribe to a plan to continue!'),
                'subscription_inactive'
            );
        }

        if ($request->isMethod('post')) {
            if (SubscriptionService::isSubscriptionFeatureLimitReached($request->organization, 'contacts_limit')) {
                return DeveloperApiResponse::forbidden(
                    __('You have reached your limit of contacts. Please upgrade your account to add more!'),
                    'contacts_limit_reached'
                );
            }
        }

        try {
            $contactService = new ContactService($request->organization);
            $contact = $contactService->store($request, $uuid);

            return response()->json([
                'statusCode' => 200,
                'id' => $contact->uuid,
                'message' => __('Request processed successfully')
            ], 200);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse(__('Contact not found.'));
        } catch (\Exception $e) {
            return DeveloperApiResponse::serverError();
        }
    }

    /**
     * Delete a contact.
     *
     * @param  \App\Models\Contact  $contact
     * @return \Illuminate\Http\Response
     */
    public function destroyContact(Request $request, $uuid){
        $contactExists = Contact::where('organization_id', $request->organization)
            ->where('uuid', $uuid)
            ->whereNull('deleted_at')
            ->exists();

        if (!$contactExists) {
            return $this->notFoundResponse(__('Contact not found.'));
        }

        try {
            $contactService = new ContactService($request->organization);
            $contactService->delete([$uuid]);

            return response()->json([
                'statusCode' => 200,
                'id' => $uuid,
                'message' => __('Request processed successfully')
            ], 200);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse(__('Contact not found.'));
        } catch (\Exception $e) {
            return DeveloperApiResponse::serverError();
        }
    }

    /**
     * List all contact groups.
     *
     * @return \Illuminate\Http\Response
     */
    public function listContactGroups(Request $request){
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100', // Adjust max per_page limit as needed
        ]);

        if ($validator->fails()) {
            return DeveloperApiResponse::validation($validator->errors());
        }

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);

        $contactGroups = ContactGroup::where('organization_id', $request->organization)
            ->where('deleted_at', NULL)
            ->paginate($perPage, ['*'], 'page', $page);

        return ContactGroupResource::collection($contactGroups);
    }

    /**
     * Create a new contact group.
     *
     * @param  \App\Http\Requests\CreateContactGroupRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function storeContactGroup(Request $request, $uuid = NULL){
        $organizationId = $request->organization;

        if ($request->isMethod('post')) {
            $rules = [
                'name' => [
                    'required',
                    Rule::unique('contact_groups', 'name')->where(function ($query) use ($organizationId) {
                        return $query->where('organization_id', $organizationId)
                            ->where('deleted_at', null);
                    }),
                ],
            ];
        } else {
            $rules = [
                'name' => [ 
                    'required',
                    Rule::unique('contact_groups', 'name')->where(function ($query) use ($organizationId, $uuid) {
                        return $query->where('organization_id', $organizationId)
                            ->where('deleted_at', null)
                            ->whereNotIn('uuid', [$uuid]);
                    }),
                ],
            ];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return DeveloperApiResponse::validation($validator->errors());
        }

        if(!SubscriptionService::isSubscriptionActive($request->organization)){
            return DeveloperApiResponse::forbidden(
                __('Please renew or subscribe to a plan to continue!'),
                'subscription_inactive'
            );
        }

        try {
            $contactGroup = $request->isMethod('post')
                ? new ContactGroup()
                : ContactGroup::where('organization_id', $request->organization)
                    ->where('uuid', $uuid)
                    ->whereNull('deleted_at')
                    ->firstOrFail();

            if ($request->isMethod('post')) {
                $contactGroup->organization_id = $request->organization;
                $contactGroup->created_by = 0;
            }

            $contactGroup->name = $request->name;
            $contactGroup->save();

            return response()->json([
                'statusCode' => 200,
                'id' => $contactGroup->uuid,
                'message' => __('Request processed successfully')
            ], 200);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse(__('Contact group not found.'));
        } catch (\Exception $e) {
            return DeveloperApiResponse::serverError();
        }
    }

    /**
     * Delete a contact group.
     *
     * @param  \App\Models\ContactGroup  $contactGroup
     * @return \Illuminate\Http\Response
     */
    public function destroyContactGroup(Request $request, $uuid){
        try {
            $contactGroup = ContactGroup::where('organization_id', $request->organization)
                ->where('uuid', $uuid)
                ->whereNull('deleted_at')
                ->firstOrFail();
            $contactGroup->deleted_at = date('Y-m-d H:i:s');
            $contactGroup->save();

            // Remove pivot associations for many-to-many relation.
            DB::table('contact_contact_group')
                ->where('contact_group_id', $contactGroup->id)
                ->delete();

            // Keep previous column cleanup for backward compatibility.
            Contact::where('contact_group_id', $contactGroup->id)->update([
                'contact_group_id' => null
            ]);

            return response()->json([
                'statusCode' => 200,
                'id' => $uuid,
                'message' => __('Request processed successfully')
            ], 200);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse(__('Contact group not found.'));
        } catch (\Exception $e) {
            return DeveloperApiResponse::serverError();
        }
    }

    public function listCannedReplies(Request $request){
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100', // Adjust max per_page limit as needed
        ]);

        if ($validator->fails()) {
            return DeveloperApiResponse::validation($validator->errors());
        }

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);

        $rows = AutoReply::where('organization_id', $request->organization)
            ->where('deleted_at', NULL)
            ->paginate($perPage, ['*'], 'page', $page);

        return AutoReplyResource::collection($rows);
    }

    /**
     * Create a new canned reply.
     *
     * @param  \App\Http\Requests\CreateCannedReplyRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function storeCannedReply(Request $request, $uuid = NULL){
        $rules = [
            'name' => 'required',
            'trigger' => 'required',
            'match_criteria' => 'required|in:exact match,contains',
            'response_type' => 'required|in:text,image,audio',
            'response' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return DeveloperApiResponse::validation($validator->errors());
        }

        if(!SubscriptionService::isSubscriptionActive($request->organization)){
            return DeveloperApiResponse::forbidden(
                __('Please renew or subscribe to a plan to continue!'),
                'subscription_inactive'
            );
        }

        if ($request->isMethod('post')) {
            if (SubscriptionService::isSubscriptionFeatureLimitReached($request->organization, 'canned_replies_limit')) {
                return DeveloperApiResponse::forbidden(
                    __('You\'ve reached your limit. Upgrade your account'),
                    'canned_replies_limit_reached'
                );
            }
        }

        try {
            $model = $uuid == null
                ? new AutoReply
                : AutoReply::where('organization_id', $request->organization)
                    ->where('uuid', $uuid)
                    ->whereNull('deleted_at')
                    ->firstOrFail();
            $model['name'] = $request->name;
            $model['trigger'] = $request->trigger;
            $model['match_criteria'] = $request->match_criteria;

            $metadata['type'] = $request->response_type;
            if($request->response_type === 'image' || $request->response_type === 'audio'){
                if($request->hasFile('response')){
                    $uploadedMedia = MediaService::upload($request->file('response'));

                    $metadata['data']['file']['name'] = $uploadedMedia['name'];
                    $metadata['data']['file']['location'] = $uploadedMedia['path'];
                } else {
                    $media = json_decode($model->metadata)->data;
                    $metadata['data']['file']['name'] = $media->file->name;
                    $metadata['data']['file']['location'] = $media->file->location;
                }
            } else if($request->response_type === 'text') {
                $metadata['data']['text'] = $request->response;
            } else {
                $metadata['data']['template'] = $request->response;
            }

            $model['metadata'] = json_encode($metadata);
            $model['updated_at'] = now();

            if($uuid === null){
                $model['organization_id'] = $request->organization;
                $model['created_by'] = 0;
                $model['created_at'] = now();
            }

            $model->save();

            return response()->json([
                'statusCode' => 200,
                'id' => $model->uuid,
                'message' => __('Request processed successfully')
            ], 200);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse(__('Canned reply not found.'));
        } catch (\Exception $e) {
            return DeveloperApiResponse::serverError();
        }
    }

    /**
     * Delete a canned reply.
     *
     * @param  \App\Models\CannedReply  $cannedReply
     * @return \Illuminate\Http\Response
     */
    public function destroyCannedReply(Request $request, $uuid){
        try {
            $autoreply = AutoReply::where('organization_id', $request->organization)
                ->where('uuid', $uuid)
                ->whereNull('deleted_at')
                ->firstOrFail();
            $autoreply->deleted_at = now();
            $autoreply->deleted_by = 0;
            $autoreply->save();

            return response()->json([
                'statusCode' => 200,
                'id' => $uuid,
                'message' => __('Request processed successfully')
            ], 200);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse(__('Canned reply not found.'));
        } catch (\Exception $e) {
            return DeveloperApiResponse::serverError();
        }
    }

    /**
     * Send a chat message.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function sendMessage(Request $request){
        $rules = [
            'phone' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (!PhoneService::isValid($value)) {
                    $fail(__('The phone number is not valid.'));
                }
            }],
            'message' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return DeveloperApiResponse::validation($validator->errors(), __('The provided data is invalid.'));
        }

        if(!SubscriptionService::isSubscriptionActive($request->organization)){
            return DeveloperApiResponse::forbidden(
                __('Please renew or subscribe to a plan to continue!'),
                'subscription_inactive'
            );
        }

        //Check if the whatsapp connection exists
        if(!$this->isWhatsAppConnected($request->organization)){
            return DeveloperApiResponse::forbidden(
                __('Please setup your whatsapp account!'),
                'whatsapp_not_connected'
            );
        }

        if ($blockedResponse = $this->outboundMessageLimitResponse((int) $request->organization)) {
            return $blockedResponse;
        }

        $contact = $this->resolveSendContact($request);
        if ($contact instanceof \Illuminate\Http\JsonResponse) {
            return $contact;
        }

        // Extract the UUID of the contact
        $this->initializeWhatsappService($request->organization);
        $type = !isset($request->buttons) ? 'text' : 'interactive buttons';

        $header = [];
        if($request->header){
            $header['type'] = 'text';
            $header['text'] = clean($request->header);
        }

        $message = $this->whatsappService->sendMessage($contact->uuid, $request->message, 0, $type, $request->buttons, $header, $request->footer);
        
        return response()->json([
            'statusCode' => 200,
            'data' => $message
        ], 200);
    }

    public function sendTemplateMessage(Request $request){
        $rules = [
            'phone' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (!PhoneService::isValid($value)) {
                    $fail(__('The phone number is not valid.'));
                }
            }],
            'template.name' => 'required',
            'template.language' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return DeveloperApiResponse::validation($validator->errors(), __('The provided data is invalid.'));
        }

        if(!SubscriptionService::isSubscriptionActive($request->organization)){
            return DeveloperApiResponse::forbidden(
                __('Please renew or subscribe to a plan to continue!'),
                'subscription_inactive'
            );
        }

        //Check if the whatsapp connection exists
        if(!$this->isWhatsAppConnected($request->organization)){
            return DeveloperApiResponse::forbidden(
                __('Please setup your whatsapp account!'),
                'whatsapp_not_connected'
            );
        }

        if ($blockedResponse = $this->outboundMessageLimitResponse((int) $request->organization)) {
            return $blockedResponse;
        }

        $contact = $this->resolveSendContact($request);
        if ($contact instanceof \Illuminate\Http\JsonResponse) {
            return $contact;
        }

        // Extract the UUID of the contact
        $this->initializeWhatsappService($request->organization);
        $responseObject = $this->whatsappService->sendTemplateMessage($contact->uuid, $request->template, 0);

        return response()->json([
            'statusCode' => 200,
            'data' => $responseObject
        ], 200);
    }

    public function sendMediaMessage(Request $request){
        $rules = [
            'phone' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (!PhoneService::isValid($value)) {
                    $fail(__('The phone number is not valid.'));
                }
            }],
            'media_type' => 'required',
            'media_url' => 'required',
            'caption' => 'required',
            'file_name' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return DeveloperApiResponse::validation($validator->errors(), __('The provided data is invalid.'));
        }

        if(!SubscriptionService::isSubscriptionActive($request->organization)){
            return DeveloperApiResponse::forbidden(
                __('Please renew or subscribe to a plan to continue!'),
                'subscription_inactive'
            );
        }

        //Check if the whatsapp connection exists
        if(!$this->isWhatsAppConnected($request->organization)){
            return DeveloperApiResponse::forbidden(
                __('Please setup your whatsapp account!'),
                'whatsapp_not_connected'
            );
        }

        if ($blockedResponse = $this->outboundMessageLimitResponse((int) $request->organization)) {
            return $blockedResponse;
        }

        $contact = $this->resolveSendContact($request);
        if ($contact instanceof \Illuminate\Http\JsonResponse) {
            return $contact;
        }

        // Extract the UUID of the contact
        $this->initializeWhatsappService($request->organization);
        $type = !isset($request->buttons) ? 'text' : 'interactive';

        $message = $this->whatsappService->sendMedia($contact->uuid, $request->media_type, $request->file_name, $request->media_url, $request->media_url, 'amazon');
        
        return response()->json([
            'statusCode' => 200,
            'data' => $message
        ], 200);
    }

    /**
     * Store a campaign.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function storeCampaign(Request $request){
        // Backward-compatible aliases for external API payloads.
        if (!$request->has('template') && $request->has('template_uuid')) {
            $request->merge(['template' => $request->input('template_uuid')]);
        }
        if (!$request->has('contacts') && $request->has('contact_group')) {
            $request->merge(['contacts' => $request->input('contact_group')]);
        }
        if (!$request->has('name') || empty($request->input('name'))) {
            $request->merge(['name' => 'API Campaign ' . now()->format('YmdHis')]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:128',
            'template' => 'required|string',
            'contacts' => 'required|string',
            'skip_schedule' => 'nullable|boolean',
            'time' => 'required_unless:skip_schedule,1,true|date',
            'header' => 'nullable|array',
            'body' => 'nullable|array',
            'footer' => 'nullable|array',
            'buttons' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return DeveloperApiResponse::validation($validator->errors());
        }

        if (!SubscriptionService::isSubscriptionActive($request->organization)) {
            return DeveloperApiResponse::forbidden(
                __('Please renew or subscribe to a plan to continue!'),
                'subscription_inactive'
            );
        }

        if (SubscriptionService::isSubscriptionFeatureLimitReached($request->organization, 'campaign_limit')) {
            return DeveloperApiResponse::forbidden(
                __('You have reached your limit of campaigns. Please upgrade your account to add more!'),
                'campaign_limit_reached'
            );
        }

        if (!$this->isWhatsAppConnected($request->organization)) {
            return DeveloperApiResponse::forbidden(
                __('Please setup your whatsapp account!'),
                'whatsapp_not_connected'
            );
        }

        $template = Template::where('organization_id', $request->organization)
            ->where('uuid', $request->template)
            ->whereNull('deleted_at')
            ->first();

        if (!$template) {
            return $this->notFoundResponse(__('Template not found.'), 'template_not_found');
        }

        $contactGroupId = 0;
        $contactQuery = Contact::query()
            ->where('organization_id', $request->organization)
            ->whereNull('deleted_at');

        if ($request->contacts !== 'all') {
            $contactGroup = ContactGroup::where('organization_id', $request->organization)
                ->where('uuid', $request->contacts)
                ->whereNull('deleted_at')
                ->first();

            if (!$contactGroup) {
                return $this->notFoundResponse(__('Contact group not found.'), 'contact_group_not_found');
            }

            $contactGroupId = $contactGroup->id;
            $contactQuery->whereHas('contactGroups', function ($q) use ($contactGroupId) {
                $q->where('contact_groups.id', $contactGroupId);
            });
        }

        $contactsCount = (clone $contactQuery)->count();
        if ($contactsCount === 0) {
            return DeveloperApiResponse::unprocessable(
                __('No contacts found for this campaign.'),
                'campaign_audience_empty'
            );
        }

        $organization = Organization::where('id', $request->organization)->first();
        $defaultTimezone = Setting::where('key', 'timezone')->value('value') ?? 'UTC';
        $organizationMetadata = $organization && $organization->metadata
            ? json_decode($organization->metadata, true)
            : [];
        $timezone = $organizationMetadata['timezone'] ?? $defaultTimezone;

        $skipSchedule = filter_var($request->input('skip_schedule', true), FILTER_VALIDATE_BOOLEAN);
        $scheduledAtUtc = $skipSchedule
            ? Carbon::now('UTC')
            : Carbon::parse($request->input('time'), $timezone)->setTimezone('UTC');
        $campaignStatus = $skipSchedule || $scheduledAtUtc->lte(now('UTC')) ? 'ongoing' : 'scheduled';

        $metadata = [
            'header' => $request->input('header', ['format' => null, 'text' => null, 'parameters' => []]),
            'body' => $request->input('body', ['text' => null, 'parameters' => []]),
            'footer' => $request->input('footer', ['text' => null]),
            'buttons' => $request->input('buttons', []),
            'media' => null,
        ];

        try {
            $result = DB::transaction(function () use (
                $request,
                $template,
                $contactGroupId,
                $campaignStatus,
                $scheduledAtUtc,
                $metadata,
                $contactQuery
            ) {
                $campaign = Campaign::create([
                    'organization_id' => $request->organization,
                    'name' => $request->name,
                    'template_id' => $template->id,
                    'contact_group_id' => $contactGroupId,
                    'metadata' => json_encode($metadata),
                    'status' => $campaignStatus,
                    'scheduled_at' => $scheduledAtUtc->toDateTimeString(),
                    'created_by' => 0,
                    'created_at' => now(),
                ]);

                $now = now();
                $logsInserted = 0;

                (clone $contactQuery)
                    ->select('contacts.id')
                    ->orderBy('contacts.id')
                    ->chunkById(1000, function ($contacts) use ($campaign, $scheduledAtUtc, $now, &$logsInserted) {
                        $rows = [];
                        foreach ($contacts as $contact) {
                            $rows[] = [
                                'campaign_id' => $campaign->id,
                                'contact_id' => $contact->id,
                                'chat_id' => null,
                                'metadata' => null,
                                'status' => 'pending',
                                'retry_count' => 0,
                                'scheduled_at' => $scheduledAtUtc->toDateTimeString(),
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }

                        if (!empty($rows)) {
                            DB::table('campaign_logs')->insert($rows);
                            $logsInserted += count($rows);
                        }
                    }, 'contacts.id');

                return [
                    'campaign' => $campaign,
                    'logsInserted' => $logsInserted,
                ];
            });

            // For non-sync queues, dispatch jobs immediately with schedule delay.
            if (config('queue.default') !== 'sync') {
                $logIds = CampaignLog::where('campaign_id', $result['campaign']->id)->pluck('id');
                foreach ($logIds as $logId) {
                    SendCampaignMessageJob::dispatch((int) $logId)
                        ->onQueue('campaign-messages')
                        ->delay($scheduledAtUtc);
                }
            }

            return response()->json([
                'statusCode' => 200,
                'id' => $result['campaign']->uuid,
                'message' => __('Campaign created successfully!'),
                'data' => [
                    'campaign_status' => $result['campaign']->status,
                    'scheduled_at_utc' => $scheduledAtUtc->toDateTimeString(),
                    'contacts_count' => $result['logsInserted'],
                    'queue_driver' => config('queue.default'),
                ],
            ], 200);
        } catch (\Throwable $e) {
            return DeveloperApiResponse::serverError();
        }
    }

    private function isWhatsAppConnected($organizationId){
        $settings = Organization::where('id', $organizationId)->first();
        if (!$settings) {
            return false;
        }

        $metadata = $settings->metadata ? json_decode($settings->metadata, true) : [];

        return isset($metadata['whatsapp']);
    }

    private function notFoundResponse(string $message, string $code = 'not_found')
    {
        return DeveloperApiResponse::notFound($message, $code);
    }

    private function outboundMessageLimitResponse(int $organizationId)
    {
        if (!SubscriptionService::isSubscriptionFeatureLimitReached($organizationId, 'message_limit')) {
            return null;
        }

        return DeveloperApiResponse::forbidden(
            __('You have reached your message limit for the current subscription cycle. Please upgrade your plan to continue sending messages.'),
            'message_limit_reached'
        );
    }

    private function resolveSendContact(Request $request)
    {
        $phone = $this->normalizePhoneNumber((string) $request->phone);
        $contact = Contact::where('organization_id', $request->organization)
            ->where('phone', $phone)
            ->whereNull('deleted_at')
            ->first();

        if ($contact) {
            return $contact;
        }

        if (SubscriptionService::isSubscriptionFeatureLimitReached($request->organization, 'contacts_limit')) {
            return DeveloperApiResponse::forbidden(
                __('You have reached your limit of contacts. Please upgrade your account to add more!'),
                'contacts_limit_reached'
            );
        }

        $contact = new Contact();
        $contact->organization_id = $request->organization;
        $contact->first_name = $request->first_name;
        $contact->last_name = $request->last_name;
        $contact->email = $request->email;
        $contact->phone = $phone;
        $contact->created_by = 0;
        $contact->save();

        return $contact;
    }

    private function normalizePhoneNumber(string $phone): string
    {
        if (substr($phone, 0, 1) !== '+') {
            $phone = '+' . $phone;
        }

        return (new PhoneNumber($phone))->formatE164();
    }

    private function initializeWhatsappService($organizationId)
    {
        $organization = Organization::where('id', $organizationId)->first();
        if (!$organization) {
            throw new HttpException(404, __('Organization not found.'));
        }

        $config = $organization->metadata;
        $config = $config ? json_decode($config, true) : [];
        $accessToken = app(WhatsappAccessTokenRefreshService::class)
            ->resolveTokenForOrganization((int) $organizationId, true);
        $apiVersion = config('graph.api_version');
        $appId = $config['whatsapp']['app_id'] ?? null;
        $phoneNumberId = $config['whatsapp']['phone_number_id'] ?? null;
        $wabaId = $config['whatsapp']['waba_id'] ?? null;

        $this->whatsappService = new WhatsappService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $organizationId);
    }

    /**
     * List all templates.
     *
     * @return \Illuminate\Http\Response
     */
    public function listTemplates(Request $request){
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100', // Adjust max per_page limit as needed
        ]);

        if ($validator->fails()) {
            return DeveloperApiResponse::validation($validator->errors());
        }

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);

        $templates = Template::where('organization_id', $request->organization)
            ->where('deleted_at', NULL)
            ->paginate($perPage, ['uuid', 'name', 'metadata', 'updated_at'], 'page', $page);

        return TemplateResource::collection($templates);
    }

    /**
     * Verify if the API key is active.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function verifyApiKey(Request $request)
    {
        $bearerToken = $request->bearerToken();

        if (!$bearerToken) {
            return DeveloperApiResponse::unauthorized(
                __('No API key provided. Please include it in the Authorization header as a Bearer token.'),
                'bearer_token_missing'
            );
        }

        try {
            $token = app(OrganizationApiService::class)->findActiveTokenRecord($bearerToken);

            if (!$token) {
                return DeveloperApiResponse::unauthorized(__('Invalid API key.'), 'bearer_token_invalid');
            }

            $organizationId = $token->organization_id;

            if (!SubscriptionService::isSubscriptionActive($organizationId)) {
                return DeveloperApiResponse::forbidden(
                    __('API key is inactive. Please renew or subscribe to a plan to continue!'),
                    'subscription_inactive'
                );
            }

            return response()->json([
                'statusCode' => 200,
                'message' => __('API key is valid and active')
            ], 200);
        } catch (\Exception $e) {
            return DeveloperApiResponse::serverError();
        }
    }
}
