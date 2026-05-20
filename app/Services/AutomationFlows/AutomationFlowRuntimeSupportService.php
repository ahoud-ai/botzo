<?php

namespace App\Services\AutomationFlows;

use App\Models\AutomationFlow;
use App\Models\AutomationFlowRun;
use App\Models\AutomationFlowRunStep;
use App\Models\Chat;
use App\Models\ChatTicket;
use App\Models\Contact;
use App\Models\Organization;
use App\Services\Whatsapp\WhatsappAccessTokenRefreshService;
use App\Services\WhatsappService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;

class AutomationFlowRuntimeSupportService
{
    private const CUSTOMER_CARE_WINDOW_STEP_TYPES = [
        'send_text',
        'send_media',
        'send_buttons',
        'send_list',
    ];
    private const CUSTOMER_CARE_WINDOW_ON_CLOSED_ACTIONS = [
        'fail_run',
        'release_to_fallback',
    ];

    public function __construct(
        private readonly AutomationFlowAssetService $assets,
        private readonly AutomationFlowContactMutationService $mutations,
        private readonly AutomationFlowPersonalizationService $personalization,
        private readonly AutomationFlowSessionVariableService $sessionVariables,
        private readonly AutomationFlowWhatsappComplianceService $whatsappCompliance,
        private readonly WhatsappAccessTokenRefreshService $tokenRefresh,
    ) {
    }

    public function buildInboundContext(Chat $chat): array
    {
        $payload = is_array($chat->metadata) ? $chat->metadata : (json_decode((string) $chat->metadata, true) ?: []);
        $interactiveType = (string) Arr::get($payload, 'interactive.type', '');

        return [
            'last_user_message' => trim((string) (
                Arr::get($payload, 'text.body')
                ?? Arr::get($payload, 'interactive.button_reply.title')
                ?? Arr::get($payload, 'interactive.list_reply.title')
                ?? Arr::get($payload, 'button.payload')
                ?? ''
            )),
            'selected_button_id' => $interactiveType === 'button_reply' ? (string) Arr::get($payload, 'interactive.button_reply.id', '') : '',
            'selected_list_row_id' => $interactiveType === 'list_reply' ? (string) Arr::get($payload, 'interactive.list_reply.id', '') : '',
            'selected_list_row_title' => $interactiveType === 'list_reply' ? (string) Arr::get($payload, 'interactive.list_reply.title', '') : '',
            'input_type' => (string) ($payload['type'] ?? ''),
        ];
    }

    public function mergeContext(array $state, array $incomingContext): array
    {
        $state['context'] = array_merge(Arr::get($state, 'context', []), $incomingContext);

        return $state;
    }

    public function sessionVariableValue(array $state, ?string $key, mixed $default = null): mixed
    {
        return $this->sessionVariables->get($state, $key, $default);
    }

    public function rememberSessionVariable(array $state, ?string $key, mixed $value): array
    {
        return $this->sessionVariables->put($state, $key, $value);
    }

    public function markWaitingForFreeText(AutomationFlowRun $run, string $nodeId, array $context): void
    {
        $this->recordStep($run, $nodeId, 'waiting', $context, null, ['mode' => 'free_text']);

        $run->update([
            'status' => 'waiting_input',
            'waiting_node_id' => $nodeId,
            'waiting_for' => 'free_text',
            'last_activity_at' => now(),
        ]);
    }

    public function persistNodeValue(
        AutomationFlowRun $run,
        Contact $contact,
        string $saveTarget,
        string $fieldUuid,
        string $variableKey,
        mixed $value
    ): void {
        if ($saveTarget === 'session_variable') {
            $nextState = $this->rememberSessionVariable($run->state_json ?? [], $variableKey, $value);
            $run->state_json = $nextState;
            $run->update([
                'state_json' => $nextState,
                'last_activity_at' => now(),
            ]);

            return;
        }

        $this->mutations->updateField($contact, $fieldUuid, $value);
    }

    public function resolveUpdateNodeValue(AutomationFlowRun $run, array $node, string $mode): mixed
    {
        return match ($mode) {
            'save_last_user_message', 'last_input' => Arr::get($run->last_input_json ?? [], 'last_user_message'),
            'session_variable' => $this->sessionVariableValue($run->state_json ?? [], (string) Arr::get($node, 'config.source_variable_key', '')),
            default => Arr::get($node, 'config.value'),
        };
    }

    public function buttonsConfig(array $config): array
    {
        return collect(Arr::get($config, 'buttons', []))
            ->map(fn ($button) => [
                'id' => (string) Arr::get($button, 'id'),
                'title' => (string) Arr::get($button, 'title'),
            ])
            ->filter(fn ($button) => filled($button['id']) && filled($button['title']))
            ->values()
            ->all();
    }

    public function listSectionsConfig(array $config): array
    {
        return collect(Arr::get($config, 'sections', []))
            ->map(function ($section) {
                return [
                    'title' => (string) Arr::get($section, 'title'),
                    'rows' => collect(Arr::get($section, 'rows', []))
                        ->map(fn ($row) => [
                            'id' => (string) Arr::get($row, 'id'),
                            'title' => (string) Arr::get($row, 'title'),
                            'description' => (string) Arr::get($row, 'description'),
                        ])
                        ->filter(fn ($row) => filled($row['id']) && filled($row['title']))
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn ($section) => filled($section['title']) && !empty($section['rows']))
            ->values()
            ->all();
    }

    public function interactiveInvalidReplyBehavior(array $config): string
    {
        $default = (string) config('automation_flows.runtime.invalid_reply_default_behavior', 'release_to_fallback');
        $allowed = collect(config('automation_flows.runtime.invalid_reply_behaviors', []))
            ->map(fn ($value) => (string) $value)
            ->filter()
            ->values()
            ->all();

        $selected = (string) Arr::get($config, 'invalid_reply_behavior', $default);

        return in_array($selected, $allowed, true) ? $selected : $default;
    }

    public function sendButtonsPrompt(Contact $contact, array $config, array $buttons): mixed
    {
        return $this->whatsapp((int) $contact->organization_id)->sendMessage(
            $contact->uuid,
            $this->personalization->replacePlaceholders($contact, (string) Arr::get($config, 'body', '')),
            null,
            'interactive buttons',
            $buttons,
            $this->whatsappCompliance->interactiveTextHeader(
                $this->personalization->replacePlaceholders($contact, (string) Arr::get($config, 'header', ''))
            ),
            Arr::get($config, 'footer')
        );
    }

    public function sendListPrompt(Contact $contact, array $config, array $sections): mixed
    {
        return $this->whatsapp((int) $contact->organization_id)->sendMessage(
            $contact->uuid,
            $this->personalization->replacePlaceholders($contact, (string) Arr::get($config, 'body', '')),
            null,
            'interactive list',
            $sections,
            $this->whatsappCompliance->interactiveTextHeader(
                $this->personalization->replacePlaceholders($contact, (string) Arr::get($config, 'header', ''))
            ),
            Arr::get($config, 'footer'),
            (string) Arr::get($config, 'button_label')
        );
    }

    public function resolveMediaUrl(?AutomationFlow $flow, string $assetId): ?string
    {
        if (!$flow || $assetId === '') {
            return null;
        }

        $asset = $flow->assets->firstWhere('uuid', $assetId);

        return $asset ? $this->assets->publicUrl($flow, $asset) : null;
    }

    public function resolveMediaName(?AutomationFlow $flow, string $assetId, string $fallbackUrl): string
    {
        if ($flow && $assetId !== '') {
            $asset = $flow->assets->firstWhere('uuid', $assetId);
            if ($asset?->original_name) {
                return $asset->original_name;
            }
        }

        return basename(parse_url($fallbackUrl, PHP_URL_PATH) ?: 'media');
    }

    public function conversationWindowBlockPayload(Contact $contact, string $nodeType): ?array
    {
        if (!$this->requiresCustomerCareWindow($nodeType)) {
            return null;
        }

        $policy = $this->whatsappConversationPolicy();
        if (!($policy['enforce_customer_care_window'] ?? true)) {
            return null;
        }

        $lastInboundAt = $this->lastInboundAt((int) $contact->organization_id, (int) $contact->id);
        $windowHours = max(1, (int) ($policy['customer_care_window_hours'] ?? 24));
        $windowOpen = $lastInboundAt !== null && $lastInboundAt->greaterThanOrEqualTo(now()->subHours($windowHours));

        if ($windowOpen) {
            return null;
        }

        $onWindowClosed = $this->normalizeWindowClosedAction((string) ($policy['on_window_closed'] ?? 'fail_run'));

        return [
            'reason' => 'customer_care_window_closed',
            'on_window_closed' => $onWindowClosed,
            'window_hours' => $windowHours,
            'last_inbound_at' => $lastInboundAt?->toIso8601String(),
            'message' => __('This step sends a free-form WhatsApp message and requires an open customer care window (:hours hours). Start with a template message first.', [
                'hours' => $windowHours,
            ]),
        ];
    }

    public function requiresCustomerCareWindow(string $nodeType): bool
    {
        return in_array($nodeType, self::CUSTOMER_CARE_WINDOW_STEP_TYPES, true);
    }

    public function whatsappConversationPolicy(): array
    {
        return [
            'customer_care_window_hours' => max(1, (int) config('automation_flows.whatsapp.customer_care_window_hours', 24)),
            'enforce_customer_care_window' => (bool) config('automation_flows.whatsapp.enforce_customer_care_window', true),
            'on_window_closed' => $this->normalizeWindowClosedAction((string) config('automation_flows.whatsapp.on_window_closed', 'fail_run')),
        ];
    }

    private function normalizeWindowClosedAction(string $action): string
    {
        return in_array($action, self::CUSTOMER_CARE_WINDOW_ON_CLOSED_ACTIONS, true)
            ? $action
            : 'fail_run';
    }

    public function whatsappForOrganization(int $organizationId): WhatsappService
    {
        return $this->whatsapp($organizationId);
    }

    public function evaluateCondition(Contact $contact, array $config, array $state): string
    {
        $source = (string) Arr::get($config, 'source', 'last_user_message');
        $operator = (string) Arr::get($config, 'operator', 'equals');
        $expected = (string) Arr::get($config, 'value', '');
        $context = Arr::get($state, 'context', []);

        $actual = match ($source) {
            'selected_button_id' => (string) Arr::get($context, 'selected_button_id', ''),
            'selected_list_row_id' => (string) Arr::get($context, 'selected_list_row_id', ''),
            'contact_field' => (string) $this->mutations->fieldValue($contact, (string) Arr::get($config, 'field_uuid', '')),
            'flow_variable' => (string) $this->sessionVariableValue($state, (string) Arr::get($config, 'variable_key', '')),
            default => (string) Arr::get($context, 'last_user_message', ''),
        };

        $matched = match ($operator) {
            'equals' => mb_strtolower($actual) === mb_strtolower($expected),
            'not_equals' => mb_strtolower($actual) !== mb_strtolower($expected),
            'contains' => $expected !== '' && str_contains(mb_strtolower($actual), mb_strtolower($expected)),
            'filled' => filled($actual),
            'not_filled' => blank($actual),
            default => false,
        };

        return $matched ? 'matched' : 'unmatched';
    }

    public function resolveNextNodeId(array $edges, string $preferredBranch): ?string
    {
        $collection = collect($edges);

        return $collection->firstWhere('branch', $preferredBranch)['target_id']
            ?? $collection->firstWhere('branch', 'default')['target_id']
            ?? $collection->first()['target_id']
            ?? null;
    }

    public function recordStep(
        AutomationFlowRun $run,
        ?string $nodeId,
        string $status,
        ?array $input,
        ?array $output = null,
        ?array $metadata = null
    ): void {
        if (!$nodeId) {
            return;
        }

        $node = Arr::get($run->version?->compiled_json ?? [], 'nodes.' . $nodeId, []);

        AutomationFlowRunStep::create([
            'automation_flow_run_id' => $run->id,
            'automation_flow_id' => $run->automation_flow_id,
            'organization_id' => $run->organization_id,
            'node_id' => $nodeId,
            'node_type' => (string) Arr::get($node, 'type', 'unknown'),
            'status' => $status,
            'input_json' => $input,
            'output_json' => $output,
            'metadata_json' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    public function expireStaleRunIfNeeded(AutomationFlowRun $run): bool
    {
        $thresholdMinutes = match ($run->status) {
            'waiting_input' => (int) config('automation_flows.runtime.waiting_input_stale_minutes', 1440),
            'waiting_handoff' => (int) config('automation_flows.runtime.waiting_handoff_stale_minutes', 10080),
            'active' => (int) config('automation_flows.runtime.active_run_stale_minutes', 30),
            default => 0,
        };

        if ($thresholdMinutes < 1) {
            return false;
        }

        $lastActivityAt = $run->last_activity_at ?? $run->updated_at ?? $run->started_at;
        if (!$lastActivityAt || $lastActivityAt->greaterThan(now()->subMinutes($thresholdMinutes))) {
            return false;
        }

        $nodeId = $run->waiting_node_id ?: $run->current_node_id;
        $context = Arr::get($run->state_json ?? [], 'context', []);

        $this->recordStep($run, $nodeId, 'cancelled', $context, null, [
            'reason' => 'stale_run_expired',
            'stale_status' => $run->status,
            'stale_threshold_minutes' => $thresholdMinutes,
        ]);

        $run->update([
            'status' => 'cancelled',
            'waiting_node_id' => null,
            'waiting_for' => null,
            'completed_at' => now(),
            'last_activity_at' => now(),
        ]);

        return true;
    }

    public function failRunAtNode(
        AutomationFlowRun $run,
        string $nodeId,
        array $context,
        array $result,
        array $metadata = []
    ): void {
        $this->recordStep($run, $nodeId, 'failed', $context, $result, $metadata);
        $run->update([
            'status' => 'failed',
            'current_node_id' => $nodeId,
            'waiting_node_id' => null,
            'waiting_for' => null,
            'completed_at' => now(),
            'last_activity_at' => now(),
        ]);
    }

    public function resumeHandoffRun(AutomationFlowRun $run, Chat $chat): bool
    {
        $handoff = Arr::get($run->state_json ?? [], 'handoff', []);
        $context = $this->buildInboundContext($chat);
        $waitingNodeId = $run->waiting_node_id ?: $run->current_node_id;

        $run->update([
            'chat_id' => $chat->id,
            'last_input_json' => $context,
            'state_json' => $this->mergeContext($run->state_json ?? [], $context),
            'last_activity_at' => now(),
        ]);

        if (($handoff['target'] ?? null) === 'human') {
            $ticket = ChatTicket::query()->where('contact_id', $run->contact_id)->first();

            if ($ticket && $ticket->status === 'open') {
                $this->recordStep($run, $waitingNodeId, 'waiting', $context, [
                    'ticket_id' => $ticket->id,
                    'assigned_user_id' => $ticket->assigned_to,
                ], [
                    'source' => 'inbound_message',
                    'reason' => 'human_handoff_active',
                    'handoff_target' => 'human',
                ]);

                return true;
            }

            $this->recordStep($run, $waitingNodeId, 'cancelled', $context, null, [
                'source' => 'inbound_message',
                'reason' => 'human_handoff_released',
                'handoff_target' => 'human',
            ]);
            $run->update([
                'status' => 'cancelled',
                'waiting_node_id' => null,
                'waiting_for' => null,
                'completed_at' => now(),
                'last_activity_at' => now(),
            ]);

            return false;
        }

        if (($handoff['target'] ?? null) === 'ai_assistant') {
            $contact = Contact::find($run->contact_id);

            if (!$contact || !$contact->ai_assistance_enabled) {
                $this->recordStep($run, $waitingNodeId, 'cancelled', $context, null, [
                    'source' => 'inbound_message',
                    'reason' => 'ai_handoff_released',
                    'handoff_target' => 'ai_assistant',
                ]);
                $run->update([
                    'status' => 'cancelled',
                    'waiting_node_id' => null,
                    'waiting_for' => null,
                    'completed_at' => now(),
                    'last_activity_at' => now(),
                ]);

                return false;
            }

            $handled = false;

            if (class_exists(\Modules\IntelliReply\Services\AIResponseService::class)) {
                try {
                    $handled = (bool) app(\Modules\IntelliReply\Services\AIResponseService::class)
                        ->handleAIResponse($chat, ' ' . (string) Arr::get($context, 'last_user_message', ''));
                } catch (\Throwable $exception) {
                    report($exception);
                    $handled = false;
                }
            }

            $this->recordStep($run, $waitingNodeId, $handled ? 'executed' : 'waiting', $context, [
                'handled_by_ai' => $handled,
            ], [
                'source' => 'inbound_message',
                'reason' => 'ai_handoff_active',
                'handoff_target' => 'ai_assistant',
            ]);

            return $handled;
        }

        $this->recordStep($run, $waitingNodeId, 'cancelled', $context, null, [
            'source' => 'inbound_message',
            'reason' => 'unknown_handoff_target',
        ]);
        $run->update([
            'status' => 'cancelled',
            'waiting_node_id' => null,
            'waiting_for' => null,
            'completed_at' => now(),
            'last_activity_at' => now(),
        ]);

        return false;
    }

    private function whatsapp(int $organizationId): WhatsappService
    {
        $metadata = Organization::find($organizationId)?->metadata;
        $metadata = $metadata ? json_decode($metadata, true) : [];
        $accessToken = $this->tokenRefresh->resolveTokenForOrganization($organizationId, true);

        return new WhatsappService(
            $accessToken,
            config('graph.api_version'),
            $metadata['whatsapp']['app_id'] ?? null,
            $metadata['whatsapp']['phone_number_id'] ?? null,
            $metadata['whatsapp']['waba_id'] ?? null,
            $organizationId,
        );
    }

    private function lastInboundAt(int $organizationId, int $contactId): ?Carbon
    {
        $value = Chat::query()
            ->where('organization_id', $organizationId)
            ->where('contact_id', $contactId)
            ->where('type', 'inbound')
            ->latest('created_at')
            ->value('created_at');

        if ($value === null) {
            return null;
        }

        return $value instanceof Carbon ? $value : Carbon::parse($value);
    }
}
