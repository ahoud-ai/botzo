<?php
namespace App\Services\AutomationFlows;

use App\Jobs\ResumeAutomationFlowRunJob;
use App\Models\AutomationFlow;
use App\Models\AutomationFlowRun;
use App\Models\Chat;
use App\Models\Contact;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
class AutomationFlowRuntimeService
{
    public function __construct(
        private readonly AutomationFlowContactMutationService $mutations,
        private readonly AutomationFlowAccessService $access,
        private readonly AutomationFlowActionDispatchService $actionDispatch,
        private readonly AutomationFlowBuilderPolicyService $builderPolicy,
        private readonly AutomationFlowNodeCatalog $catalog,
        private readonly AutomationFlowPersonalizationService $personalization,
        private readonly AutomationFlowRunQuotaService $runQuotaService,
        private readonly AutomationFlowRuntimeSupportService $runtimeSupport,
        private readonly AutomationFlowConversationHandoffService $conversationHandoff,
    ) {
    }
    public function handleInbound(Chat $chat): bool
    {
        if (!$this->access->availableForOrganization((int) $chat->organization_id)) {
            return false;
        }
        return (bool) $this->withContactLock(
            (int) $chat->organization_id,
            (int) $chat->contact_id,
            fn (): bool => $this->handleInboundUnlocked($chat),
            false
        );
    }
    private function handleInboundUnlocked(Chat $chat): bool
    {
        $inboundContext = $this->runtimeSupport->buildInboundContext($chat);
        $isFirstInboundMessage = null;
        $run = AutomationFlowRun::with(['flow.currentVersion', 'version'])
            ->where('organization_id', $chat->organization_id)
            ->where('contact_id', $chat->contact_id)
            ->whereIn('status', ['waiting_input', 'waiting_handoff', 'active'])
            ->latest('id')
            ->first();
        if ($run && $this->runtimeSupport->expireStaleRunIfNeeded($run)) {
            $run = null;
        }
        if ($run) {
            $resumeResult = $this->resumeWaitingRun($run, $chat);
            if ($resumeResult !== null) {
                return $resumeResult;
            }
        }
        $flows = AutomationFlow::with('currentVersion')
            ->where('organization_id', $chat->organization_id)
            ->where('channel', 'whatsapp')
            ->where('status', 'published')
            ->whereNotNull('current_version_id')
            ->orderByDesc('updated_at')
            ->get();
        foreach ($flows as $flow) {
            if ($this->triggerMatches($flow, $chat, $inboundContext, $isFirstInboundMessage)) {
                return $this->startRun($flow, $chat);
            }
        }
        return false;
    }
    public function resumeDelayedRun(AutomationFlowRun $run): void
    {
        $this->withContactLock(
            (int) $run->organization_id,
            (int) $run->contact_id,
            function () use ($run): void {
                $freshRun = AutomationFlowRun::with(['flow.currentVersion', 'version'])->find($run->id);
                if (!$freshRun || $freshRun->status !== 'waiting_delay') {
                    return;
                }
                if (!$this->access->availableForOrganization((int) $freshRun->organization_id)) {
                    $freshRun->update([
                        'status' => 'cancelled',
                        'completed_at' => now(),
                    ]);
                    return;
                }
                if (!$freshRun->flow || $freshRun->flow->status !== 'published') {
                    $freshRun->update([
                        'status' => 'cancelled',
                        'completed_at' => now(),
                    ]);
                    return;
                }
                $freshRun->update([
                    'status' => 'active',
                    'waiting_node_id' => null,
                    'waiting_for' => null,
                    'next_resume_at' => null,
                    'last_activity_at' => now(),
                ]);
                $this->continueRun($freshRun->fresh(['flow.currentVersion', 'version']), null);
            }
        );
    }
    private function startRun(AutomationFlow $flow, Chat $chat): bool
    {
        $existingRun = AutomationFlowRun::query()
            ->where('organization_id', $flow->organization_id)
            ->where('contact_id', $chat->contact_id)
            ->whereIn('status', ['active', 'waiting_input', 'waiting_handoff', 'waiting_delay'])
            ->latest('id')
            ->first();
        if ($existingRun) {
            return false;
        }
        if (!$this->runQuotaService->canStartRunForOrganization((int) $flow->organization_id)) {
            return false;
        }
        $version = $flow->currentVersion;
        if (!$version) {
            return false;
        }
        $context = $this->runtimeSupport->buildInboundContext($chat);
        $run = AutomationFlowRun::create([
            'uuid' => (string) Str::uuid(),
            'automation_flow_id' => $flow->id,
            'automation_flow_version_id' => $version->id,
            'organization_id' => $flow->organization_id,
            'contact_id' => $chat->contact_id,
            'chat_id' => $chat->id,
            'status' => 'active',
            'current_node_id' => $version->compiled_json['start_node_id'] ?? null,
            'state_json' => [
                'context' => $context,
            ],
            'last_input_json' => $context,
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        AutomationFlow::whereKey($flow->id)->increment('runs_count');
        $this->continueRun($run->fresh(['flow.currentVersion', 'version']), null);
        $finalRun = $run->fresh();

        if ((bool) Arr::get($finalRun?->state_json ?? [], 'runtime.release_to_fallback', false)) {
            return false;
        }

        return true;
    }
    private function resumeWaitingRun(AutomationFlowRun $run, Chat $chat): ?bool
    {
        if (!$run->flow || $run->flow->status !== 'published') {
            return null;
        }
        if ($run->status === 'waiting_handoff') {
            return $this->runtimeSupport->resumeHandoffRun($run, $chat);
        }
        $waitingFor = (string) ($run->waiting_for ?? '');
        $waitingNodeId = $run->waiting_node_id ?: $run->current_node_id;
        $waitingNode = Arr::get($run->version?->compiled_json ?? [], 'nodes.' . $waitingNodeId, []);
        $waitingConfig = Arr::get($waitingNode, 'config', []);
        $context = $this->runtimeSupport->buildInboundContext($chat);
        $expectsInteractiveReply = in_array($waitingFor, ['button', 'list'], true);
        $hasInteractiveReply = ($waitingFor === 'button' && filled(Arr::get($context, 'selected_button_id')))
            || ($waitingFor === 'list' && filled(Arr::get($context, 'selected_list_row_id')));
        if ($expectsInteractiveReply && !$hasInteractiveReply) {
            $behavior = $this->runtimeSupport->interactiveInvalidReplyBehavior($waitingConfig);
            $run->update([
                'chat_id' => $chat->id,
                'last_activity_at' => now(),
            ]);
            if ($behavior === 'repeat_prompt') {
                $contact = Contact::find($run->contact_id);
                if (!$contact) {
                    $this->runtimeSupport->failRunAtNode($run, $waitingNodeId, $context, [
                        'reason' => 'contact_not_found_during_repeat_prompt',
                    ], [
                        'type' => 'interactive_repeat_prompt',
                    ]);
                    return true;
                }
                if ($waitingFor === 'button') {
                    $buttons = $this->runtimeSupport->buttonsConfig($waitingConfig);
                    $response = $this->runtimeSupport->sendButtonsPrompt($contact, $waitingConfig, $buttons);
                    if (!$this->wasWhatsappSendSuccessful($response)) {
                        $this->runtimeSupport->failRunAtNode($run, $waitingNodeId, $context, array_merge(
                            ['reason' => 'whatsapp_send_failed'],
                            $this->whatsappFailurePayload($response)
                        ), [
                            'type' => 'interactive_repeat_prompt',
                        ]);
                        return true;
                    }
                    $this->runtimeSupport->recordStep($run, $waitingNodeId, 'waiting', $context, ['buttons' => $buttons], [
                        'source' => 'inbound_message',
                        'reason' => 'interactive_selection_required',
                        'waiting_for' => $waitingFor,
                        'invalid_reply_behavior' => $behavior,
                    ]);
                } elseif ($waitingFor === 'list') {
                    $sections = $this->runtimeSupport->listSectionsConfig($waitingConfig);
                    $response = $this->runtimeSupport->sendListPrompt($contact, $waitingConfig, $sections);
                    if (!$this->wasWhatsappSendSuccessful($response)) {
                        $this->runtimeSupport->failRunAtNode($run, $waitingNodeId, $context, array_merge(
                            ['reason' => 'whatsapp_send_failed'],
                            $this->whatsappFailurePayload($response)
                        ), [
                            'type' => 'interactive_repeat_prompt',
                        ]);
                        return true;
                    }
                    $this->runtimeSupport->recordStep($run, $waitingNodeId, 'waiting', $context, ['sections' => $sections], [
                        'source' => 'inbound_message',
                        'reason' => 'interactive_selection_required',
                        'waiting_for' => $waitingFor,
                        'invalid_reply_behavior' => $behavior,
                    ]);
                }
                return true;
            }
            $status = $behavior === 'end_run' ? 'cancelled' : 'ignored';
            $metadata = [
                'source' => 'inbound_message',
                'reason' => 'interactive_selection_required',
                'waiting_for' => $waitingFor,
                'invalid_reply_behavior' => $behavior,
                'released_to_fallback' => true,
            ];
            $this->runtimeSupport->recordStep($run, $waitingNodeId, $status, $context, null, $metadata);
            if ($behavior === 'end_run') {
                $run->update([
                    'status' => 'cancelled',
                    'waiting_node_id' => null,
                    'waiting_for' => null,
                    'completed_at' => now(),
                    'last_activity_at' => now(),
                ]);
                return false;
            }
            return false;
        }
        $run->update([
            'status' => 'active',
            'chat_id' => $chat->id,
            'last_input_json' => $context,
            'state_json' => $this->runtimeSupport->mergeContext($run->state_json ?? [], $context),
            'last_activity_at' => now(),
        ]);
        $this->runtimeSupport->recordStep($run, $run->waiting_node_id ?: $run->current_node_id, 'resumed', $context, null, [
            'source' => 'inbound_message',
        ]);
        $this->continueRun($run->fresh(['flow.currentVersion', 'version']), $chat);
        return true;
    }
    private function continueRun(AutomationFlowRun $run, ?Chat $chat): void
    {
        $run->loadMissing(['flow.assets', 'flow.nodeSecrets', 'version']);
        $compiled = $run->version?->compiled_json ?? [];
        $nodeMap = Arr::get($compiled, 'nodes', []);
        $adjacency = Arr::get($compiled, 'adjacency', []);
        $currentNodeId = $run->current_node_id;
        $steps = 0;
        $maxSteps = (int) config('automation_flows.max_execution_steps', 60);
        $contact = Contact::find($run->contact_id);
        if (!$contact) {
            $run->update([
                'status' => 'failed',
                'completed_at' => now(),
            ]);
            return;
        }
        while ($currentNodeId && isset($nodeMap[$currentNodeId]) && $steps < $maxSteps) {
            $steps++;
            $node = $nodeMap[$currentNodeId];
            $context = Arr::get($run->state_json ?? [], 'context', []);
            $nextNodeId = null;
            $outgoing = $adjacency[$currentNodeId] ?? [];
            $nodeType = (string) Arr::get($node, 'type', '');
            if (!$this->builderPolicy->allowsNodeType($nodeType)) {
                $definition = $this->catalog->definition($nodeType);
                $label = __((string) ($definition['label'] ?? $nodeType));
                $this->failRunAtNode($run, $currentNodeId, $context, [
                    'reason' => 'builder_policy_blocked_step',
                    'blocked_step_type' => $nodeType,
                ], [
                    'type' => $nodeType,
                    'policy_blocked' => true,
                    'step_label' => $label,
                    'message' => __('This flow run stopped because the current Flow Builder policy blocks :step.', [
                        'step' => $label,
                    ]),
                ]);
                return;
            }
            if (($node['type'] ?? null) !== 'trigger' && Arr::get($node, 'config.active', true) === false) {
                $this->recordStep($run, $currentNodeId, 'skipped', $context, null, [
                    'reason' => 'inactive_step',
                ]);
                $nextNodeId = $this->resolveNextNodeId($outgoing, 'default');
                if (!$nextNodeId) {
                    $run->update([
                        'status' => 'completed',
                        'current_node_id' => $currentNodeId,
                        'waiting_node_id' => null,
                        'waiting_for' => null,
                        'completed_at' => now(),
                        'last_activity_at' => now(),
                    ]);
                    return;
                }
                $currentNodeId = $nextNodeId;
                $run->update([
                    'status' => 'active',
                    'current_node_id' => $currentNodeId,
                    'waiting_node_id' => null,
                    'waiting_for' => null,
                    'last_activity_at' => now(),
                ]);
                continue;
            }
            $windowBlockPayload = $this->runtimeSupport->conversationWindowBlockPayload($contact, $nodeType);
            if ($windowBlockPayload !== null) {
                $definition = $this->catalog->definition($nodeType);
                $label = __((string) ($definition['label'] ?? $nodeType));
                $windowClosedAction = (string) Arr::get($windowBlockPayload, 'on_window_closed', 'fail_run');

                if ($windowClosedAction === 'release_to_fallback') {
                    $state = $run->state_json ?? [];
                    $state['runtime'] = array_merge((array) Arr::get($state, 'runtime', []), [
                        'release_to_fallback' => true,
                        'release_reason' => 'customer_care_window_closed',
                    ]);

                    $this->recordStep($run, $currentNodeId, 'cancelled', $context, $windowBlockPayload, [
                        'type' => $nodeType,
                        'step_label' => $label,
                        'conversation_window_closed' => true,
                        'released_to_fallback' => true,
                        'window_closed_action' => $windowClosedAction,
                        'message' => (string) ($windowBlockPayload['message'] ?? __('This flow run stopped because the WhatsApp conversation window is closed.')),
                    ]);

                    $run->update([
                        'status' => 'cancelled',
                        'current_node_id' => $currentNodeId,
                        'waiting_node_id' => null,
                        'waiting_for' => null,
                        'state_json' => $state,
                        'completed_at' => now(),
                        'last_activity_at' => now(),
                    ]);

                    return;
                }

                $this->failRunAtNode($run, $currentNodeId, $context, array_merge($windowBlockPayload, [
                    'blocked_step_type' => $nodeType,
                ]), [
                    'type' => $nodeType,
                    'step_label' => $label,
                    'conversation_window_closed' => true,
                    'window_closed_action' => $windowClosedAction,
                    'message' => (string) ($windowBlockPayload['message'] ?? __('This flow run stopped because the WhatsApp conversation window is closed.')),
                ]);
                return;
            }
            switch ($node['type']) {
                case 'trigger':
                    $this->recordStep($run, $currentNodeId, 'executed', $context, null, ['type' => 'trigger']);
                    $nextNodeId = $this->resolveNextNodeId($outgoing, 'default');
                    break;
                case 'send_text':
                    $message = $this->personalization->replacePlaceholders($contact, (string) Arr::get($node, 'config.text', ''));
                    $response = $this->whatsapp($contact->organization_id)->sendMessage($contact->uuid, $message);
                    if (!$this->wasWhatsappSendSuccessful($response)) {
                        $this->failRunAtNode($run, $currentNodeId, $context, array_merge(
                            ['reason' => 'whatsapp_send_failed'],
                            $this->whatsappFailurePayload($response)
                        ), [
                            'type' => 'send_text',
                        ]);
                        return;
                    }
                    $this->recordStep($run, $currentNodeId, 'executed', $context, ['message' => $message]);
                    $nextNodeId = $this->resolveNextNodeId($outgoing, 'default');
                    break;
                case 'send_media':
                    $config = Arr::get($node, 'config', []);
                    $caption = $this->personalization->replacePlaceholders($contact, (string) Arr::get($config, 'caption', ''));
                    $assetId = (string) Arr::get($config, 'asset_id', '');
                    $mediaUrl = $this->runtimeSupport->resolveMediaUrl($run->flow, $assetId);
                    $mediaType = (string) Arr::get($config, 'media_type', 'image');
                    if (!$mediaUrl) {
                        $run->update([
                            'status' => 'failed',
                            'completed_at' => now(),
                        ]);
                        return;
                    }
                    $response = $this->whatsapp($contact->organization_id)->sendMedia(
                        $contact->uuid,
                        $mediaType,
                        $this->runtimeSupport->resolveMediaName($run->flow, $assetId, $mediaUrl),
                        $mediaUrl,
                        $mediaUrl,
                        'remote',
                        $caption
                    );
                    if (!$this->wasWhatsappSendSuccessful($response)) {
                        $this->failRunAtNode($run, $currentNodeId, $context, array_merge(
                            ['reason' => 'whatsapp_send_failed'],
                            $this->whatsappFailurePayload($response)
                        ), [
                            'type' => 'send_media',
                        ]);
                        return;
                    }
                    $this->recordStep($run, $currentNodeId, 'executed', $context, ['asset_id' => $assetId, 'media_url' => $mediaUrl, 'caption' => $caption]);
                    $nextNodeId = $this->resolveNextNodeId($outgoing, 'default');
                    break;
                case 'send_buttons':
                    $config = Arr::get($node, 'config', []);
                    $buttons = $this->runtimeSupport->buttonsConfig($config);
                    if ($run->waiting_node_id === $currentNodeId && $run->waiting_for === 'button' && filled(Arr::get($context, 'selected_button_id'))) {
                        $selectedButtonId = (string) Arr::get($context, 'selected_button_id');
                        $this->recordStep($run, $currentNodeId, 'executed', $context, [
                            'selected_button_id' => $selectedButtonId,
                        ], [
                            'buttons' => $buttons,
                        ]);
                        $nextNodeId = $this->resolveNextNodeId($outgoing, $selectedButtonId);
                        break;
                    }
                    $response = $this->runtimeSupport->sendButtonsPrompt($contact, $config, $buttons);
                    if (!$this->wasWhatsappSendSuccessful($response)) {
                        $this->failRunAtNode($run, $currentNodeId, $context, array_merge(
                            ['reason' => 'whatsapp_send_failed'],
                            $this->whatsappFailurePayload($response)
                        ), [
                            'type' => 'send_buttons',
                        ]);
                        return;
                    }
                    $this->recordStep($run, $currentNodeId, 'waiting', $context, ['buttons' => $buttons]);
                    $run->update([
                        'status' => 'waiting_input',
                        'waiting_node_id' => $currentNodeId,
                        'waiting_for' => 'button',
                        'last_activity_at' => now(),
                    ]);
                    return;
                case 'send_list':
                    $config = Arr::get($node, 'config', []);
                    $sections = $this->runtimeSupport->listSectionsConfig($config);
                    if ($run->waiting_node_id === $currentNodeId && $run->waiting_for === 'list' && filled(Arr::get($context, 'selected_list_row_id'))) {
                        $selectedRowId = (string) Arr::get($context, 'selected_list_row_id');
                        $this->recordStep($run, $currentNodeId, 'executed', $context, [
                            'selected_list_row_id' => $selectedRowId,
                            'selected_list_row_title' => (string) Arr::get($context, 'selected_list_row_title', ''),
                        ], [
                            'sections' => $sections,
                        ]);
                        $nextNodeId = $this->resolveNextNodeId($outgoing, $selectedRowId);
                        break;
                    }
                    $response = $this->runtimeSupport->sendListPrompt($contact, $config, $sections);
                    if (!$this->wasWhatsappSendSuccessful($response)) {
                        $this->failRunAtNode($run, $currentNodeId, $context, array_merge(
                            ['reason' => 'whatsapp_send_failed'],
                            $this->whatsappFailurePayload($response)
                        ), [
                            'type' => 'send_list',
                        ]);
                        return;
                    }
                    $this->recordStep($run, $currentNodeId, 'waiting', $context, ['sections' => $sections]);
                    $run->update([
                        'status' => 'waiting_input',
                        'waiting_node_id' => $currentNodeId,
                        'waiting_for' => 'list',
                        'last_activity_at' => now(),
                    ]);
                    return;
                case 'save_reply_to_field':
                    $saveTarget = (string) Arr::get($node, 'config.save_target', 'contact_field');
                    $fieldUuid = (string) Arr::get($node, 'config.field_uuid', '');
                    $variableKey = (string) Arr::get($node, 'config.variable_key', '');
                    $value = Arr::get($run->last_input_json ?? [], 'last_user_message');
                    if (blank($value) && !$chat) {
                        $this->runtimeSupport->markWaitingForFreeText($run, $currentNodeId, $context);
                        return;
                    }
                    $this->runtimeSupport->persistNodeValue($run, $contact, $saveTarget, $fieldUuid, $variableKey, $value);
                    $this->recordStep($run, $currentNodeId, 'executed', $context, [
                        'save_target' => $saveTarget,
                        'field_uuid' => $saveTarget === 'contact_field' ? $fieldUuid : null,
                        'variable_key' => $saveTarget === 'session_variable' ? $variableKey : null,
                        'value' => $value,
                    ]);
                    $nextNodeId = $this->resolveNextNodeId($outgoing, 'default');
                    break;
                case 'condition':
                    $branch = $this->evaluateCondition($contact, Arr::get($node, 'config', []), $run->state_json ?? []);
                    $this->recordStep($run, $currentNodeId, 'executed', $context, ['branch' => $branch]);
                    $nextNodeId = $this->resolveNextNodeId($outgoing, $branch);
                    break;
                case 'add_to_group':
                    $groupUuid = (string) Arr::get($node, 'config.group_uuid', '');
                    $this->mutations->addToGroup($contact, $groupUuid);
                    $this->recordStep($run, $currentNodeId, 'executed', $context, ['group_uuid' => $groupUuid]);
                    $nextNodeId = $this->resolveNextNodeId($outgoing, 'default');
                    break;
                case 'remove_from_group':
                    $groupUuid = (string) Arr::get($node, 'config.group_uuid', '');
                    $this->mutations->removeFromGroup($contact, $groupUuid);
                    $this->recordStep($run, $currentNodeId, 'executed', $context, ['group_uuid' => $groupUuid]);
                    $nextNodeId = $this->resolveNextNodeId($outgoing, 'default');
                    break;
                case 'update_contact_field':
                    $saveTarget = (string) Arr::get($node, 'config.save_target', 'contact_field');
                    $fieldUuid = (string) Arr::get($node, 'config.field_uuid', '');
                    $variableKey = (string) Arr::get($node, 'config.variable_key', '');
                    $mode = (string) Arr::get($node, 'config.mode', Arr::get($node, 'config.value_mode', 'save_last_user_message'));
                    $value = $this->runtimeSupport->resolveUpdateNodeValue($run, $node, $mode);
                    if (in_array($mode, ['save_last_user_message', 'last_input'], true) && blank($value) && !$chat) {
                        $this->runtimeSupport->markWaitingForFreeText($run, $currentNodeId, $context);
                        return;
                    }
                    $this->runtimeSupport->persistNodeValue($run, $contact, $saveTarget, $fieldUuid, $variableKey, $value);
                    $this->recordStep($run, $currentNodeId, 'executed', $context, [
                        'save_target' => $saveTarget,
                        'field_uuid' => $saveTarget === 'contact_field' ? $fieldUuid : null,
                        'variable_key' => $saveTarget === 'session_variable' ? $variableKey : null,
                        'source_variable_key' => $mode === 'session_variable' ? (string) Arr::get($node, 'config.source_variable_key', '') : null,
                        'value' => $value,
                    ]);
                    $nextNodeId = $this->resolveNextNodeId($outgoing, 'default');
                    break;
                case 'assign_to_agent':
                    $assignmentResult = $this->conversationHandoff->assignToAgent(
                        $contact,
                        (string) Arr::get($node, 'config.assignment_mode', 'auto_assign'),
                        ($agentUserId = (int) Arr::get($node, 'config.agent_user_id', 0)) > 0 ? $agentUserId : null,
                        (bool) Arr::get($node, 'config.reopen_closed_ticket', true),
                    );
                    if (!($assignmentResult['successful'] ?? false)) {
                        $this->failRunAtNode($run, $currentNodeId, $context, $assignmentResult, [
                            'type' => 'assign_to_agent',
                        ]);
                        return;
                    }
                    $this->recordStep($run, $currentNodeId, 'executed', $context, $assignmentResult, [
                        'type' => 'assign_to_agent',
                    ]);
                    $nextNodeId = $this->resolveNextNodeId($outgoing, 'default');
                    break;
                case 'human_handoff':
                    $handoffResult = $this->conversationHandoff->startHumanHandoff(
                        $contact,
                        (string) Arr::get($node, 'config.assignment_mode', 'auto_assign'),
                        ($agentUserId = (int) Arr::get($node, 'config.agent_user_id', 0)) > 0 ? $agentUserId : null,
                        (bool) Arr::get($node, 'config.reopen_closed_ticket', true),
                    );
                    if (!($handoffResult['successful'] ?? false)) {
                        $this->failRunAtNode($run, $currentNodeId, $context, $handoffResult, [
                            'type' => 'human_handoff',
                        ]);
                        return;
                    }
                    $this->recordStep($run, $currentNodeId, 'waiting', $context, $handoffResult, [
                        'type' => 'human_handoff',
                        'reason' => 'handoff_started',
                    ]);
                    $run->update([
                        'status' => 'waiting_handoff',
                        'waiting_node_id' => $currentNodeId,
                        'waiting_for' => 'human_handoff',
                        'state_json' => $this->runtimeSupport->mergeContext(array_merge($run->state_json ?? [], [
                            'handoff' => [
                                'target' => 'human',
                                'assignment_mode' => (string) Arr::get($node, 'config.assignment_mode', 'auto_assign'),
                                'assigned_user_id' => $handoffResult['assigned_user_id'] ?? null,
                                'ticket_id' => $handoffResult['ticket_id'] ?? null,
                                'started_at' => now()->toISOString(),
                            ],
                        ]), $context),
                        'last_activity_at' => now(),
                    ]);
                    return;
                case 'handoff_to_ai_assistant':
                    $handoffResult = $this->conversationHandoff->startAiHandoff($contact);
                    if (!($handoffResult['successful'] ?? false)) {
                        $this->failRunAtNode($run, $currentNodeId, $context, $handoffResult, [
                            'type' => 'handoff_to_ai_assistant',
                        ]);
                        return;
                    }
                    $this->recordStep($run, $currentNodeId, 'waiting', $context, $handoffResult, [
                        'type' => 'handoff_to_ai_assistant',
                        'reason' => 'handoff_started',
                    ]);
                    $run->update([
                        'status' => 'waiting_handoff',
                        'waiting_node_id' => $currentNodeId,
                        'waiting_for' => 'ai_handoff',
                        'state_json' => $this->runtimeSupport->mergeContext(array_merge($run->state_json ?? [], [
                            'handoff' => [
                                'target' => 'ai_assistant',
                                'started_at' => now()->toISOString(),
                            ],
                        ]), $context),
                        'last_activity_at' => now(),
                    ]);
                    return;
                case 'send_email':
                    $config = Arr::get($node, 'config', []);
                    $result = $this->actionDispatch->sendEmail($run->flow, $contact, $currentNodeId, $config);
                    if (!($result['sent'] ?? false)) {
                        $this->failRunAtNode($run, $currentNodeId, $context, $result, [
                            'type' => 'send_email',
                        ]);
                        return;
                    }
                    $this->recordStep($run, $currentNodeId, 'executed', $context, $result);
                    $nextNodeId = $this->resolveNextNodeId($outgoing, 'default');
                    break;
                case 'delay':
                    $minutes = max(1, (int) Arr::get($node, 'config.minutes', 1));
                    $resumeAt = now()->addMinutes($minutes);
                    $this->recordStep($run, $currentNodeId, 'waiting', $context, ['resume_at' => $resumeAt->toISOString()]);
                    $run->update([
                        'status' => 'waiting_delay',
                        'waiting_node_id' => $currentNodeId,
                        'waiting_for' => 'delay',
                        'next_resume_at' => $resumeAt,
                        'last_activity_at' => now(),
                    ]);

                    ResumeAutomationFlowRunJob::dispatch($run->id)
                        ->onQueue((string) config('automation_flows.resume_queue', 'default'))
                        ->delay($resumeAt);
                    return;
                case 'end':
                    $this->recordStep($run, $currentNodeId, 'executed', $context, ['type' => 'end']);
                    $run->update([
                        'status' => 'completed',
                        'current_node_id' => $currentNodeId,
                        'waiting_node_id' => null,
                        'waiting_for' => null,
                        'completed_at' => now(),
                        'last_activity_at' => now(),
                    ]);
                    return;
                default:
                    $run->update([
                        'status' => 'failed',
                        'completed_at' => now(),
                    ]);
                    return;
            }
            if (!$nextNodeId) {
                $run->update([
                    'status' => 'completed',
                    'current_node_id' => $currentNodeId,
                    'waiting_node_id' => null,
                    'waiting_for' => null,
                    'completed_at' => now(),
                    'last_activity_at' => now(),
                ]);
                return;
            }
            $currentNodeId = $nextNodeId;
            $run->update([
                'status' => 'active',
                'current_node_id' => $currentNodeId,
                'waiting_node_id' => null,
                'waiting_for' => null,
                'last_activity_at' => now(),
            ]);
        }

        // Defensive fallback: never leave a run active when execution exits abnormally.
        $reason = ($steps >= $maxSteps && $currentNodeId && isset($nodeMap[$currentNodeId]))
            ? 'max_steps_exceeded'
            : 'invalid_node';
        $context = Arr::get($run->state_json ?? [], 'context', []);
        $this->recordStep($run, $currentNodeId, 'failed', $context, null, [
            'reason' => $reason,
            'max_steps' => $maxSteps,
            'steps_executed' => $steps,
        ]);
        $run->update([
            'status' => 'failed',
            'current_node_id' => $currentNodeId,
            'waiting_node_id' => null,
            'waiting_for' => null,
            'completed_at' => now(),
            'last_activity_at' => now(),
        ]);
    }
    private function triggerMatches(AutomationFlow $flow, Chat $chat, array $inboundContext, ?bool &$isFirstInboundMessage): bool
    {
        $compiled = $flow->currentVersion?->compiled_json ?? [];
        $startNodeId = Arr::get($compiled, 'start_node_id');
        $startNode = Arr::get($compiled, 'nodes.' . $startNodeId);
        if (!$startNode || ($startNode['type'] ?? null) !== 'trigger') {
            return false;
        }
        $config = Arr::get($startNode, 'config', []);
        $matchMode = (string) Arr::get($config, 'match_mode', 'any_incoming');
        $message = mb_strtolower((string) Arr::get($inboundContext, 'last_user_message', ''));
        if ($matchMode === 'any_incoming') {
            return $message !== ''
                || filled(Arr::get($inboundContext, 'selected_button_id'))
                || filled(Arr::get($inboundContext, 'selected_list_row_id'))
                || filled(Arr::get($inboundContext, 'input_type'));
        }
        if ($matchMode === 'first_in_conversation') {
            if ($isFirstInboundMessage === null) {
                $isFirstInboundMessage = $this->isFirstInboundMessage($chat);
            }
            return $isFirstInboundMessage;
        }
        if ($matchMode === 'keyword_match') {
            $keywords = collect(Arr::get($config, 'keywords', []))
                ->map(fn ($keyword) => mb_strtolower(trim((string) $keyword)))
                ->filter();
            return $keywords->contains(fn ($keyword) => str_contains($message, $keyword));
        }
        return false;
    }
    private function withContactLock(int $organizationId, int $contactId, callable $callback, mixed $fallback = null): mixed
    {
        $lockKey = sprintf('automation-flow-runtime:%d:%d', $organizationId, $contactId);
        $ttlSeconds = max(5, (int) config('automation_flows.runtime.contact_lock_ttl_seconds', 10));
        $waitSeconds = max(1, (int) config('automation_flows.runtime.contact_lock_wait_seconds', 3));

        try {
            return Cache::store(config('cache.default'))
                ->lock($lockKey, $ttlSeconds)
                ->block($waitSeconds, $callback);
        } catch (LockTimeoutException) {
            return $fallback;
        } catch (\Throwable $exception) {
            report($exception);
            return $callback();
        }
    }
    private function isFirstInboundMessage(Chat $chat): bool
    {
        return Chat::query()
            ->where('organization_id', $chat->organization_id)
            ->where('contact_id', $chat->contact_id)
            ->where('type', 'inbound')
            ->count() === 1;
    }
    private function wasWhatsappSendSuccessful(mixed $response): bool
    {
        if (is_object($response) && property_exists($response, 'success')) {
            return (bool) $response->success;
        }
        if (is_array($response) && array_key_exists('success', $response)) {
            return (bool) $response['success'];
        }
        return false;
    }
    private function whatsappFailurePayload(mixed $response): array
    {
        $payload = [
            'message' => null,
            'error_code' => null,
            'error_subcode' => null,
        ];
        if (!is_object($response)) {
            return $payload;
        }
        $payload['message'] = (string) (
            $response->message
            ?? data_get($response->data ?? null, 'error.error_user_msg')
            ?? data_get($response->data ?? null, 'error.message')
            ?? ''
        );
        $payload['error_code'] = data_get($response->data ?? null, 'error.code');
        $payload['error_subcode'] = data_get($response->data ?? null, 'error.error_subcode');
        return $payload;
    }
    private function evaluateCondition(Contact $contact, array $config, array $state): string
    {
        return $this->runtimeSupport->evaluateCondition($contact, $config, $state);
    }
    private function resolveNextNodeId(array $edges, string $preferredBranch): ?string
    {
        return $this->runtimeSupport->resolveNextNodeId($edges, $preferredBranch);
    }
    private function recordStep(AutomationFlowRun $run, ?string $nodeId, string $status, ?array $input, ?array $output = null, ?array $metadata = null): void
    {
        $this->runtimeSupport->recordStep($run, $nodeId, $status, $input, $output, $metadata);
    }
    private function expireStaleRunIfNeeded(AutomationFlowRun $run): bool
    {
        return $this->runtimeSupport->expireStaleRunIfNeeded($run);
    }
    private function failRunAtNode(AutomationFlowRun $run, string $nodeId, array $context, array $result, array $metadata = []): void
    {
        $this->runtimeSupport->failRunAtNode($run, $nodeId, $context, $result, $metadata);
    }
    private function resumeHandoffRun(AutomationFlowRun $run, Chat $chat): bool
    {
        return $this->runtimeSupport->resumeHandoffRun($run, $chat);
    }
    private function whatsapp(int $organizationId)
    {
        return $this->runtimeSupport->whatsappForOrganization($organizationId);
    }

}
