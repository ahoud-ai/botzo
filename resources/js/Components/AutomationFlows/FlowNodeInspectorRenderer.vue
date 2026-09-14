<template>
    <article class="inspector-shell flex flex-col gap-3" :class="[shellClass, uiEnhanced ? 'inspector-shell-enhanced' : '']">
        <header :class="headerClass">
            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[15px] border text-[var(--ui-text)]" :class="cardTone.iconClass">
                <component :is="IconComponent" class="h-4 w-4" />
            </span>

            <div class="min-w-0 flex-1">
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em]" :class="cardTone.categoryClass">{{ nodeCategoryLabel }}</span>
                    <span v-if="statusBadge" class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-semibold" :class="statusBadge.className">{{ statusBadge.label }}</span>
                </div>
                <h3 class="mt-1.5 text-sm font-semibold text-[var(--ui-text)]">{{ displayTitle }}</h3>
                <p v-if="compactSummary && !isInlineVariant" class="mt-1.5 text-xs leading-5 text-[var(--ui-muted)]">{{ compactSummary }}</p>
            </div>

            <button
                v-if="isInlineVariant"
                type="button"
                class="nodrag inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] text-[var(--ui-muted)] transition hover:border-[var(--ui-border-strong)] hover:text-[var(--ui-text)]"
                :title="$t('Collapse editor')"
                @click="$emit('close-inline')"
            >
                <X class="h-4 w-4" />
            </button>
        </header>

        <div v-if="customerOutcomeText && !isInlineVariant" class="rounded-[18px] border px-3.5 py-3" :class="outcomeClass">
            <div class="text-[10px] font-semibold uppercase tracking-[0.16em] opacity-75">{{ $t('Customer outcome') }}</div>
            <div class="mt-1.5 text-xs font-semibold leading-5">{{ customerOutcomeText }}</div>
        </div>

        <div :class="formBodyClass">
            <FlowNodeInspectorTriggerForm v-if="nodeType === 'trigger'" />
            <FlowNodeInspectorTextMediaForm v-else-if="nodeType === 'send_text' || looksLikeMediaNode" />
            <FlowNodeInspectorInteractiveForm v-else-if="nodeType === 'send_buttons' || nodeType === 'send_list'" />
            <FlowNodeInspectorContactActionForm v-else-if="['add_to_group', 'remove_from_group', 'update_contact_field', 'save_reply_to_field'].includes(nodeType)" />
            <FlowNodeInspectorHandoffForm v-else-if="['assign_to_agent', 'human_handoff', 'handoff_to_ai_assistant'].includes(nodeType)" />
            <FlowNodeInspectorExternalActionForm v-else-if="['send_email', 'delay'].includes(nodeType)" />
            <FlowNodeInspectorConditionForm v-else />

            <div v-if="errors.length" class="rounded-xl border border-[color-mix(in_srgb,var(--ui-danger)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-danger)_12%,var(--ui-surface))] px-3 py-2 text-xs font-medium text-[var(--ui-danger)]">
                <ul class="space-y-1">
                    <li v-for="(error, index) in visibleErrors" :key="`${error}-${index}`" class="flex items-start gap-1.5">
                        <span class="mt-0.5 text-[10px]">•</span>
                        <span>{{ error }}</span>
                    </li>
                </ul>
                <div v-if="hiddenErrorCount > 0" class="mt-2 text-[11px] font-semibold text-[var(--ui-danger)]">{{ hiddenIssuesLabel }}</div>
            </div>
        </div>
    </article>
</template>

<script setup>
import { computed } from 'vue';
import { X } from 'lucide-vue-next';
import FlowNodeInspectorConditionForm from '@/Components/AutomationFlows/FlowNodeInspectorConditionForm.vue';
import FlowNodeInspectorContactActionForm from '@/Components/AutomationFlows/FlowNodeInspectorContactActionForm.vue';
import FlowNodeInspectorExternalActionForm from '@/Components/AutomationFlows/FlowNodeInspectorExternalActionForm.vue';
import FlowNodeInspectorHandoffForm from '@/Components/AutomationFlows/FlowNodeInspectorHandoffForm.vue';
import FlowNodeInspectorInteractiveForm from '@/Components/AutomationFlows/FlowNodeInspectorInteractiveForm.vue';
import FlowNodeInspectorTextMediaForm from '@/Components/AutomationFlows/FlowNodeInspectorTextMediaForm.vue';
import FlowNodeInspectorTriggerForm from '@/Components/AutomationFlows/FlowNodeInspectorTriggerForm.vue';
import { createFlowNodeInspector, provideFlowNodeInspector } from '@/Components/AutomationFlows/useFlowNodeInspector.js';

const props = defineProps({
    id: {
        type: String,
        required: true,
    },
    data: {
        type: Object,
        required: true,
    },
    selected: {
        type: Boolean,
        default: false,
    },
    variant: {
        type: String,
        default: 'inspector',
    },
});
defineEmits(['close-inline']);

const inspector = createFlowNodeInspector(props);
provideFlowNodeInspector(inspector);

const {
    IconComponent,
    cardTone,
    compactSummary,
    customerOutcomeText,
    displayTitle,
    errors,
    hiddenErrorCount,
    hiddenIssuesLabel,
    looksLikeMediaNode,
    nodeCategoryLabel,
    nodeType,
    statusBadge,
    uiEnhanced,
    visibleErrors,
} = inspector;

const isInlineVariant = computed(() => props.variant === 'inline');
const shellClass = computed(() => (
    isInlineVariant.value
        ? 'max-h-[68vh] rounded-[22px] bg-transparent'
        : 'min-h-full rounded-[20px] border border-[var(--ui-border)] bg-[var(--ui-surface)] p-3'
));
const headerClass = computed(() => (
    isInlineVariant.value
        ? 'flex items-start gap-3 rounded-[18px] border border-[var(--ui-border)] bg-[var(--ui-surface)] px-3 py-2.5 shadow-[var(--ui-shadow-1)]'
        : 'flex items-start gap-3 rounded-[18px] border border-[var(--ui-border)] bg-[linear-gradient(180deg,color-mix(in_srgb,var(--ui-surface)_98%,transparent),color-mix(in_srgb,var(--ui-surface-soft)_92%,transparent))] px-3.5 py-3.5'
));
const outcomeClass = computed(() => (
    isInlineVariant.value
        ? 'border-[color-mix(in_srgb,var(--ui-success)_35%,var(--ui-border))] bg-[var(--ui-surface)] text-[var(--ui-success)]'
        : cardTone.value.accentClass
));
const formBodyClass = computed(() => (
    isInlineVariant.value
        ? 'flow-node-editor-scroll grid max-h-[360px] gap-3 overflow-y-auto rounded-[18px] border border-[var(--ui-border)] bg-[var(--ui-surface)] p-3 pe-1 shadow-[var(--ui-shadow-1)]'
        : 'grid gap-3'
));
</script>

<style scoped>
.inspector-shell.inspector-shell-enhanced :is(input:not([type='checkbox']):not([type='radio']), textarea, select) {
    border-color: var(--ui-border) !important;
    background-color: var(--ui-surface-soft) !important;
    color: var(--ui-text) !important;
}

.inspector-shell.inspector-shell-enhanced :is(input:not([type='checkbox']):not([type='radio']), textarea, select)::placeholder {
    color: var(--ui-muted) !important;
}

.inspector-shell.inspector-shell-enhanced :is(input:not([type='checkbox']):not([type='radio']), textarea, select):focus {
    border-color: var(--ui-secondary) !important;
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--ui-secondary) 12%, transparent);
}
</style>
