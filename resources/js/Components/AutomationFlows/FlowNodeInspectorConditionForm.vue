<template>
    <div v-if="nodeType === 'condition'" class="grid gap-4">
        <label class="block">
            <div class="mb-2 text-sm font-semibold text-[var(--ui-text)]">{{ $t('Condition source') }}</div>
            <select class="nodrag w-full rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-3 text-sm text-[var(--ui-text)] outline-none transition focus:border-[var(--ui-secondary)]" :value="config.source || 'last_user_message'" @change="setConfigValue('source', $event.target.value)">
                <option value="last_user_message">{{ $t('Last user message') }}</option>
                <option value="selected_button_id">{{ $t('Selected button') }}</option>
                <option value="selected_list_row_id">{{ $t('Selected list row') }}</option>
                <option value="contact_field">{{ $t('Contact field') }}</option>
                <option value="flow_variable">{{ $t('Flow variable') }}</option>
            </select>
        </label>

        <label v-if="config.source === 'contact_field'" class="block">
            <div class="mb-2 text-sm font-semibold text-[var(--ui-text)]">{{ $t('Contact field') }}</div>
            <select class="nodrag w-full rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-3 text-sm text-[var(--ui-text)] outline-none transition focus:border-[var(--ui-secondary)]" :value="config.field_uuid || ''" @change="setConfigValue('field_uuid', $event.target.value)">
                <option value="">{{ $t('Choose which field to update') }}</option>
                <option v-for="field in contactFields" :key="field.uuid" :value="field.uuid">{{ field.name }}</option>
            </select>
        </label>

        <label v-if="config.source === 'flow_variable'" class="block">
            <div class="mb-2 text-sm font-semibold text-[var(--ui-text)]">{{ $t('Flow variable key') }}</div>
            <input type="text" class="nodrag w-full rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-3 text-sm text-[var(--ui-text)] outline-none transition focus:border-[var(--ui-secondary)]" :maxlength="flowVariableKeyMaxLength" :value="config.variable_key || ''" :placeholder="$t('Example: service_interest')" @input="setConfigValue('variable_key', $event.target.value)" />
        </label>

        <label class="block">
            <div class="mb-2 text-sm font-semibold text-[var(--ui-text)]">{{ $t('Operator') }}</div>
            <select class="nodrag w-full rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-3 text-sm text-[var(--ui-text)] outline-none transition focus:border-[var(--ui-secondary)]" :value="config.operator || 'equals'" @change="setConfigValue('operator', $event.target.value)">
                <option value="equals">{{ $t('Equals') }}</option>
                <option value="not_equals">{{ $t('Does not equal') }}</option>
                <option value="contains">{{ $t('Contains') }}</option>
                <option value="filled">{{ $t('Is filled') }}</option>
                <option value="not_filled">{{ $t('Is empty') }}</option>
            </select>
        </label>

        <label v-if="!['filled', 'not_filled'].includes(config.operator || '')" class="block">
            <div class="mb-2 text-sm font-semibold text-[var(--ui-text)]">{{ $t('Value') }}</div>
            <input type="text" class="nodrag w-full rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-3 text-sm text-[var(--ui-text)] outline-none transition focus:border-[var(--ui-secondary)]" :value="config.value || ''" @input="setConfigValue('value', $event.target.value)" />
        </label>
    </div>

    <div v-else class="rounded-2xl bg-[var(--ui-surface-soft)] px-4 py-4 text-sm leading-6 text-[var(--ui-muted)]">
        {{ $t('This step ends the journey.') }}
    </div>
</template>

<script setup>
import { useFlowNodeInspectorContext } from '@/Components/AutomationFlows/useFlowNodeInspector.js';

const { config, contactFields, flowVariableKeyMaxLength, nodeType, setConfigValue } = useFlowNodeInspectorContext();
</script>
