<template>
    <div v-if="nodeType === 'assign_to_agent' || nodeType === 'human_handoff'" class="grid gap-4">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs font-medium text-slate-600">
            {{ nodeType === 'human_handoff' ? $t('Pause the flow and send this conversation to customer service.') : $t('Open the service ticket and assign the conversation before the flow continues.') }}
        </div>

        <label class="block">
            <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('Assignment mode') }}</div>
            <select class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :value="config.assignment_mode || 'auto_assign'" @change="setConfigValue('assignment_mode', $event.target.value)">
                <option v-for="option in handoffAssignmentModeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
        </label>

        <label v-if="config.assignment_mode === 'specific_agent'" class="block">
            <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('Service agent') }}</div>
            <select class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :value="config.agent_user_id || ''" @change="setConfigValue('agent_user_id', $event.target.value ? Number($event.target.value) : '')">
                <option value="">{{ $t('Choose a valid service agent for this step.') }}</option>
                <option v-for="agent in assignableAgents" :key="agent.user_id" :value="agent.user_id">{{ agent.name }}</option>
            </select>
        </label>

        <details class="nodrag rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
            <summary class="nodrag cursor-pointer list-none text-sm font-semibold text-slate-900">
                {{ $t('Advanced settings') }}
            </summary>
            <div class="mt-3 grid gap-3">
                <label class="flex items-center gap-3 text-sm font-medium text-slate-700">
                    <input type="checkbox" class="nodrag h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-200" :checked="Boolean(config.reopen_closed_ticket ?? true)" @change="setConfigValue('reopen_closed_ticket', $event.target.checked)" />
                    {{ $t('Reopen a closed ticket before assigning') }}
                </label>

                <div class="grid gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-600">
                    <div class="flex items-center justify-between gap-3">
                        <span>{{ $t('Ticketing') }}</span>
                        <span :class="ticketingIsAvailable ? 'text-emerald-600' : 'text-rose-600'">{{ ticketingIsAvailable ? $t('Ready') : $t('Needs activation') }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span>{{ $t('Assignable agents') }}</span>
                        <span :class="hasAssignableAgents ? 'text-emerald-600' : 'text-amber-600'">{{ hasAssignableAgents ? assignableAgents.length : $t('None yet') }}</span>
                    </div>
                </div>
            </div>
        </details>
    </div>

    <div v-else class="grid gap-4">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-600">
            {{ $t('Pause this flow and let the built-in AI assistant handle the next replies.') }}
        </div>
        <details class="nodrag rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3" open>
            <summary class="nodrag cursor-pointer list-none text-sm font-semibold text-slate-900">
                {{ $t('Advanced settings') }}
            </summary>
            <div class="mt-3 grid gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-600">
                <div class="flex items-center justify-between gap-3">
                    <span>{{ $t('AI module') }}</span>
                    <span :class="handoffCapabilities.ai_assistant_module_enabled ? 'text-emerald-600' : 'text-rose-600'">{{ handoffCapabilities.ai_assistant_module_enabled ? $t('Ready') : $t('Not enabled') }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span>{{ $t('AI assistant status') }}</span>
                    <span :class="aiAssistantAvailable ? 'text-emerald-600' : 'text-rose-600'">{{ aiAssistantAvailable ? $t('Active') : $t('Needs activation') }}</span>
                </div>
            </div>
        </details>
    </div>
</template>

<script setup>
import { useFlowNodeInspectorContext } from '@/Components/AutomationFlows/useFlowNodeInspector.js';

const {
    aiAssistantAvailable,
    assignableAgents,
    config,
    handoffAssignmentModeOptions,
    handoffCapabilities,
    hasAssignableAgents,
    nodeType,
    setConfigValue,
    ticketingIsAvailable,
} = useFlowNodeInspectorContext();
</script>
