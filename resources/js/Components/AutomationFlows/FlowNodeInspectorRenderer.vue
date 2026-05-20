<template>
    <article class="inspector-shell flex flex-col gap-3" :class="[shellClass, uiEnhanced ? 'inspector-shell-enhanced' : '']">
        <header :class="headerClass">
            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[15px] border text-slate-700" :class="cardTone.iconClass">
                <component :is="IconComponent" class="h-4 w-4" />
            </span>

            <div class="min-w-0 flex-1">
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em]" :class="cardTone.categoryClass">{{ nodeCategoryLabel }}</span>
                    <span v-if="statusBadge" class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-semibold" :class="statusBadge.className">{{ statusBadge.label }}</span>
                </div>
                <h3 class="mt-1.5 text-sm font-semibold text-slate-950">{{ displayTitle }}</h3>
                <p v-if="compactSummary && !isInlineVariant" class="mt-1.5 text-xs leading-5 text-slate-500">{{ compactSummary }}</p>
            </div>

            <button
                v-if="isInlineVariant"
                type="button"
                class="nodrag inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-slate-900"
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

            <div v-if="errors.length" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700">
                <ul class="space-y-1">
                    <li v-for="(error, index) in visibleErrors" :key="`${error}-${index}`" class="flex items-start gap-1.5">
                        <span class="mt-0.5 text-[10px]">•</span>
                        <span>{{ error }}</span>
                    </li>
                </ul>
                <div v-if="hiddenErrorCount > 0" class="mt-2 text-[11px] font-semibold text-rose-700">{{ hiddenIssuesLabel }}</div>
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
        : 'min-h-full rounded-[20px] border border-slate-200 bg-white p-3'
));
const headerClass = computed(() => (
    isInlineVariant.value
        ? 'flex items-start gap-3 rounded-[18px] border border-slate-200 bg-white px-3 py-2.5 shadow-sm'
        : 'flex items-start gap-3 rounded-[18px] border border-slate-200 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(248,250,252,0.92))] px-3.5 py-3.5'
));
const outcomeClass = computed(() => (
    isInlineVariant.value
        ? 'border-emerald-200 bg-white text-emerald-900'
        : cardTone.value.accentClass
));
const formBodyClass = computed(() => (
    isInlineVariant.value
        ? 'flow-node-editor-scroll grid max-h-[360px] gap-3 overflow-y-auto rounded-[18px] border border-slate-200 bg-white p-3 pe-1 shadow-sm'
        : 'grid gap-3'
));
</script>

<style scoped>
.inspector-shell.inspector-shell-enhanced :is(input:not([type='checkbox']):not([type='radio']), textarea, select) {
    border-color: #dbe3ef !important;
    background-color: #f8fafc !important;
    color: #0f172a !important;
}

.inspector-shell.inspector-shell-enhanced :is(input:not([type='checkbox']):not([type='radio']), textarea, select)::placeholder {
    color: #94a3b8 !important;
}

.inspector-shell.inspector-shell-enhanced :is(input:not([type='checkbox']):not([type='radio']), textarea, select):focus {
    border-color: #0f766e !important;
    box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.12);
}
</style>
