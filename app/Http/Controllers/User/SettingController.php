<?php

namespace App\Http\Controllers\User;

use DB;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\ExchangeEmbeddedSignupCodeRequest;
use App\Http\Requests\StoreWhatsappSettings;
use App\Http\Requests\StoreWhatsappProfile;
use App\Models\Addon;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\Template;
use App\Services\ContactFieldService;
use App\Services\AutomationResponseSequenceService;
use App\Services\EmbeddedSignup\EmbeddedSignupAuditService;
use App\Services\EmbeddedSignup\EmbeddedSignupGate;
use App\Services\EmbeddedSignup\EmbeddedSignupService;
use App\Support\OrganizationSettingsViewData;
use App\Services\Whatsapp\WhatsappAccessTokenRefreshService;
use App\Services\Whatsapp\WhatsappTokenVault;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;

class SettingController extends BaseController
{
    private EmbeddedSignupGate $embeddedSignupGate;
    private EmbeddedSignupAuditService $embeddedSignupAuditService;
    private WhatsappTokenVault $tokenVault;

    public function __construct(ContactFieldService $contactFieldService)
    {
        $this->contactFieldService = $contactFieldService;
        $this->embeddedSignupGate = new EmbeddedSignupGate();
        $this->embeddedSignupAuditService = new EmbeddedSignupAuditService();
        $this->tokenVault = new WhatsappTokenVault();
    }

    public function index(Request $request, $display = null){
        if ($request->isMethod('get')) {
            $organizationId = session()->get('current_organization');
            $this->checkPermission('settings.manage', $organizationId);

            return Inertia::render('User/Settings/General', app(OrganizationSettingsViewData::class)->general((int) $organizationId));
        }
    }

    public function viewGeneralSettings(Request $request){
        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.manage', $organizationId);

        return Inertia::render('User/Settings/General', app(OrganizationSettingsViewData::class)->general((int) $organizationId));
    }

    public function viewWhatsappSettings(Request $request){
        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.manage', $organizationId);

        $settings = Setting::whereIn('key', ['is_embedded_signup_active', 'whatsapp_client_id', 'whatsapp_client_secret', 'whatsapp_config_id'])
            ->pluck('value', 'key');

        $organization = Organization::where('id', $organizationId)->first();
        $organizationMetadata = $organization && $organization->metadata
            ? json_decode($organization->metadata, true)
            : [];

        $embeddedSignupGlobalEnabled = $this->embeddedSignupGate->isGloballyEnabled();
        $embeddedSignupPlanEnabled = $this->embeddedSignupGate->isPlanEnabled($organizationId);
        $embeddedSignupOrgEnabled = $this->embeddedSignupGate->isOrganizationEnabled($organizationId);
        $embeddedSignupActive = $embeddedSignupGlobalEnabled && $embeddedSignupPlanEnabled && $embeddedSignupOrgEnabled;
        $manualFallbackReason = null;

        if (!$embeddedSignupActive) {
            if (!$embeddedSignupGlobalEnabled) {
                $manualFallbackReason = 'global_disabled';
            } elseif (!$embeddedSignupPlanEnabled) {
                $manualFallbackReason = 'plan_disabled';
            } elseif (!$embeddedSignupOrgEnabled) {
                $manualFallbackReason = 'org_disabled';
            }
        }

        $webhookReadiness = $this->buildWhatsappWebhookReadiness(
            $organization,
            $organizationMetadata,
            $settings->get('whatsapp_client_secret')
        );

        $data = [
            'embeddedSignupActive' => $embeddedSignupActive,
            'embeddedSignupOrgEnabled' => $embeddedSignupOrgEnabled,
            'embeddedSignupGlobalEnabled' => $embeddedSignupGlobalEnabled,
            'embeddedSignupPlanEnabled' => $embeddedSignupPlanEnabled,
            'manualFallbackActive' => !$embeddedSignupActive,
            'manualFallbackReason' => $manualFallbackReason,
            'autoOpenManualSetup' => (bool) session('whatsapp_auto_open_manual_setup', false),
            'graphAPIVersion' => config('graph.api_version'),
            'appId' => $settings->get('whatsapp_client_id', null),
            'configId' => $settings->get('whatsapp_config_id', null),
            'settings' => $organization,
            'hasEncryptedWhatsappToken' => !empty(data_get($organizationMetadata, 'whatsapp.access_token_encrypted')),
            'hasEncryptedWhatsappAppSecret' => !empty(data_get($organizationMetadata, 'whatsapp.app_secret_encrypted')),
            'whatsappWebhookReadiness' => $webhookReadiness,
            'modules' => Addon::query()->whereIn('name', \App\Support\SaClientPlanProfile::planAddonNames())->get(),
            'title' => __('Settings'),
        ];

        return Inertia::render('User/Settings/Whatsapp', $data);
    }

    public function storeWhatsappSettings(StoreWhatsappSettings $request) {
        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.manage', $organizationId);

        return $this->saveWhatsappSettings(
            $request->access_token,
            $request->app_secret,
            $request->app_id,
            $request->phone_number_id,
            $request->waba_id,
            false,
            false,
            'manual'
        );
    }

    public function toggleEmbeddedSignup(Request $request)
    {
        if ($response = $this->abortIfDemo()) {
            return $response;
        }

        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.manage', $organizationId);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $organization = Organization::findOrFail($organizationId);
        $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];

        if (!isset($metadata['addons']) || !is_array($metadata['addons'])) {
            $metadata['addons'] = [];
        }

        $metadata['addons']['embedded_signup_enabled'] = (bool) $validated['enabled'];
        $autoOpenManualSetup = false;
        $status = [
            'type' => 'success',
            'message' => __('Embedded signup preference updated successfully.'),
        ];

        if (!$validated['enabled']) {
            $hasWhatsappConfig = isset($metadata['whatsapp']) && is_array($metadata['whatsapp']);
            $isEmbeddedIntegration = ((int) data_get($metadata, 'whatsapp.is_embedded_signup', 0)) === 1;

            if ($hasWhatsappConfig && $isEmbeddedIntegration) {
                // Always switch local mode to manual when org disables embedded signup,
                // even if the WhatsApp callback override fails.
                $metadata['whatsapp']['is_embedded_signup'] = 0;
                $metadata['whatsapp']['token_source'] = 'manual';

                $resolvedToken = app(WhatsappAccessTokenRefreshService::class)->resolveTokenForOrganization((int) $organizationId, true);
                $wabaId = data_get($metadata, 'whatsapp.waba_id');

                if (!empty($resolvedToken) && !empty($wabaId) && !empty($organization->identifier)) {
                    $whatsappService = $this->makeWhatsappService($resolvedToken, config('graph.api_version'), data_get($metadata, 'whatsapp.app_id'), data_get($metadata, 'whatsapp.phone_number_id'), $wabaId, $organizationId);

                    $manualWebhookResponse = $whatsappService->overrideCallbackUrl(
                        URL::to('/webhook/whatsapp/' . $organization->identifier),
                        $organization->identifier
                    );

                    if (($manualWebhookResponse->success ?? false) === true) {
                        $status['message'] = __('Embedded signup was disabled and your integration was switched to manual mode.');
                    } else {
                        $autoOpenManualSetup = true;
                        $status = [
                            'type' => 'warning',
                            'message' => __('Embedded signup was disabled, but automatic webhook switch failed. Please complete manual WhatsApp setup.'),
                        ];
                    }
                } else {
                    $metadata['whatsapp']['is_embedded_signup'] = 0;
                    $metadata['whatsapp']['token_source'] = 'manual';
                    $autoOpenManualSetup = true;
                    $status = [
                        'type' => 'warning',
                        'message' => __('Embedded signup was disabled. Please complete manual WhatsApp setup.'),
                    ];
                }
            } elseif (!$hasWhatsappConfig) {
                $autoOpenManualSetup = true;
            }
        }

        $organization->metadata = json_encode($metadata);
        $organization->save();

        $response = back()->with('status', $status);
        if ($autoOpenManualSetup) {
            $response->with('whatsapp_auto_open_manual_setup', true);
        }

        return $response;
    }

    public function exchangeEmbeddedSignupCode(ExchangeEmbeddedSignupCodeRequest $request)
    {
        if ($response = $this->abortIfDemo()) {
            return $response;
        }

        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.manage', $organizationId);
        $userId = auth()->id();

        $this->embeddedSignupAuditService->record(
            'exchange_code.requested',
            'started',
            [
                'waba_id' => $request->waba_id,
                'phone_number_id' => $request->phone_number_id,
            ],
            null,
            $organizationId,
            $userId
        );

        if (!$this->embeddedSignupGate->isGloballyEnabled()) {
            $this->embeddedSignupAuditService->record(
                'exchange_code.validation',
                'failed',
                [],
                'EMBEDDED_DISABLED',
                $organizationId,
                $userId,
                __('Embedded signup is currently disabled.')
            );

            return back()->with('status', [
                'type' => 'error',
                'message' => __('Embedded signup is currently disabled.'),
            ]);
        }

        if (!$this->embeddedSignupGate->isPlanEnabled($organizationId)) {
            $this->embeddedSignupAuditService->record(
                'exchange_code.validation',
                'failed',
                [],
                'EMBEDDED_DISABLED',
                $organizationId,
                $userId,
                __('Embedded signup is not available for your organization.')
            );

            return back()->with('status', [
                'type' => 'error',
                'message' => __('Embedded signup is not available for your organization.'),
            ]);
        }

        if (!$this->embeddedSignupGate->isOrganizationEnabled($organizationId)) {
            $this->embeddedSignupAuditService->record(
                'exchange_code.validation',
                'failed',
                [],
                'EMBEDDED_DISABLED',
                $organizationId,
                $userId,
                __('Embedded signup is disabled for your organization.')
            );

            return back()->with('status', [
                'type' => 'error',
                'message' => __('Embedded signup is disabled for your organization.'),
            ]);
        }

        $embeddedSignupService = new EmbeddedSignupService();
        if (!$embeddedSignupService->isConfigured()) {
            $this->embeddedSignupAuditService->record(
                'exchange_code.validation',
                'failed',
                [],
                'META_CONFIG_MISSING',
                $organizationId,
                $userId,
                __('Embedded signup is not configured correctly. Contact the administrator.')
            );

            return back()->with('status', [
                'type' => 'error',
                'message' => __('Embedded signup is not configured correctly. Contact the administrator.'),
            ]);
        }

        $exchangeResponse = $embeddedSignupService->exchangeCodeForToken($request->token);
        if (!$exchangeResponse->success) {
            $this->embeddedSignupAuditService->record(
                'exchange_code.exchange',
                'failed',
                [],
                $exchangeResponse->code ?? 'CODE_EXCHANGE_FAILED',
                $organizationId,
                $userId,
                $exchangeResponse->message
            );

            return back()->with('status', [
                'type' => 'error',
                'message' => $this->embeddedSignupMessage($exchangeResponse->code ?? 'CODE_EXCHANGE_FAILED', $exchangeResponse->message),
            ]);
        }

        $longLivedResponse = $embeddedSignupService->exchangeToLongLivedToken($exchangeResponse->data['access_token']);
        if (!$longLivedResponse->success) {
            $this->embeddedSignupAuditService->record(
                'exchange_code.long_lived',
                'failed',
                [],
                $longLivedResponse->code ?? 'LONG_TOKEN_EXCHANGE_FAILED',
                $organizationId,
                $userId,
                $longLivedResponse->message
            );

            return back()->with('status', [
                'type' => 'error',
                'message' => $this->embeddedSignupMessage($longLivedResponse->code ?? 'LONG_TOKEN_EXCHANGE_FAILED', $longLivedResponse->message),
            ]);
        }

        $onboardingAccessToken = $longLivedResponse->data['access_token'] ?? $embeddedSignupService->getFallbackOnboardingToken();

        $resolvedConnection = $embeddedSignupService->resolveWabaConnection(
            $onboardingAccessToken,
            $request->waba_id,
            $request->phone_number_id
        );

        if (!$resolvedConnection->success) {
            $this->embeddedSignupAuditService->record(
                'exchange_code.resolve_waba',
                'failed',
                [],
                $resolvedConnection->code ?? 'WABA_RESOLUTION_FAILED',
                $organizationId,
                $userId,
                $resolvedConnection->message
            );

            return back()->with('status', [
                'type' => 'error',
                'message' => $this->embeddedSignupMessage($resolvedConnection->code ?? 'WABA_RESOLUTION_FAILED', $resolvedConnection->message),
            ]);
        }

        $overrideResponse = $embeddedSignupService->overrideWabaCallback(
            $onboardingAccessToken,
            $resolvedConnection->data['waba_id']
        );

        if (!$overrideResponse->success) {
            $this->embeddedSignupAuditService->record(
                'exchange_code.override_webhook',
                'failed',
                [
                    'waba_id' => $resolvedConnection->data['waba_id'] ?? null,
                ],
                $overrideResponse->code ?? 'WEBHOOK_OVERRIDE_FAILED',
                $organizationId,
                $userId,
                $overrideResponse->message
            );

            return back()->with('status', [
                'type' => 'error',
                'message' => $this->embeddedSignupMessage($overrideResponse->code ?? 'WEBHOOK_OVERRIDE_FAILED', $overrideResponse->message),
            ]);
        }

        try {
            DB::beginTransaction();

            $persistResponse = $this->persistWhatsappSettings(
                $onboardingAccessToken,
                null,
                $embeddedSignupService->getAppId(),
                $resolvedConnection->data['phone_number_id'] ?? null,
                $resolvedConnection->data['waba_id'] ?? null,
                false,
                true,
                'embedded_signup',
                $longLivedResponse->data['expires_at'] ?? null
            );

            if (!$persistResponse->success) {
                DB::rollBack();

                $this->embeddedSignupAuditService->record(
                    'exchange_code.persist',
                    'failed',
                    [],
                    $persistResponse->code ?? 'WABA_RESOLUTION_FAILED',
                    $organizationId,
                    $userId,
                    $persistResponse->message
                );

                return back()->with('status', [
                    'type' => 'error',
                    'message' => $this->embeddedSignupMessage($persistResponse->code ?? 'WABA_RESOLUTION_FAILED', $persistResponse->message),
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Embedded signup exchange failed during persistence', [
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            $this->embeddedSignupAuditService->record(
                'exchange_code.persist',
                'failed',
                [],
                'WABA_RESOLUTION_FAILED',
                $organizationId,
                $userId,
                __('Unable to finalize embedded signup connection.')
            );

            return back()->with('status', [
                'type' => 'error',
                'message' => __('Unable to finalize embedded signup connection.'),
            ]);
        }

        $this->embeddedSignupAuditService->record(
            'exchange_code.completed',
            'success',
            [
                'waba_id' => $resolvedConnection->data['waba_id'] ?? null,
                'phone_number_id' => $resolvedConnection->data['phone_number_id'] ?? null,
            ],
            null,
            $organizationId,
            $userId,
            __('Embedded signup connected successfully.')
        );

        return back()->with('status', [
            'type' => 'success',
            'message' => __('Embedded signup connected successfully.'),
        ]);
    }

    public function updateToken(Request $request) {
        if ($response = $this->abortIfDemo()) {
            return $response;
        }
        
        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.manage', $organizationId);
        $config = Organization::findOrFail($organizationId)->metadata;
        $config = $config ? json_decode($config, true) : [];
        $request->validate([
            'access_token' => ['required', 'string'],
            'app_secret' => ['nullable', 'string'],
        ]);

        return $this->saveWhatsappSettings(
            $request->access_token,
            $request->input('app_secret'),
            $config['whatsapp']['app_id'] ?? null,
            $config['whatsapp']['phone_number_id'] ?? null,
            $config['whatsapp']['waba_id'] ?? null,
            false,
            false,
            'manual',
            $config['whatsapp']['access_token_expires_at'] ?? null
        );
    }
    
    public function refreshWhatsappData() {
        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.manage', $organizationId);
        $config = Organization::findOrFail($organizationId)->metadata;
        $config = $config ? json_decode($config, true) : [];

        $resolvedToken = app(WhatsappAccessTokenRefreshService::class)
            ->resolveTokenForOrganization((int) $organizationId, true);

        if (isset($config['whatsapp']['is_embedded_signup']) && (int) $config['whatsapp']['is_embedded_signup'] === 1) {
            $wabaId = $config['whatsapp']['waba_id'] ?? null;

            if (!empty($resolvedToken) && !empty($wabaId)) {
                (new EmbeddedSignupService())->overrideWabaCallback((string) $resolvedToken, (string) $wabaId);
            }
        }
    
        return $this->saveWhatsappSettings(
            $resolvedToken,
            null,
            $config['whatsapp']['app_id'] ?? null,
            $config['whatsapp']['phone_number_id'] ?? null,
            $config['whatsapp']['waba_id'] ?? null,
            false,
            null,
            $config['whatsapp']['token_source'] ?? 'manual',
            $config['whatsapp']['access_token_expires_at'] ?? null
        );
    }

    public function contacts(Request $request){
        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.manage', $organizationId);
        
        if ($request->isMethod('get')) {
            $contactFieldService = new ContactFieldService($organizationId);
            $settings = Organization::where('id', session()->get('current_organization'))->first();

            return Inertia::render('User/Settings/Contact', [
                'title' => __('Settings'),
                'filters' => $request->all(),
                'rows' => $contactFieldService->get($request),
                'settings' => $settings,
                'modules' => Addon::query()->whereIn('name', \App\Support\SaClientPlanProfile::planAddonNames())->get(),
            ]);
        } else if($request->isMethod('post')) {
            $currentOrganizationId = session()->get('current_organization');
            $organizationConfig = Organization::where('id', $currentOrganizationId)->first();
    
            $metadataArray = $organizationConfig->metadata ? json_decode($organizationConfig->metadata, true) : [];

            $metadataArray['contacts']['location'] = $request->location;

            $updatedMetadataJson = json_encode($metadataArray);

            $organizationConfig->metadata = $updatedMetadataJson;
            $organizationConfig->save();

            return back()->with(
                'status', [
                    'type' => 'success', 
                    'message' => __('Settings updated successfully')
                ]
            );
        }
    }

    public function tickets(Request $request){
        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.manage', $organizationId);
        
        if ($request->isMethod('get')) {
            $contactFieldService = new ContactFieldService($organizationId);
            $settings = Organization::where('id', session()->get('current_organization'))->first();

            return Inertia::render('User/Settings/Ticket', [
                'title' => __('Settings'),
                'filters' => $request->all(),
                'rows' => $contactFieldService->get($request),
                'settings' => $settings,
                'modules' => Addon::query()->whereIn('name', \App\Support\SaClientPlanProfile::planAddonNames())->get(),
            ]);
        } else if($request->isMethod('post')) {
            $currentOrganizationId = session()->get('current_organization');
            $organizationConfig = Organization::where('id', $currentOrganizationId)->first();
    
            $metadataArray = $organizationConfig->metadata ? json_decode($organizationConfig->metadata, true) : [];

            $metadataArray['tickets']['active'] = $request->active;
            $metadataArray['tickets']['auto_assignment'] = $request->auto_assignment;
            $metadataArray['tickets']['reassign_reopened_chats'] = $request->reassign_reopened_chats;
            $metadataArray['tickets']['allow_agents_to_view_all_chats'] = $request->allow_agents_to_view_all_chats;

            $updatedMetadataJson = json_encode($metadataArray);

            $organizationConfig->metadata = $updatedMetadataJson;
            $organizationConfig->save();

            /*return back()->with(
                'status', [
                    'type' => 'success', 
                    'message' => __('Settings updated successfully')
                ]
            );*/
        }
    }

    public function automation(Request $request){
        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.manage', $organizationId);
        $sequenceService = app(AutomationResponseSequenceService::class);
        
        if ($request->isMethod('get')) {
            $settings = Organization::where('id', $organizationId)->first();

            return Inertia::render('User/Settings/Automation', [
                'title' => __('Settings'),
                'settings' => $settings,
                'modules' => Addon::query()->whereIn('name', \App\Support\SaClientPlanProfile::planAddonNames())->get(),
                'automationSequenceOptions' => $sequenceService->sanitize([], (int) $organizationId),
            ]);
        } else if($request->isMethod('post')) {
            $currentOrganizationId = session()->get('current_organization');
            $organizationConfig = Organization::where('id', $currentOrganizationId)->first();
    
            $metadataArray = $organizationConfig->metadata ? json_decode($organizationConfig->metadata, true) : [];
            $allowedSequence = $sequenceService->sanitize(
                (array) $request->response_sequence,
                (int) $currentOrganizationId
            );

            $metadataArray['automation']['response_sequence'] = array_values(array_unique($allowedSequence));

            $updatedMetadataJson = json_encode($metadataArray);
            $organizationConfig->metadata = $updatedMetadataJson;
            $organizationConfig->save();

            /*return back()->with(
                'status', [
                    'type' => 'success', 
                    'message' => __('Settings updated successfully')
                ]
            );*/
        }
    }

    public function whatsappBusinessProfileUpdate(StoreWhatsappProfile $request){
        if ($response = $this->abortIfDemo()) {
            return $response;
        }

        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.manage', $organizationId);
        $config = Organization::where('id', $organizationId)->first()->metadata;
        $config = $config ? json_decode($config, true) : [];

        if(isset($config['whatsapp'])){
            $accessToken = app(WhatsappAccessTokenRefreshService::class)->resolveTokenForOrganization((int) $organizationId, true);
            $apiVersion = config('graph.api_version');
            $appId = $config['whatsapp']['app_id'] ?? null;
            $phoneNumberId = $config['whatsapp']['phone_number_id'] ?? null;
            $wabaId = $config['whatsapp']['waba_id'] ?? null;

            $whatsappService = $this->makeWhatsappService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $organizationId);
            
            $response = $whatsappService->updateBusinessProfile($request);

            if($response->success === true){
                return back()->with(
                    'status', [
                        'type' => 'success', 
                        'message' => __('Your whatsapp business profile has been changed successfully!')
                    ]
                );
            } else {
                return back()->with(
                    'status', [
                        'type' => 'error', 
                        'message' => __('Something went wrong! Your business profile could not be updated!')
                    ]
                );
            }
        }

        return back()->with(
            'status', [
                'type' => 'error', 
                'message' => __('Setup your whatsapp integration first!')
            ]
        );
    }

    public function deleteWhatsappIntegration(Request $request){
        if ($response = $this->abortIfDemo()) {
            return $response;
        }

        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.manage', $organizationId);
        
        $organizationConfig = Organization::where('id', $organizationId)->first();
        $config = $organizationConfig->metadata ? json_decode($organizationConfig->metadata, true) : [];

        if(isset($config['whatsapp'])){
            if(((int) ($config['whatsapp']['is_embedded_signup'] ?? 0)) === 1){
                //Unsubscribe webhook
                $organizationId = session()->get('current_organization');
                $apiVersion = config('graph.api_version');

                $accessToken = app(WhatsappAccessTokenRefreshService::class)->resolveTokenForOrganization((int) $organizationId, true);
                $appId = $config['whatsapp']['app_id'] ?? null;
                $phoneNumberId = $config['whatsapp']['phone_number_id'] ?? null;
                $wabaId = $config['whatsapp']['waba_id'] ?? null;
            
                $whatsappService = $this->makeWhatsappService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $organizationId);
                $unsubscribe = $whatsappService->unSubscribeToWaba();
            }
            
            //Delete whatsapp settings
            if (isset($config['whatsapp'])) {
                unset($config['whatsapp']);
            }

            $updatedMetadataJson = json_encode($config);
            $organizationConfig->metadata = $updatedMetadataJson;
            $organizationConfig->save();

            //Delete templates
            $templates = Template::where('organization_id', $organizationId)->get();
            foreach ($templates as $template) {
                $template->deleted_at = now();
                $template->save();
            }

            return back()->with(
                'status', [
                    'type' => 'success', 
                    'message' => __('Your integration has been removed successfully!')
                ]
            );
        }

        return back()->with(
            'status', [
                'type' => 'error', 
                'message' => __('Setup your whatsapp integration first!')
            ]
        );
    }

    private function saveWhatsappSettings(
        $accessToken,
        $appSecret,
        $appId,
        $phoneNumberId,
        $wabaId,
        $subscribeToWebhook = false,
        $isEmbeddedSignup = null,
        $tokenSource = 'manual',
        $tokenExpiresAt = null
    ) {
        $response = $this->persistWhatsappSettings(
            $accessToken,
            $appSecret,
            $appId,
            $phoneNumberId,
            $wabaId,
            $subscribeToWebhook,
            $isEmbeddedSignup,
            $tokenSource,
            $tokenExpiresAt
        );

        if (!$response->success) {
            return back()->with('status', [
                'type' => 'error',
                'message' => $response->message ?? __('Something went wrong. Refresh the page and try again'),
            ]);
        }

        if (!empty($response->data['warning_message'])) {
            return back()->with('status', [
                'type' => 'warning',
                'message' => $response->data['warning_message'],
            ]);
        }

        return back()->with('status', [
            'type' => 'success',
            'message' => __('Whatsapp settings updated successfully'),
        ]);
    }

    private function persistWhatsappSettings(
        $accessToken,
        $appSecret,
        $appId,
        $phoneNumberId,
        $wabaId,
        $subscribeToWebhook = false,
        $isEmbeddedSignup = null,
        $tokenSource = 'manual',
        $tokenExpiresAt = null
    ): object {
        $tokenExchangeWarning = null;
        if ($tokenSource === 'manual' && !empty($accessToken) && !empty($appId) && !empty($appSecret)) {
            $tokenExchange = app(WhatsappAccessTokenRefreshService::class)->exchangeToLongLivedToken((string) $accessToken, (string) $appId, (string) $appSecret);
            if ($tokenExchange->success ?? false) {
                $accessToken = $tokenExchange->access_token;
                $tokenExpiresAt = $tokenExchange->expires_at ?? $tokenExpiresAt;
            } else {
                $tokenExchangeWarning = __('The access token was saved, but automatic long-lived token exchange failed. You may need to refresh token manually later.');
            }
        }
        if (empty($accessToken) || empty($wabaId)) {
            return $this->errorResult(
                'WABA_RESOLUTION_FAILED',
                __('Invalid WhatsApp integration payload. Missing access token or WABA ID.')
            );
        }
        $organizationId = session()->get('current_organization');
        $apiVersion = config('graph.api_version');
        $whatsappService = $this->makeWhatsappService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $organizationId);

        $phoneNumberResponse = $whatsappService->getPhoneNumberId($accessToken, $wabaId);
        if (!$phoneNumberResponse->success) {
            return $this->errorResult(
                'WABA_RESOLUTION_FAILED',
                $this->extractServiceError($phoneNumberResponse, __('Unable to fetch WhatsApp phone number information.'))
            );
        }
        $phoneNumberData = $phoneNumberResponse->data ?? new \stdClass();
        $resolvedPhoneNumberId = $phoneNumberData->id ?? $phoneNumberId;

        if (empty($resolvedPhoneNumberId)) {
            return $this->errorResult(
                'WABA_RESOLUTION_FAILED',
                __('Unable to fetch WhatsApp phone number information.')
            );
        }

        $phoneNumberStatusResponse = $whatsappService->getPhoneNumberStatus($accessToken, $resolvedPhoneNumberId);
        if (!$phoneNumberStatusResponse->success) {
            return $this->errorResult(
                'WABA_RESOLUTION_FAILED',
                $this->extractServiceError($phoneNumberStatusResponse, __('Unable to fetch WhatsApp phone status.'))
            );
        }
        $accountReviewStatusResponse = $whatsappService->getAccountReviewStatus($accessToken, $wabaId);
        if (!$accountReviewStatusResponse->success) {
            return $this->errorResult(
                'WABA_RESOLUTION_FAILED',
                $this->extractServiceError($accountReviewStatusResponse, __('Unable to fetch WhatsApp account review status.'))
            );
        }
        $businessProfileResponse = $whatsappService->getBusinessProfile($accessToken, $resolvedPhoneNumberId);
        if (!$businessProfileResponse->success) {
            return $this->errorResult(
                'WABA_RESOLUTION_FAILED',
                $this->extractServiceError($businessProfileResponse, __('Unable to fetch WhatsApp business profile.'))
            );
        }
        $organizationConfig = Organization::where('id', $organizationId)->first();
        if (!$organizationConfig) {
            return $this->errorResult('WABA_RESOLUTION_FAILED', __('Organization not found.'));
        }

        $warningMessage = $tokenExchangeWarning;

        $metadataArray = $organizationConfig->metadata ? json_decode($organizationConfig->metadata, true) : [];
        if (!isset($metadataArray['addons']) || !is_array($metadataArray['addons'])) {
            $metadataArray['addons'] = [];
        }

        if (!array_key_exists('embedded_signup_enabled', $metadataArray['addons'])) {
            $metadataArray['addons']['embedded_signup_enabled'] = true;
        }

        if (!isset($metadataArray['whatsapp']) || !is_array($metadataArray['whatsapp'])) {
            $metadataArray['whatsapp'] = [];
        }

        if ($isEmbeddedSignup === null) {
            $metadataArray['whatsapp']['is_embedded_signup'] = $metadataArray['whatsapp']['is_embedded_signup'] ?? 0;
        } else {
            $metadataArray['whatsapp']['is_embedded_signup'] = $isEmbeddedSignup ? 1 : 0;
        }

        $metadataArray['whatsapp']['access_token_encrypted'] = $this->tokenVault->encryptToken($accessToken);
        $metadataArray['whatsapp']['access_token_expires_at'] = $tokenExpiresAt;
        $metadataArray['whatsapp']['access_token_last_refreshed_at'] = now()->toDateTimeString();
        $metadataArray['whatsapp']['token_source'] = $tokenSource;
        unset($metadataArray['whatsapp']['access_token']);
        if (!empty($appSecret)) {
            $metadataArray['whatsapp']['app_secret_encrypted'] = $this->tokenVault->encryptAppSecret($appSecret);
        }
        unset($metadataArray['whatsapp']['app_secret']);
        $metadataArray['whatsapp']['app_id'] = $appId;
        $metadataArray['whatsapp']['waba_id'] = $wabaId;
        $metadataArray['whatsapp']['phone_number_id'] = $resolvedPhoneNumberId;
        $metadataArray['whatsapp']['display_phone_number'] = $phoneNumberData->display_phone_number ?? null;
        $metadataArray['whatsapp']['verified_name'] = $phoneNumberData->verified_name ?? null;
        $metadataArray['whatsapp']['quality_rating'] = $phoneNumberData->quality_rating ?? null;
        $metadataArray['whatsapp']['name_status'] = $phoneNumberData->name_status ?? null;
        $metadataArray['whatsapp']['messaging_limit_tier'] = $phoneNumberData->messaging_limit_tier ?? null;
        $metadataArray['whatsapp']['max_daily_conversation_per_phone'] = null;
        $metadataArray['whatsapp']['max_phone_numbers_per_business'] = null;
        $metadataArray['whatsapp']['number_status'] = $phoneNumberStatusResponse->data->status ?? null;
        $metadataArray['whatsapp']['code_verification_status'] = $phoneNumberStatusResponse->data->code_verification_status ?? null;
        $metadataArray['whatsapp']['business_verification'] = '';
        $metadataArray['whatsapp']['account_review_status'] = $accountReviewStatusResponse->data->account_review_status ?? null;
        $metadataArray['whatsapp']['business_profile']['about'] = $businessProfileResponse->data->about ?? null;
        $metadataArray['whatsapp']['business_profile']['address'] = $businessProfileResponse->data->address ?? null;
        $metadataArray['whatsapp']['business_profile']['description'] = $businessProfileResponse->data->description ?? null;
        $metadataArray['whatsapp']['business_profile']['industry'] = $businessProfileResponse->data->vertical ?? null;
        $metadataArray['whatsapp']['business_profile']['email'] = $businessProfileResponse->data->email ?? null;

        if ($isEmbeddedSignup === false) {
            $manualWebhookResponse = $whatsappService->overrideCallbackUrl(
                URL::to('/webhook/whatsapp/' . $organizationConfig->identifier),
                $organizationConfig->identifier
            );

            if (($manualWebhookResponse->success ?? false) !== true) {
                $webhookWarning = __('Unable to override webhook callback for manual setup. Please update the callback URL and verify token manually.');
                $warningMessage = empty($warningMessage) ? $webhookWarning : trim($warningMessage . ' ' . $webhookWarning);
            }
        }

        $organizationConfig->metadata = json_encode($metadataArray);
        if (!$organizationConfig->save()) {
            return $this->errorResult('WABA_RESOLUTION_FAILED', __('Something went wrong. Refresh the page and try again'));
        }

        $whatsappService->syncTemplates($accessToken, $wabaId);

        return $this->successResult([
            'warning_message' => $warningMessage,
        ]);
    }

    private function extractServiceError(object $response, string $fallback): string
    {
        if (isset($response->message) && !empty($response->message)) {
            return $response->message;
        }

        if (isset($response->data) && isset($response->data->error) && isset($response->data->error->message)) {
            return $response->data->error->message;
        }

        return $fallback;
    }

    private function buildWhatsappWebhookReadiness(?Organization $organization, array $organizationMetadata, ?string $embeddedAppSecret): array
    {
        $hasWhatsappConfig = !empty(data_get($organizationMetadata, 'whatsapp'));
        $isEmbeddedSignup = ((int) data_get($organizationMetadata, 'whatsapp.is_embedded_signup', 0)) === 1;
        $manualSecretConfigured = !empty($this->tokenVault->resolveAppSecretFromMetadata($organizationMetadata));
        $embeddedSecretConfigured = !empty($embeddedAppSecret);
        $secretConfigured = $isEmbeddedSignup ? $embeddedSecretConfigured : $manualSecretConfigured;

        if (!$hasWhatsappConfig) {
            $status = 'missing_integration';
        } elseif ($secretConfigured) {
            $status = 'configured';
        } else {
            $status = 'missing_secret';
        }

        return [
            'status' => $status,
            'mode' => $isEmbeddedSignup ? 'embedded_signup' : 'manual',
            'secretConfigured' => $secretConfigured,
            'canReceiveInbound' => $hasWhatsappConfig && $secretConfigured,
            'webhookUrl' => !$isEmbeddedSignup && $organization ? URL::to('/webhook/whatsapp/' . $organization->identifier) : null,
            'verifyToken' => !$isEmbeddedSignup && $organization ? $organization->identifier : null,
            'message' => $this->resolveWhatsappWebhookReadinessMessage($status, $isEmbeddedSignup),
        ];
    }

    private function resolveWhatsappWebhookReadinessMessage(string $status, bool $isEmbeddedSignup): string
    {
        if ($status === 'configured') {
            return $isEmbeddedSignup
                ? __('Inbound webhook signature validation is active using the embedded app secret.')
                : __('Inbound webhook signature validation is active for your manual WhatsApp integration.');
        }

        if ($status === 'missing_secret') {
            return $isEmbeddedSignup
                ? __('Inbound webhook signature validation is blocked until the embedded app secret is configured by the administrator.')
                : __('Inbound webhook signature validation is blocked until you save the WhatsApp app secret for this organization.');
        }

        return __('WhatsApp inbound webhook readiness will be available after the integration is configured.');
    }

    private function makeWhatsappService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $organizationId): WhatsappService
    {
        return app()->makeWith(WhatsappService::class, ['accessToken' => $accessToken, 'apiVersion' => $apiVersion, 'appId' => $appId, 'phoneNumberId' => $phoneNumberId, 'wabaId' => $wabaId, 'organizationId' => $organizationId]);
    }

    private function successResult(array $data = []): object
    {
        return (object) ['success' => true, 'message' => null, 'code' => null, 'data' => $data];
    }

    private function errorResult(string $code, string $message): object
    {
        return (object) ['success' => false, 'message' => $message, 'code' => $code, 'data' => []];
    }

    private function embeddedSignupMessage(?string $code, ?string $defaultMessage = null): string
    {
        $messageMap = [
            'EMBEDDED_DISABLED' => __('Embedded signup is currently disabled.'),
            'META_CONFIG_MISSING' => __('Embedded signup is not configured correctly. Contact the administrator.'),
            'CODE_EXCHANGE_FAILED' => __('Unable to exchange embedded signup code.'),
            'LONG_TOKEN_EXCHANGE_FAILED' => __('Unable to generate a long-lived access token.'),
            'WABA_RESOLUTION_FAILED' => __('Unable to resolve WhatsApp business account from embedded signup.'),
            'WEBHOOK_OVERRIDE_FAILED' => __('Unable to override webhook callback URL.'),
        ];

        if ($code && isset($messageMap[$code])) {
            return $defaultMessage ?: $messageMap[$code];
        }

        return $defaultMessage ?: __('Something went wrong. Refresh the page and try again');
    }

    protected function abortIfDemo(){
        $organizationId = session()->get('current_organization');

        if (app()->environment('demo') && $organizationId == 1) {
            return back()->with(
                'status', [
                    'type' => 'error', 
                    'message' => __('You cannot perform this action using the demo account. To test this feature, please create your own account.')
                ]
            );
        }

        return null;
    }
}
