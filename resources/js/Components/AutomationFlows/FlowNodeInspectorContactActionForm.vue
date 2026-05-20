<template>
    <div v-if="nodeType === 'add_to_group' || nodeType === 'remove_from_group'" class="grid gap-4">
        <label class="block">
            <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('Select Group') }}</div>
            <select class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :value="config.group_uuid || ''" @change="setConfigValue('group_uuid', $event.target.value)">
                <option value="">{{ $t('Select a group') }}</option>
                <option v-for="group in contactGroups" :key="group.uuid" :value="group.uuid">{{ group.name }}</option>
            </select>
        </label>

        <details class="nodrag rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
            <summary class="nodrag cursor-pointer list-none text-sm font-semibold text-slate-900">
                {{ $t('Advanced settings') }}
            </summary>
            <label class="mt-3 flex items-center gap-3 text-sm font-medium text-slate-700">
                <input type="checkbox" class="nodrag h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-200" :checked="Boolean(config.active ?? true)" @change="setConfigValue('active', $event.target.checked)" />
                {{ $t('Active') }}
            </label>
        </details>
    </div>

    <div v-else class="grid gap-4">
        <label class="block">
            <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('Save into') }}</div>
            <select class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :value="normalizedSaveTarget" @change="setConfigValue('save_target', $event.target.value)">
                <option value="contact_field">{{ $t('Contact field') }}</option>
                <option value="session_variable">{{ $t('Flow variable') }}</option>
            </select>
        </label>

        <label v-if="normalizedSaveTarget === 'contact_field'" class="block">
            <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('Select Contact Field') }}</div>
            <select class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :value="config.field_uuid || ''" @change="setConfigValue('field_uuid', $event.target.value)">
                <option value="">{{ $t('Choose which field to update') }}</option>
                <option v-for="field in contactFields" :key="field.uuid" :value="field.uuid">{{ field.name }}</option>
            </select>
        </label>

        <label v-else class="block">
            <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('Flow variable key') }}</div>
            <input type="text" class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :maxlength="flowVariableKeyMaxLength" :value="config.variable_key || ''" :placeholder="$t('Example: service_interest')" @input="setConfigValue('variable_key', $event.target.value)" />
        </label>

        <label v-if="nodeType === 'update_contact_field'" class="block">
            <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('Update mode') }}</div>
            <select class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :value="normalizedUpdateMode" @change="setConfigValue('mode', $event.target.value)">
                <option value="save_last_user_message">{{ $t('Save the latest customer reply') }}</option>
                <option value="session_variable">{{ $t('Use an existing flow variable') }}</option>
                <option value="static">{{ $t('Save a static value') }}</option>
            </select>
        </label>

        <label v-if="nodeType === 'update_contact_field' && normalizedUpdateMode === 'session_variable'" class="block">
            <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('Source flow variable') }}</div>
            <input type="text" class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :maxlength="flowVariableKeyMaxLength" :value="config.source_variable_key || ''" :placeholder="$t('Example: service_interest')" @input="setConfigValue('source_variable_key', $event.target.value)" />
        </label>

        <label v-if="nodeType === 'update_contact_field' && normalizedUpdateMode === 'static'" class="block">
            <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('Static value') }}</div>
            <input type="text" class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :value="config.value || ''" :placeholder="normalizedSaveTarget === 'session_variable' ? $t('Add the static value that should be stored only inside this flow.') : $t('Add the static value that should be stored in the contact field.')" @input="setConfigValue('value', $event.target.value)" />
        </label>

        <details class="nodrag rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
            <summary class="nodrag cursor-pointer list-none text-sm font-semibold text-slate-900">
                {{ $t('Advanced settings') }}
            </summary>
            <div class="mt-3 grid gap-3">
                <div v-if="normalizedSaveTarget === 'session_variable'" class="text-xs leading-5 text-slate-500">
                    {{ $t('Keep this value only inside the current flow until it finishes.') }}
                </div>
                <label v-if="nodeType === 'update_contact_field'" class="flex items-center gap-3 text-sm font-medium text-slate-700">
                    <input type="checkbox" class="nodrag h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-200" :checked="Boolean(config.active ?? true)" @change="setConfigValue('active', $event.target.checked)" />
                    {{ $t('Active') }}
                </label>
            </div>
        </details>
    </div>
</template>

<script setup>
import { useFlowNodeInspectorContext } from '@/Components/AutomationFlows/useFlowNodeInspector.js';

const {
    config,
    contactFields,
    contactGroups,
    flowVariableKeyMaxLength,
    nodeType,
    normalizedSaveTarget,
    normalizedUpdateMode,
    setConfigValue,
} = useFlowNodeInspectorContext();
</script>
