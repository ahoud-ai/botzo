<template>
    <div class="grid gap-4">
        <label class="block">
            <div class="mb-2 text-sm font-semibold text-[var(--ui-text)]">{{ $t('Match mode') }}</div>
            <select class="nodrag w-full rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-3 text-sm text-[var(--ui-text)] outline-none transition focus:border-[var(--ui-secondary)]" :value="config.match_mode || 'any_incoming'" @change="setConfigValue('match_mode', $event.target.value)">
                <option value="any_incoming">{{ $t('Any incoming message') }}</option>
                <option value="first_in_conversation">{{ $t('First message in the conversation') }}</option>
                <option value="keyword_match">{{ $t('Keyword match') }}</option>
            </select>
        </label>

        <label v-if="config.match_mode === 'keyword_match'" class="block">
            <div class="mb-2 text-sm font-semibold text-[var(--ui-text)]">{{ $t('Keywords') }}</div>
            <input type="text" class="nodrag w-full rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-3 text-sm text-[var(--ui-text)] outline-none transition focus:border-[var(--ui-secondary)]" :value="keywordInput" :placeholder="$t('Example: pricing, demo, sales')" @input="updateKeywords($event.target.value)" />
        </label>

        <label class="block">
            <div class="mb-2 text-sm font-semibold text-[var(--ui-text)]">{{ $t('Starting Step') }}</div>
            <select class="nodrag w-full rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-3 text-sm text-[var(--ui-text)] outline-none transition focus:border-[var(--ui-secondary)]" :value="config.starting_step || ''" @change="updateStartingStep($event.target.value)">
                <option value="">{{ $t('Select option') }}</option>
                <option v-for="option in startingSteps" :key="option.id" :value="option.id">{{ option.label }}</option>
            </select>
        </label>
    </div>
</template>

<script setup>
import { useFlowNodeInspectorContext } from '@/Components/AutomationFlows/useFlowNodeInspector.js';

const { config, keywordInput, setConfigValue, startingSteps, updateKeywords, updateStartingStep } = useFlowNodeInspectorContext();
</script>
