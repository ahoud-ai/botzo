<?php

namespace Modules\IntelliReply\Controllers;

use App\Helpers\CustomHelper;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Contact;
use App\Models\Organization;
use App\Services\IntelliReply\AiEmbeddingService;
use App\Services\IntelliReply\AiKeyResolver;
use App\Services\IntelliReply\AiPersonaPromptBuilder;
use App\Services\Ai\AiProviderManager;
use App\Services\OrganizationUsageSummaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\IntelliReply\Models\Document;
use Modules\IntelliReply\Models\Faq;
use Modules\IntelliReply\Resources\DocumentResource;

class MainController extends BaseController
{
    private const DEFAULT_MODEL = 'gpt-4o-mini';
    private const DEFAULT_MAX_TOKENS = 512;
    private const DEFAULT_TEMPERATURE = 0.7;
    private const DEFAULT_EMBEDDING_MODEL = 'text-embedding-3-small';

    public function index(Request $request)
    {
        $organizationId = (int) session()->get('current_organization');
        $this->checkPermission('automations.view_all', $organizationId);
        $this->ensureAiAssistantEnabled($organizationId);

        $organization = Organization::findOrFail($organizationId);
        $searchTerm = $request->query('search');
        $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];
        $metadata = is_array($metadata) ? $metadata : [];
        $ai = isset($metadata['ai']) && is_array($metadata['ai']) ? $metadata['ai'] : [];

        $keyResolver = app(AiKeyResolver::class);
        $policy = $keyResolver->getPolicyConfig();
        $requestedKeySource = data_get($ai, 'key_source', 'auto');
        if (!in_array($requestedKeySource, ['organization', 'global', 'auto'], true)) {
            $requestedKeySource = 'auto';
        }
        $resolvedKey = $keyResolver->resolveForOrganization($metadata, $requestedKeySource, $organizationId);

        $canSetOrgKey = $this->policyAllowsOrganizationKey($policy)
            && (bool) ($resolvedKey['organization_key_allowed'] ?? true);
        $requestedKeySource = $this->normalizeRequestedKeySource($policy, $requestedKeySource, $canSetOrgKey);

        if ($requestedKeySource !== data_get($ai, 'key_source', 'auto')) {
            $resolvedKey = $keyResolver->resolveForOrganization($metadata, $requestedKeySource, $organizationId);
        }

        $ai['has_api_key'] = $resolvedKey['key'] !== null;
        $ai['key_source'] = $requestedKeySource;
        $ai['effective_key_source'] = $resolvedKey['source'] ?? null;
        $ai['key_policy'] = $policy['policy'];
        $ai['allow_org_override'] = $policy['allow_org_override'];
        $ai['has_global_key'] = $policy['has_global_key'];
        $ai['can_set_org_key'] = $canSetOrgKey;
        $ai['organization_key_allowed'] = (bool) ($resolvedKey['organization_key_allowed'] ?? true);
        $ai['organization_key_locked'] = !$canSetOrgKey;

        unset($ai['api_key'], $ai['api_key_encrypted']);
        $ai['model'] = $ai['model'] ?? self::DEFAULT_MODEL;
        $ai['max_tokens'] = $ai['max_tokens'] ?? self::DEFAULT_MAX_TOKENS;
        $ai['temperature'] = $ai['temperature'] ?? self::DEFAULT_TEMPERATURE;
        $ai['embedding_model'] = $ai['embedding_model'] ?? $this->defaultEmbeddingModel();
        $metadata['ai'] = $ai;
        $organization->metadata = json_encode($metadata);

        $data['title'] = __('Settings');
        $data['rows'] = DocumentResource::collection((new Document())->listAll($organizationId, $searchTerm));
        $data['settings'] = $organization;
        $data['models'] = config('models');
        $data['embeddingModels'] = collect($this->availableEmbeddingModels())
            ->map(fn (string $value) => ['value' => $value, 'label' => $value])
            ->values()
            ->all();
        $data['audioResponseModels'] = $this->availableAudioResponseModels();
        $data['voices'] = config('voices');
        $data['filters'] = request()->all();
        $data['aimodule'] = CustomHelper::isModuleEnabled('AI Assistant', $organizationId);
        $data['fbmodule'] = CustomHelper::isModuleEnabled('Flow builder', $organizationId);
        $data['aiUsageSummary'] = app(OrganizationUsageSummaryService::class)->build($organizationId);
        $data['defaultDisclosureText'] = (string) config('intellireply.disclosure.default_text');
        $data['maxOutputTokens'] = (int) config('intellireply.safety.max_output_tokens', 1024);
        $data['persona'] = app(AiPersonaPromptBuilder::class)->persona($metadata);
        $data['personaLimits'] = AiPersonaPromptBuilder::LIMITS;
        $data['faqs'] = Faq::where('organization_id', $organizationId)->orderBy('id')->get(['uuid', 'question', 'answer']);
        $data['faqLimits'] = ['items' => Faq::MAX_PER_ORGANIZATION, 'question' => Faq::MAX_QUESTION_LENGTH, 'answer' => Faq::MAX_ANSWER_LENGTH];

        return Inertia::render('IntelliReply::User/Index', $data);
    }

    public function activate(Request $request)
    {
        $organizationId = (int) session()->get('current_organization');
        $this->checkPermission('automations.edit', $organizationId);
        $this->ensureAiAssistantEnabled($organizationId);

        $payload = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        $organization = Organization::findOrFail($organizationId);
        $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];
        $metadata = is_array($metadata) ? $metadata : [];
        $metadata['ai']['active'] = (bool) $payload['active'];

        $organization->metadata = json_encode($metadata);

        if ($organization->save()) {
            Log::info('intellireply.activate.updated', [
                'organization_id' => $organizationId,
                'active' => (bool) $payload['active'],
                'user_id' => auth()->id(),
            ]);

            return back()->with(
                'status', [
                    'type' => 'success',
                    'message' => (bool) $payload['active'] ? __('AI assistant activated') : __('AI assistant deactivated'),
                ]
            );
        }

        return back()->with(
            'status', [
                'type' => 'error',
                'message' => __('Something went wrong. Refresh the page and try again'),
            ]
        );
    }

    public function assistant_setup(Request $request)
    {
        $organizationId = (int) session()->get('current_organization');
        $this->checkPermission('automations.edit', $organizationId);
        $this->ensureAiAssistantEnabled($organizationId);

        $payload = $request->validate([
            'enable_automatic_responses' => ['required', 'boolean'],
            'start_keywords' => ['nullable', 'string', 'max:1000'],
            'stop_keywords' => ['nullable', 'string', 'max:1000'],
            'disclosure_text' => ['nullable', 'string', 'max:500'],
        ]);

        $organization = Organization::findOrFail($organizationId);
        $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];
        $metadata = is_array($metadata) ? $metadata : [];
        $metadata['ai']['enable_automatic_responses'] = (bool) $payload['enable_automatic_responses'];
        $metadata['ai']['start_keywords'] = $payload['start_keywords'] ?? null;
        $metadata['ai']['stop_keywords'] = $payload['stop_keywords'] ?? null;
        $metadata['ai']['disclosure_text'] = trim((string) ($payload['disclosure_text'] ?? '')) ?: null;
        $organization->metadata = json_encode($metadata);

        if ($organization->save()) {
            Log::info('intellireply.assistant_setup.updated', [
                'organization_id' => $organizationId,
                'enable_automatic_responses' => (bool) $payload['enable_automatic_responses'],
                'user_id' => auth()->id(),
            ]);

            return back()->with(
                'status', [
                    'type' => 'success',
                    'message' => __('Settings updated successfully'),
                ]
            );
        }

        return back()->with(
            'status', [
                'type' => 'error',
                'message' => __('Something went wrong. Refresh the page and try again'),
            ]
        );
    }

    public function persona(Request $request)
    {
        $organizationId = (int) session()->get('current_organization');
        $this->checkPermission('automations.edit', $organizationId);
        $this->ensureAiAssistantEnabled($organizationId);

        $limits = AiPersonaPromptBuilder::LIMITS;
        $payload = $request->validate([
            'business_name' => ['nullable', 'string', 'max:'.$limits['business_name']],
            'business_description' => ['nullable', 'string', 'max:'.$limits['business_description']],
            'business_hours_address' => ['nullable', 'string', 'max:'.$limits['business_hours_address']],
            'tone' => ['required', Rule::in(AiPersonaPromptBuilder::TONES)],
            'dialect' => ['required', Rule::in(AiPersonaPromptBuilder::DIALECTS)],
            'extra_instructions' => ['nullable', 'string', 'max:'.$limits['extra_instructions']],
        ]);

        $organization = Organization::findOrFail($organizationId);
        $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];
        $metadata = is_array($metadata) ? $metadata : [];
        $metadata['ai']['persona'] = [
            'business_name' => trim((string) ($payload['business_name'] ?? '')) ?: null,
            'business_description' => trim((string) ($payload['business_description'] ?? '')) ?: null,
            'business_hours_address' => trim((string) ($payload['business_hours_address'] ?? '')) ?: null,
            'tone' => $payload['tone'],
            'dialect' => $payload['dialect'],
            'extra_instructions' => trim((string) ($payload['extra_instructions'] ?? '')) ?: null,
        ];
        $organization->metadata = json_encode($metadata);
        $organization->save();

        Log::info('intellireply.persona.updated', [
            'organization_id' => $organizationId,
            'tone' => $payload['tone'],
            'dialect' => $payload['dialect'],
            'user_id' => auth()->id(),
        ]);

        return back()->with('status', [
            'type' => 'success',
            'message' => __('Settings updated successfully'),
        ]);
    }

    public function enable_ai_assistant(Request $request, string $uuid)
    {
        $organizationId = (int) session()->get('current_organization');
        $this->checkPermission('automations.edit', $organizationId);
        $this->ensureAiAssistantEnabled($organizationId);

        $payload = $request->validate([
            'ai_assistant' => ['required', 'boolean'],
        ]);

        Contact::where('uuid', $uuid)
            ->where('organization_id', $organizationId)
            ->update(array_merge(
                ['ai_assistance_enabled' => (bool) $payload['ai_assistant']],
                // Turning the assistant back on by hand ends any human-takeover pause right away.
                (bool) $payload['ai_assistant'] ? ['ai_paused_until' => null] : []
            ));

        Log::info('intellireply.contact_toggle.updated', [
            'organization_id' => $organizationId,
            'contact_uuid' => $uuid,
            'ai_assistance_enabled' => (bool) $payload['ai_assistant'],
            'user_id' => auth()->id(),
        ]);

        return back()->with(
            'status', [
                'type' => 'success',
                'message' => __('AI assistant updated successfully!'),
            ]
        );
    }

    public function setup(Request $request)
    {
        $organizationId = (int) session()->get('current_organization');
        $this->checkPermission('automations.edit', $organizationId);
        $this->ensureAiAssistantEnabled($organizationId);

        $availableModels = collect(config('models', []))
            ->pluck('value')
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->values()
            ->all();

        $availableVoices = collect(config('voices', []))
            ->pluck('value')
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->values()
            ->all();
        $availableEmbeddingModels = $this->availableEmbeddingModels();
        $audioResponseModels = $this->availableAudioResponseModels();

        $payload = $request->validate([
            'active' => ['required', 'boolean'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'key_source' => ['nullable', Rule::in(['organization', 'global', 'auto'])],
            'model' => ['required', 'string', 'max:120', Rule::in($availableModels)],
            'voice' => ['nullable', 'string', 'max:120', Rule::in($availableVoices)],
            'allow_audio_response' => ['nullable', 'boolean'],
            'max_tokens' => ['nullable', 'integer', 'min:64', 'max:'.(int) config('intellireply.safety.max_output_tokens', 1024)],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'embedding_model' => ['nullable', 'string', 'max:120', Rule::in($availableEmbeddingModels)],
        ]);

        if ((bool) ($payload['allow_audio_response'] ?? false)
            && !in_array((string) $payload['model'], $audioResponseModels, true)) {
            return back()->with('status', [
                'type' => 'error',
                'message' => __('Selected model does not support audio responses. Choose an audio-capable model.'),
            ]);
        }

        $organization = Organization::findOrFail($organizationId);
        $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];
        $metadata = is_array($metadata) ? $metadata : [];
        $metadata['ai'] = isset($metadata['ai']) && is_array($metadata['ai']) ? $metadata['ai'] : [];

        $keyResolver = app(AiKeyResolver::class);
        $policyConfig = $keyResolver->getPolicyConfig();

        $existingApiKey = $keyResolver->resolveOrganizationKey($metadata);
        $incomingApiKey = trim((string) ($payload['api_key'] ?? ''));
        $apiKeyToStore = $incomingApiKey !== '' ? $incomingApiKey : $existingApiKey;

        $requestedKeySource = (string) ($payload['key_source'] ?? data_get($metadata, 'ai.key_source', 'auto'));
        if (!in_array($requestedKeySource, ['organization', 'global', 'auto'], true)) {
            $requestedKeySource = 'auto';
        }

        $policyResolution = $keyResolver->resolveForOrganization($metadata, $requestedKeySource, $organizationId);
        $canStoreOrganizationKey = $this->policyAllowsOrganizationKey($policyConfig)
            && (bool) ($policyResolution['organization_key_allowed'] ?? true);
        $requestedKeySource = $this->normalizeRequestedKeySource($policyConfig, $requestedKeySource, $canStoreOrganizationKey);

        if ($canStoreOrganizationKey && $apiKeyToStore) {
            $metadata['ai']['api_key_encrypted'] = Crypt::encryptString($apiKeyToStore);
        }
        unset($metadata['ai']['api_key']);

        $metadata['ai']['active'] = (bool) $payload['active'];
        $metadata['ai']['key_source'] = $requestedKeySource;
        $metadata['ai']['platform'] = 'OpenAI';
        $metadata['ai']['model'] = $payload['model'] ?: self::DEFAULT_MODEL;
        $metadata['ai']['embedding_model'] = $payload['embedding_model']
            ?? data_get($metadata, 'ai.embedding_model')
            ?? $this->defaultEmbeddingModel();
        $metadata['ai']['max_tokens'] = (int) ($payload['max_tokens'] ?? self::DEFAULT_MAX_TOKENS);
        $metadata['ai']['temperature'] = (float) ($payload['temperature'] ?? self::DEFAULT_TEMPERATURE);
        $metadata['ai']['allow_audio_response'] = (bool) ($payload['allow_audio_response'] ?? false);
        $metadata['ai']['voice'] = $payload['voice'] ?? null;

        $resolvedRuntime = $keyResolver->resolveForOrganization($metadata, $requestedKeySource, $organizationId);
        if ((bool) $payload['active'] && empty($resolvedRuntime['key'])) {
            return back()->with('status', [
                'type' => 'error',
                'message' => __('AI API key is missing for the selected key policy.'),
            ]);
        }

        if ((bool) $payload['active'] && !app()->environment('testing')) {
            try {
                $this->runEmbeddingPreflight(
                    (string) $resolvedRuntime['key'],
                    (string) $metadata['ai']['embedding_model']
                );
            } catch (\Throwable $e) {
                Log::warning('intellireply.embedding.preflight_failed', [
                    'organization_id' => $organizationId,
                    'error_code' => app(AiEmbeddingService::class)->isModelAccessError($e)
                        ? 'EMBEDDING_MODEL_UNAVAILABLE'
                        : 'EMBEDDING_PREFLIGHT_FAILED',
                    'embedding_model' => $metadata['ai']['embedding_model'],
                    'key_source' => $resolvedRuntime['source'] ?? null,
                    'error' => $e->getMessage(),
                ]);

                return back()->with('status', [
                    'type' => 'error',
                    'message' => app(AiEmbeddingService::class)->isModelAccessError($e)
                        ? __('Selected embedding model is not available for this OpenAI key. Choose another embedding model or use a key with access.')
                        : __('Unable to validate embedding model access right now. Please try again.'),
                ]);
            }
        }

        $organization->metadata = json_encode($metadata);

        if ($organization->save()) {
            Log::info('intellireply.openai_setup.updated', [
                'organization_id' => $organizationId,
                'model' => $metadata['ai']['model'] ?? null,
                'embedding_model' => $metadata['ai']['embedding_model'] ?? null,
                'allow_audio_response' => (bool) ($metadata['ai']['allow_audio_response'] ?? false),
                'key_source' => $requestedKeySource,
                'effective_key_source' => $resolvedRuntime['source'] ?? null,
                'user_id' => auth()->id(),
            ]);

            return back()->with(
                'status', [
                    'type' => 'success',
                    'message' => __('Open AI settings updated successfully'),
                ]
            );
        }

        return back()->with(
            'status', [
                'type' => 'error',
                'message' => __('Something went wrong. Refresh the page and try again'),
            ]
        );
    }

    private function ensureAiAssistantEnabled(int $organizationId): void
    {
        if (!CustomHelper::isModuleEnabled('AI Assistant', $organizationId)) {
            abort(404);
        }
    }

    private function availableEmbeddingModels(): array
    {
        $models = config('intellireply.embedding_models', [self::DEFAULT_EMBEDDING_MODEL]);
        if (!is_array($models)) {
            return [self::DEFAULT_EMBEDDING_MODEL];
        }

        $normalized = collect($models)
            ->map(fn ($model) => is_string($model) ? trim($model) : '')
            ->filter(fn ($model) => $model !== '')
            ->unique()
            ->values()
            ->all();

        return empty($normalized) ? [self::DEFAULT_EMBEDDING_MODEL] : $normalized;
    }

    private function defaultEmbeddingModel(): string
    {
        $default = config('intellireply.default_embedding_model', self::DEFAULT_EMBEDDING_MODEL);
        return is_string($default) && trim($default) !== '' ? $default : self::DEFAULT_EMBEDDING_MODEL;
    }

    private function availableAudioResponseModels(): array
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

    private function runEmbeddingPreflight(string $apiKey, string $embeddingModel): void
    {
        app(AiProviderManager::class)->embeddings(null, $apiKey)->embed('intellireply preflight', $embeddingModel);
    }

    private function policyAllowsOrganizationKey(array $policyConfig): bool
    {
        return $policyConfig['policy'] === AiKeyResolver::POLICY_ORGANIZATION_ONLY
            || ($policyConfig['policy'] === AiKeyResolver::POLICY_HYBRID
                && ($policyConfig['allow_org_override'] ?? false) === true);
    }

    private function normalizeRequestedKeySource(array $policyConfig, string $requestedKeySource, bool $canUseOrganizationKey): string
    {
        if ($policyConfig['policy'] === AiKeyResolver::POLICY_GLOBAL_ONLY) {
            return 'global';
        }

        if ($policyConfig['policy'] === AiKeyResolver::POLICY_ORGANIZATION_ONLY) {
            return 'organization';
        }

        if (($policyConfig['allow_org_override'] ?? false) === false && $requestedKeySource === 'organization') {
            return 'global';
        }

        if (!$canUseOrganizationKey && $requestedKeySource === 'organization') {
            return ($policyConfig['has_global_key'] ?? false) ? 'global' : 'auto';
        }

        return $requestedKeySource;
    }
}
