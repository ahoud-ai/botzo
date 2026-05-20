<template>
    <div :class="surfaceRootClass">
        <div v-if="!isModalSurface" class="border-b border-slate-200 px-3 py-3">
            <div class="flex items-center justify-between gap-3">
                <div v-if="!collapsed" class="min-w-0">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Customer view') }}</div>
                    <div class="mt-1 truncate text-sm font-semibold text-slate-950">{{ activeNodeLabel || $t('Customer preview') }}</div>
                    <div v-if="!activeNodeLabel" class="mt-1 truncate text-xs text-slate-500">{{ $t('Preview the live WhatsApp journey before you publish.') }}</div>
                </div>

                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-slate-300 hover:text-slate-900"
                    @click="$emit('toggle')"
                >
                    <svg v-if="collapsed" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="m9 6l6 6l-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="m15 6l-6 6l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
            </div>
        </div>

        <div v-if="!isModalSurface && collapsed" class="flex flex-1 flex-col items-center gap-3 px-3 py-5">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-700">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <path d="M7 10h10M7 14h7m5 7l-3.8-2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2v4Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
            <span class="text-center text-[11px] font-semibold leading-4 text-slate-500">
                {{ $t('Customer view') }}
            </span>
        </div>

        <div v-else :class="surfaceBodyClass">
            <div :class="controlsCardClass">
                <template v-if="isModalSurface">
                    <button
                        type="button"
                        :class="primaryActionButtonClass"
                        :title="$t('Run preview')"
                        :disabled="!canRun || simulation.status === 'running'"
                        @click="runSimulation"
                    >
                        <Play class="h-4 w-4" />
                        <span class="sr-only">{{ $t('Run preview') }}</span>
                    </button>
                    <button
                        type="button"
                        :class="secondaryActionButtonClass"
                        :title="$t('Reset')"
                        :disabled="simulation.status === 'idle' && !simulation.steps.length && branchCoverageSummary.covered === 0"
                        @click="resetSimulation"
                    >
                        <RotateCcw class="h-4 w-4" />
                        <span class="sr-only">{{ $t('Reset') }}</span>
                    </button>
                    <span
                        v-if="validation.errors?.length"
                        class="inline-flex h-10 min-w-10 items-center justify-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-3 text-[11px] font-semibold text-rose-700"
                        :title="previewBlockersLabel"
                    >
                        <TriangleAlert class="h-3.5 w-3.5" />
                        <span>{{ validation.errors.length }}</span>
                    </span>
                </template>
                <template v-else>
                    <button
                        type="button"
                        :class="primaryActionButtonClass"
                        :disabled="!canRun || simulation.status === 'running'"
                        @click="runSimulation"
                    >
                        <Play class="h-3.5 w-3.5" />
                        <span>{{ $t('Run preview') }}</span>
                    </button>
                    <button
                        type="button"
                        :class="secondaryActionButtonClass"
                        :disabled="simulation.status === 'idle' && !simulation.steps.length && branchCoverageSummary.covered === 0"
                        @click="resetSimulation"
                    >
                        <RotateCcw class="h-3.5 w-3.5" />
                        <span>{{ $t('Reset') }}</span>
                    </button>
                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-medium text-slate-600">
                        {{ simulationStatusShortLabel }}
                    </span>
                    <span
                        v-if="branchCoverageSummary.total"
                        class="inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-medium"
                        :class="branchCoverageSummary.remaining > 0 ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'"
                    >
                        {{ coverageSummaryLabel }}
                    </span>
                    <span
                        v-if="validation.errors?.length"
                        class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-[11px] font-medium text-rose-700"
                    >
                        {{ previewBlockersLabel }}
                    </span>
                    <label
                        v-if="enforceCustomerCareWindow"
                        class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] font-medium text-slate-600"
                    >
                        <input v-model="assumeOpenCustomerCareWindow" type="checkbox" class="h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                        <span>{{ customerCareWindowLabel }}</span>
                    </label>
                </template>
            </div>

            <div :class="phoneStageClass">
                <div :class="phoneViewportClass">
                    <div :class="phoneFrameClass">
                    <div v-if="isModalSurface" class="flex justify-center bg-slate-950 px-6 py-1.5">
                        <span class="h-1.5 w-24 rounded-full bg-slate-700"></span>
                    </div>

                        <div :class="phoneHeaderClass">
                            <div class="flex items-center justify-between gap-2">
                                <div class="truncate text-xs font-semibold">{{ $t('WhatsApp conversation') }}</div>
                                <div class="text-[10px] opacity-90">{{ simulationStatusShortLabel }}</div>
                            </div>
                        </div>

                        <div :class="chatCanvasClass">
                            <div class="space-y-2">
                                <template v-for="(step, index) in renderedSteps" :key="step.id || `${step.node_id || 'step'}-${index}`">
                                    <div v-if="step.kind === 'system'" class="rounded-full bg-slate-100/95 px-3 py-2 text-center text-[11px] font-medium text-slate-600">
                                        {{ stepLabel(step) }}
                                    </div>
                                    <div v-else class="flex" :class="step.kind === 'user' ? 'justify-end' : 'justify-start'">
                                        <div class="flow-preview-bubble relative max-w-[92%] rounded-2xl border px-3 py-2 text-sm shadow-none" :class="bubbleClass(step)">
                                            <div class="whitespace-pre-wrap leading-6">{{ step.label }}</div>

                                            <div v-if="step.meta?.media_type" class="mt-2">
                                                <span class="inline-flex rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-600">
                                                    {{ mediaTypeLabel(step.meta.media_type) }}
                                                </span>
                                            </div>

                                            <div v-if="step.meta?.media_url && step.meta?.media_type === 'image'" class="mt-2 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                                                <img :src="step.meta.media_url" :alt="step.meta?.media_name || step.label" class="block max-h-72 w-full object-cover" />
                                            </div>

                                            <div v-else-if="step.meta?.media_url && step.meta?.media_type === 'video'" class="mt-2 overflow-hidden rounded-2xl border border-slate-200 bg-white p-2">
                                                <video :src="step.meta.media_url" controls preload="metadata" class="block max-h-72 w-full rounded-xl bg-slate-950" />
                                            </div>

                                            <div v-else-if="step.meta?.media_url && step.meta?.media_type === 'audio'" class="mt-2 rounded-2xl border border-slate-200 bg-white p-3">
                                                <audio :src="step.meta.media_url" controls preload="metadata" class="w-full" />
                                            </div>

                                            <div v-else-if="step.meta?.media_url && step.meta?.media_type === 'document'" class="mt-2 rounded-2xl border border-slate-200 bg-white px-3 py-2">
                                                <div class="text-xs font-semibold text-slate-700">{{ step.meta?.media_name || $t('Document') }}</div>
                                            </div>

                                            <div v-if="step.meta?.buttons?.length" class="mt-2 flex flex-wrap gap-1.5">
                                                <span v-for="button in step.meta.buttons" :key="button.id" class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-700">
                                                    {{ button.title }}
                                                </span>
                                            </div>

                                            <div v-if="step.meta?.sections?.length" class="mt-2 space-y-1.5 rounded-xl border border-slate-200 bg-slate-50 p-2 text-xs text-slate-600">
                                                <div v-if="step.meta?.button_label" class="rounded-xl border border-slate-200 bg-white px-2.5 py-2 font-medium text-slate-700">
                                                    {{ step.meta.button_label }}
                                                </div>
                                                <div v-for="section in step.meta.sections" :key="section.title" class="space-y-1.5">
                                                    <div class="font-semibold text-slate-700">{{ section.title }}</div>
                                                    <div v-for="row in section.rows" :key="row.id" class="rounded-xl border border-slate-200 bg-white px-2.5 py-2">
                                                        <div class="font-medium text-slate-800">{{ row.title }}</div>
                                                        <div v-if="row.description" class="mt-1 text-[11px] text-slate-500">{{ row.description }}</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div v-if="step.meta?.header" class="mt-2 text-[11px] font-semibold text-slate-700">{{ step.meta.header }}</div>
                                            <div v-if="step.meta?.footer" class="mt-2 text-[11px] text-slate-500">{{ step.meta.footer }}</div>

                                            <a v-if="step.meta?.media_url" :href="step.meta.media_url" target="_blank" class="mt-2 inline-flex items-center rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:border-slate-300 hover:text-slate-950">
                                                {{ $t('Preview file') }}
                                            </a>
                                        </div>
                                    </div>
                                </template>

                                <div v-if="!renderedSteps.length" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/95 px-4 py-8 text-center">
                                    <button
                                        type="button"
                                        :class="emptyStateActionButtonClass"
                                        :disabled="!canRun || simulation.status === 'running'"
                                        @click="runSimulation"
                                    >
                                        <Play class="h-3.5 w-3.5" />
                                        <span>{{ $t('Run preview') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div :class="composerClass">
                            <template v-if="simulation.waiting?.kind === 'buttons'">
                                <div class="mb-2 text-xs font-semibold text-slate-700">{{ $t('Choose a button') }}</div>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="option in simulation.waiting.options"
                                        :key="option.id"
                                        type="button"
                                        class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:border-slate-300 hover:bg-white"
                                        @click="selectButtonOption(option)"
                                    >
                                        {{ option.title }}
                                    </button>
                                </div>
                                <div class="mt-3 border-t border-slate-200 pt-3">
                                    <div class="mb-2 text-[11px] font-semibold text-slate-600">{{ $t('Or send a normal text') }}</div>
                                    <div class="flex items-center gap-2">
                                        <input
                                            v-model="invalidInteractiveReply"
                                            type="text"
                                            class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-emerald-300"
                                            :placeholder="$t('Write a normal reply')"
                                            @keydown.enter.prevent="submitInteractiveInvalidReply"
                                        />
                                        <button
                                            type="button"
                                            class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-950 disabled:cursor-not-allowed disabled:opacity-60"
                                            :disabled="!invalidInteractiveReply.trim()"
                                            @click="submitInteractiveInvalidReply"
                                        >
                                            {{ $t('Send text') }}
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <template v-else-if="simulation.waiting?.kind === 'list'">
                                <div class="mb-2 text-xs font-semibold text-slate-700">{{ $t('Choose an option') }}</div>
                                <div class="flex items-center gap-2">
                                    <select v-model="selectedListOptionId" class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-emerald-300">
                                        <option value="">{{ $t('Select an option') }}</option>
                                        <option v-for="option in simulation.waiting.options" :key="option.id" :value="option.id">{{ option.title }}</option>
                                    </select>
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-950 disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="selectedListOptionId === ''"
                                        @click="submitListOption"
                                    >
                                        {{ $t('Send') }}
                                    </button>
                                </div>
                                <div class="mt-3 border-t border-slate-200 pt-3">
                                    <div class="mb-2 text-[11px] font-semibold text-slate-600">{{ $t('Or send a normal text') }}</div>
                                    <div class="flex items-center gap-2">
                                        <input
                                            v-model="invalidInteractiveReply"
                                            type="text"
                                            class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-emerald-300"
                                            :placeholder="$t('Write a normal reply')"
                                            @keydown.enter.prevent="submitInteractiveInvalidReply"
                                        />
                                        <button
                                            type="button"
                                            class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-950 disabled:cursor-not-allowed disabled:opacity-60"
                                            :disabled="!invalidInteractiveReply.trim()"
                                            @click="submitInteractiveInvalidReply"
                                        >
                                            {{ $t('Send text') }}
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <template v-else-if="simulation.waiting?.kind === 'free_text'">
                                <div class="mb-2 text-xs font-semibold text-slate-700">{{ $t('Customer reply') }}</div>
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model="freeTextReply"
                                        type="text"
                                        class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-emerald-300"
                                        :placeholder="$t('Write the customer reply')"
                                        @keydown.enter.prevent="submitFreeText"
                                    />
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-950 disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="!freeTextReply.trim()"
                                        @click="submitFreeText"
                                    >
                                        {{ $t('Send') }}
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Play, RotateCcw, TriangleAlert } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { resolveFlowNodeTitle } from '@/Components/AutomationFlows/flowBuilderMeta.js';
import { translateFlowMessage } from '@/Components/AutomationFlows/flowBuilderCopy.js';

const emit = defineEmits(['toggle', 'simulation-state-change']);

const props = defineProps({
    preview: {
        type: Object,
        default: () => ({ scenario: 'main', steps: [] }),
    },
    collapsed: {
        type: Boolean,
        default: false,
    },
    surface: {
        type: String,
        default: 'dock',
    },
    activeNodeLabel: {
        type: String,
        default: '',
    },
    graph: {
        type: Object,
        default: () => ({ start_node_id: null, nodes: [], edges: [] }),
    },
    nodeTitles: {
        type: Object,
        default: () => ({}),
    },
    assets: {
        type: Object,
        default: () => ({}),
    },
    contactFields: {
        type: Array,
        default: () => ([]),
    },
    contactGroups: {
        type: Array,
        default: () => ([]),
    },
    assignableAgents: {
        type: Array,
        default: () => ([]),
    },
    uiEnhanced: {
        type: Boolean,
        default: true,
    },
    validation: {
        type: Object,
        default: () => ({ valid: true, errors: [], warnings: [] }),
    },
    builderPolicy: {
        type: Object,
        default: () => ({}),
    },
    runtimeRules: {
        type: Object,
        default: () => ({}),
    },
    insights: {
        type: Object,
        default: () => ({
            buttonChoices: 0,
            listChoices: 0,
            conditionSteps: 0,
            replyCaptureSteps: 0,
        }),
    },
});

const { t } = useI18n();
const isModalSurface = computed(() => props.surface === 'modal');
const surfaceRootClass = computed(() => (
    isModalSurface.value
        ? 'flex h-full min-h-0 flex-col bg-transparent'
        : 'flex h-full flex-col bg-white'
));
const surfaceBodyClass = computed(() => (
    isModalSurface.value
        ? 'mx-auto flex min-h-0 w-full max-w-[320px] flex-1 flex-col items-center gap-2.5 overflow-hidden bg-transparent'
        : 'flex min-h-0 flex-1 flex-col gap-2.5 overflow-hidden bg-slate-50/70 p-2.5'
));
const controlsCardClass = computed(() => (
    isModalSurface.value
        ? 'inline-flex w-auto items-center justify-center gap-1.5 rounded-full border border-slate-200/90 bg-white/92 p-1.5 shadow-[0_12px_24px_rgba(15,23,42,0.08)] backdrop-blur'
        : 'rounded-[18px] border border-slate-200 bg-white px-3 py-2.5'
));
const phoneStageClass = computed(() => (
    isModalSurface.value
        ? 'flex min-h-0 w-full flex-1 items-start justify-center overflow-hidden pb-1 pt-0.5'
        : ''
));
const phoneViewportClass = computed(() => (
    isModalSurface.value
        ? 'mx-auto w-full max-w-[298px] aspect-[9/18.5]'
        : 'mx-auto flex h-full w-full max-w-full'
));
const primaryActionButtonClass = computed(() => (
    isModalSurface.value
        ? 'inline-flex h-10 w-10 items-center justify-center rounded-full border border-emerald-700 bg-[#0f766e] text-white transition hover:bg-[#0b5f59] disabled:cursor-not-allowed disabled:opacity-60'
        : 'inline-flex items-center gap-2 rounded-xl border border-emerald-700 bg-[#0f766e] px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-[#0b5f59] disabled:cursor-not-allowed disabled:opacity-60'
));
const secondaryActionButtonClass = computed(() => (
    isModalSurface.value
        ? 'inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 transition hover:border-slate-300 hover:text-slate-950 disabled:cursor-not-allowed disabled:opacity-60'
        : 'inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-950 disabled:cursor-not-allowed disabled:opacity-60'
));
const emptyStateActionButtonClass = computed(() => (
    'inline-flex items-center gap-2 rounded-xl border border-emerald-700 bg-[#0f766e] px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-[#0b5f59] disabled:cursor-not-allowed disabled:opacity-60'
));

const freeTextReply = ref('');
const selectedListOptionId = ref('');
const invalidInteractiveReply = ref('');

const VALID_INVALID_REPLY_BEHAVIORS = [
    'release_to_fallback',
    'repeat_prompt',
    'end_run',
];
const EXTERNAL_ACTION_TYPES = ['send_email'];
const CRM_ACTION_TYPES = ['save_reply_to_field', 'add_to_group', 'remove_from_group', 'update_contact_field'];
const CUSTOMER_CARE_WINDOW_STEP_TYPES = ['send_text', 'send_media', 'send_buttons', 'send_list'];
const assumeOpenCustomerCareWindow = ref(true);
assumeOpenCustomerCareWindow.value = props.runtimeRules?.whatsapp?.preview_customer_care_window_open !== false;

const simulation = reactive({
    status: 'idle',
    runCount: 0,
    currentNodeId: null,
    waiting: null,
    steps: [],
    visitCounts: {},
    coveredBranches: {},
    context: {
        last_user_message: '',
        selected_button_id: '',
        selected_list_row_id: '',
        selected_list_row_title: '',
        contact_fields: {},
        flow_variables: {},
        customer_care_window_open: true,
    },
});

const compiledGraph = computed(() => {
    const graph = props.graph || {};
    const nodes = Array.isArray(graph.nodes) ? graph.nodes : [];
    const edges = Array.isArray(graph.edges) ? graph.edges : [];
    const nodeMap = {};
    const adjacency = {};

    nodes.forEach((node) => {
        if (!node?.id) {
            return;
        }
        nodeMap[node.id] = node;
    });

    edges.forEach((edge) => {
        const sourceId = edge?.source_id;
        const targetId = edge?.target_id;

        if (!sourceId || !targetId) {
            return;
        }

        if (!adjacency[sourceId]) {
            adjacency[sourceId] = [];
        }

        adjacency[sourceId].push({
            branch: edge?.branch || 'default',
            target_id: targetId,
        });
    });

    const triggerNode = nodes.find((node) => node?.type === 'trigger');
    const startNodeId = graph.start_node_id || triggerNode?.id || nodes[0]?.id || null;

    return {
        startNodeId,
        nodeMap,
        adjacency,
    };
});

const canRun = computed(() => Boolean(compiledGraph.value.startNodeId));
const projectedSteps = computed(() => Array.isArray(props.preview?.steps) ? props.preview.steps : []);
const renderedSteps = computed(() => (simulation.steps.length ? simulation.steps : projectedSteps.value));
const previewSyncSignature = computed(() => JSON.stringify({
    graph: props.graph || {},
    preview: props.preview || {},
}));
const phoneFrameClass = computed(() => (
    isModalSurface.value
        ? 'flow-preview-phone flex h-full w-full flex-col overflow-hidden rounded-[34px] border-[8px] border-slate-900 bg-[#d9ede4] shadow-[0_24px_60px_rgba(15,23,42,0.22)]'
        : (props.uiEnhanced
            ? 'flow-preview-phone mx-auto flex min-h-0 w-full max-w-full flex-1 flex-col overflow-hidden rounded-[28px] border border-slate-200 bg-[#d9ede4] shadow-[0_14px_36px_rgba(15,23,42,0.12)]'
            : 'mx-auto flex min-h-0 w-full max-w-full flex-1 flex-col overflow-hidden rounded-[28px] border border-slate-200 bg-[#d9ede4] shadow-[0_14px_36px_rgba(15,23,42,0.12)]')
));
const phoneHeaderClass = computed(() => (
    isModalSurface.value
        ? 'border-b border-slate-200 bg-[#0f766e] px-3 py-2.5 text-white'
        : 'border-b border-slate-200 bg-[#0f766e] px-4 py-3 text-white'
));
const chatCanvasClass = computed(() => (
    isModalSurface.value
        ? 'chat-bg flow-preview-chat min-h-0 flex-1 overflow-y-auto p-2'
        : 'chat-bg flow-preview-chat min-h-0 flex-1 overflow-y-auto p-2.5'
));
const composerClass = computed(() => (
    isModalSurface.value
        ? 'border-t border-slate-200 bg-white px-2.5 py-2.5'
        : 'border-t border-slate-200 bg-white px-3 py-3'
));
const normalizedBuilderPolicy = computed(() => {
    const raw = props.builderPolicy || {};
    const whatsappOnlyMode = Boolean(raw?.whatsapp_only_mode);

    return {
        allow_external_actions: whatsappOnlyMode ? false : (raw?.allow_external_actions ?? false),
        allow_crm_actions: raw?.allow_crm_actions ?? true,
    };
});
const conversationWindowHours = computed(() => Math.max(1, Number(props.runtimeRules?.whatsapp?.customer_care_window_hours || 24)));
const enforceCustomerCareWindow = computed(() => props.runtimeRules?.whatsapp?.enforce_customer_care_window !== false);

const makeCoverageKey = (nodeId, branch) => `${String(nodeId || '').trim()}:${String(branch || '').trim()}`;
const branchCoverageItems = computed(() => {
    const graphNodes = Array.isArray(props.graph?.nodes) ? props.graph.nodes : [];
    const items = [];

    graphNodes.forEach((node) => {
        if (!node?.id) {
            return;
        }

        if (node.type === 'condition') {
            items.push({
                key: makeCoverageKey(node.id, 'matched'),
                nodeId: node.id,
                nodeLabel: nodeLabel(node),
                label: t('Matched'),
                kind: 'condition',
            });
            items.push({
                key: makeCoverageKey(node.id, 'unmatched'),
                nodeId: node.id,
                nodeLabel: nodeLabel(node),
                label: t('Unmatched'),
                kind: 'condition',
            });
            return;
        }

        if (node.type === 'send_buttons') {
            const buttons = Array.isArray(node?.config?.buttons)
                ? node.config.buttons.filter((button) => button?.id && button?.title)
                : [];

            buttons.forEach((button) => {
                items.push({
                    key: makeCoverageKey(node.id, button.id),
                    nodeId: node.id,
                    nodeLabel: nodeLabel(node),
                    label: String(button.title),
                    kind: 'buttons',
                });
            });

            if (buttons.length) {
                items.push({
                    key: makeCoverageKey(node.id, 'invalid_reply'),
                    nodeId: node.id,
                    nodeLabel: nodeLabel(node),
                    label: t('Normal text reply'),
                    kind: 'buttons',
                });
            }

            return;
        }

        if (node.type === 'send_list') {
            const rows = flattenListOptions(Array.isArray(node?.config?.sections) ? node.config.sections : []);

            rows.forEach((row) => {
                items.push({
                    key: makeCoverageKey(node.id, row.id),
                    nodeId: node.id,
                    nodeLabel: nodeLabel(node),
                    label: String(row.title),
                    kind: 'list',
                });
            });

            if (rows.length) {
                items.push({
                    key: makeCoverageKey(node.id, 'invalid_reply'),
                    nodeId: node.id,
                    nodeLabel: nodeLabel(node),
                    label: t('Normal text reply'),
                    kind: 'list',
                });
            }
        }
    });

    return items;
});
const branchCoverageSummary = computed(() => {
    const total = branchCoverageItems.value.length;
    const covered = branchCoverageItems.value.filter((item) => simulation.coveredBranches[item.key]).length;

    return {
        total,
        covered,
        remaining: Math.max(total - covered, 0),
    };
});
const previewBlockersLabel = computed(() => translateFlowMessage(t, ':count blocker(s)', { count: props.validation?.errors?.length ?? 0 }));
const coverageSummaryLabel = computed(() => translateFlowMessage(t, ':covered / :total', {
    covered: branchCoverageSummary.value.covered,
    total: branchCoverageSummary.value.total,
}));
const customerCareWindowLabel = computed(() => translateFlowMessage(t, 'Customer care window (:hours h)', { hours: conversationWindowHours.value }));
const contactFieldNameMap = computed(() => new Map(
    (Array.isArray(props.contactFields) ? props.contactFields : [])
        .map((field) => [String(field?.uuid || '').trim(), String(field?.name || '').trim()])
        .filter(([uuid, name]) => uuid !== '' && name !== ''),
));
const contactGroupNameMap = computed(() => new Map(
    (Array.isArray(props.contactGroups) ? props.contactGroups : [])
        .map((group) => [String(group?.uuid || '').trim(), String(group?.name || '').trim()])
        .filter(([uuid, name]) => uuid !== '' && name !== ''),
));
const assignableAgentNameMap = computed(() => new Map(
    (Array.isArray(props.assignableAgents) ? props.assignableAgents : [])
        .map((agent) => [String(Number(agent?.user_id || 0)), String(agent?.name || '').trim()])
        .filter(([userId, name]) => userId !== '0' && name !== ''),
));

const simulationStatusShortLabel = computed(() => {
    if (simulation.status === 'running') {
        return t('Running');
    }

    if (simulation.status === 'waiting') {
        return t('Waiting');
    }

    if (simulation.status === 'completed') {
        return t('Completed');
    }

    return t('Idle');
});
let previewSyncTimer = null;
const clearPreviewSyncTimer = () => {
    if (typeof window !== 'undefined' && previewSyncTimer !== null) {
        window.clearTimeout(previewSyncTimer);
    }
    previewSyncTimer = null;
};
const resolveNextNodeId = (sourceId, preferredBranch = 'default') => {
    const outgoing = compiledGraph.value.adjacency[sourceId] || [];

    return outgoing.find((edge) => edge.branch === preferredBranch)?.target_id
        || outgoing.find((edge) => edge.branch === 'default')?.target_id
        || outgoing[0]?.target_id
        || null;
};

const nodeLabel = (node) => (
    props.nodeTitles?.[node?.id]
    || resolveFlowNodeTitle({
        type: node?.type,
        title: node?.ui?.title,
    }, t)
    || node?.id
    || 'node'
);
const quoted = (value) => {
    const normalized = String(value || '').trim();

    return `"${normalized || '-'}"`;
};
const resolveFieldName = (fieldUuid) => (
    contactFieldNameMap.value.get(String(fieldUuid || '').trim())
    || t('selected field')
);
const resolveGroupName = (groupUuid) => (
    contactGroupNameMap.value.get(String(groupUuid || '').trim())
    || t('selected group')
);
const resolveFlowVariableName = (key) => {
    const normalized = String(key || '').trim();

    return normalized || t('flow variable');
};
const resolveAgentName = (userId) => (
    assignableAgentNameMap.value.get(String(Number(userId || 0)))
    || t('selected service agent')
);
const describeAssignmentTarget = (config = {}) => {
    const mode = String(config.assignment_mode || 'auto_assign');

    if (mode === 'specific_agent') {
        return quoted(resolveAgentName(config.agent_user_id));
    }

    if (mode === 'unassigned') {
        return t('an open ticket without a fixed owner');
    }

    return t('the least busy service agent');
};
const describeStoredReply = (waitingState = {}, text = '') => {
    if (waitingState.saveTarget === 'session_variable' && waitingState.variableKey) {
        return translateFlowMessage(t, 'Preview saved :value in flow variable :target.', {
            value: quoted(text),
            target: quoted(resolveFlowVariableName(waitingState.variableKey)),
        });
    }

    return translateFlowMessage(t, 'Preview saved :value in contact field :target.', {
        value: quoted(text),
        target: quoted(resolveFieldName(waitingState.fieldUuid)),
    });
};
const describeUpdateContactAction = (config = {}, nextValue = null) => {
    const saveTarget = String(config.save_target || 'contact_field');
    const mode = String(config.mode || config.value_mode || 'save_last_user_message');

    if (saveTarget === 'session_variable') {
        const target = quoted(resolveFlowVariableName(config.variable_key));

        if (mode === 'static') {
            return translateFlowMessage(t, 'Preview would store :value in flow variable :target.', {
                value: quoted(config.value || t('static value')),
                target,
            });
        }

        if (mode === 'session_variable') {
            return translateFlowMessage(t, 'Preview would copy flow variable :source into flow variable :target.', {
                source: quoted(resolveFlowVariableName(config.source_variable_key)),
                target,
            });
        }

        return translateFlowMessage(t, 'Preview would store :value in flow variable :target.', {
            value: quoted(nextValue ?? t('latest customer reply')),
            target,
        });
    }

    const field = quoted(resolveFieldName(config.field_uuid));

    if (mode === 'static') {
        return translateFlowMessage(t, 'Preview would save :value to contact field :field.', {
            value: quoted(config.value || t('static value')),
            field,
        });
    }

    if (mode === 'session_variable') {
        return translateFlowMessage(t, 'Preview would copy flow variable :source into contact field :field.', {
            source: quoted(resolveFlowVariableName(config.source_variable_key)),
            field,
        });
    }

    return translateFlowMessage(t, 'Preview would save :value to contact field :field.', {
        value: quoted(nextValue ?? t('latest customer reply')),
        field,
    });
};
const describeSystemNode = (node, config = {}, extra = {}) => {
    if (node.type === 'add_to_group') {
        return translateFlowMessage(t, 'Preview would add this contact to group :group.', {
            group: quoted(resolveGroupName(config.group_uuid)),
        });
    }

    if (node.type === 'remove_from_group') {
        return translateFlowMessage(t, 'Preview would remove this contact from group :group.', {
            group: quoted(resolveGroupName(config.group_uuid)),
        });
    }

    if (node.type === 'update_contact_field') {
        return describeUpdateContactAction(config, extra.nextValue ?? null);
    }

    if (node.type === 'assign_to_agent') {
        return translateFlowMessage(t, 'Preview would open or reuse the service ticket and assign it to :target.', {
            target: describeAssignmentTarget(config),
        });
    }

    if (node.type === 'human_handoff') {
        return translateFlowMessage(t, 'Preview would hand this conversation to customer service through :target.', {
            target: describeAssignmentTarget(config),
        });
    }

    if (node.type === 'handoff_to_ai_assistant') {
        return t('Preview would pause this journey and hand the next replies to the AI assistant.');
    }

    if (node.type === 'delay') {
        return translateFlowMessage(t, 'Preview would wait :count minute(s) before the next step continues.', {
            count: Math.max(1, Number(config.minutes || 1)),
        });
    }

    if (node.type === 'send_email') {
        return translateFlowMessage(t, 'Preview would send an email with subject: :subject', {
            subject: String(config.subject || t('Configured email')),
        });
    }

    return translateFlowMessage(t, 'System event: :step', { step: nodeLabel(node) });
};
const isNodeBlockedByBuilderPolicy = (nodeType) => {
    if (!normalizedBuilderPolicy.value.allow_external_actions && EXTERNAL_ACTION_TYPES.includes(nodeType)) {
        return true;
    }

    if (!normalizedBuilderPolicy.value.allow_crm_actions && CRM_ACTION_TYPES.includes(nodeType)) {
        return true;
    }

    return false;
};
const requiresCustomerCareWindow = (nodeType) => CUSTOMER_CARE_WINDOW_STEP_TYPES.includes(nodeType);

const stepLabel = (step) => {
    const rawLabel = String(step?.label || '');
    if (rawLabel === '' || step?.kind !== 'system') {
        return rawLabel;
    }

    const translated = t(rawLabel);
    return translated === rawLabel ? rawLabel : translated;
};

const mediaTypeLabel = (mediaType) => {
    const normalized = String(mediaType || '').toLowerCase();
    if (normalized === '') {
        return '';
    }

    if (normalized === 'image') return t('Image');
    if (normalized === 'video') return t('Video');
    if (normalized === 'audio') return t('Audio');
    if (normalized === 'document') return t('Document');

    const translated = t(mediaType);
    return translated === mediaType ? mediaType : translated;
};

const pushStep = (step) => {
    simulation.steps.push({
        ...step,
        id: `${Date.now()}-${simulation.steps.length}-${Math.random().toString(36).slice(2, 6)}`,
    });
};

const addSystemStep = (label, meta = {}) => {
    pushStep({ kind: 'system', label, meta });
};

const addAssistantStep = (label, meta = {}) => {
    pushStep({ kind: 'assistant', label, meta });
};

const addUserStep = (label, meta = {}) => {
    pushStep({ kind: 'user', label, meta });
};
const markBranchCovered = (nodeId, branch) => {
    const key = makeCoverageKey(nodeId, branch);
    if (key === ':') {
        return;
    }

    simulation.coveredBranches = {
        ...simulation.coveredBranches,
        [key]: true,
    };
};

const resetContext = () => {
    simulation.context.last_user_message = '';
    simulation.context.selected_button_id = '';
    simulation.context.selected_list_row_id = '';
    simulation.context.selected_list_row_title = '';
    simulation.context.contact_fields = {};
    simulation.context.flow_variables = {};
    simulation.context.customer_care_window_open = assumeOpenCustomerCareWindow.value;
};

const finishSimulation = () => {
    simulation.status = 'completed';
    simulation.waiting = null;
    simulation.currentNodeId = null;
};

const evaluateConditionBranch = (config = {}) => {
    const source = String(config.source || 'last_user_message');
    const operator = String(config.operator || 'equals');
    const expected = String(config.value || '');

    const actual = source === 'selected_button_id'
        ? String(simulation.context.selected_button_id || '')
        : source === 'selected_list_row_id'
            ? String(simulation.context.selected_list_row_id || '')
            : source === 'contact_field'
                ? String(simulation.context.contact_fields?.[String(config.field_uuid || '')] || '')
                : source === 'flow_variable'
                    ? String(simulation.context.flow_variables?.[String(config.variable_key || '').trim()] || '')
                : String(simulation.context.last_user_message || '');

    const actualLower = actual.toLowerCase();
    const expectedLower = expected.toLowerCase();

    const matched = operator === 'equals'
        ? actualLower === expectedLower
        : operator === 'not_equals'
            ? actualLower !== expectedLower
            : operator === 'contains'
                ? expectedLower !== '' && actualLower.includes(expectedLower)
                : operator === 'filled'
                    ? actual !== ''
                    : operator === 'not_filled'
                        ? actual === ''
                        : false;

    return matched ? 'matched' : 'unmatched';
};

const flattenListOptions = (sections = []) => (
    sections
        .flatMap((section) => Array.isArray(section?.rows) ? section.rows : [])
        .filter((row) => row?.id && row?.title)
        .map((row) => ({
            id: String(row.id),
            title: String(row.title),
            description: String(row.description || ''),
        }))
);

const invalidReplyBehaviorForWaitingNode = (nodeId) => {
    const node = compiledGraph.value.nodeMap[String(nodeId || '')];
    const behavior = String(node?.config?.invalid_reply_behavior || 'release_to_fallback');

    return VALID_INVALID_REPLY_BEHAVIORS.includes(behavior) ? behavior : 'release_to_fallback';
};

const repeatInteractivePrompt = (waitingState) => {
    const node = compiledGraph.value.nodeMap[String(waitingState?.nodeId || '')];
    if (!node) {
        return;
    }

    const config = node.config || {};

    if (waitingState.kind === 'buttons') {
        addAssistantStep(String(config.body || nodeLabel(node)), {
            header: String(config.header || ''),
            footer: String(config.footer || ''),
            buttons: Array.isArray(waitingState.options) ? waitingState.options : [],
        });
    }

    if (waitingState.kind === 'list') {
        addAssistantStep(String(config.body || nodeLabel(node)), {
            header: String(config.header || ''),
            footer: String(config.footer || ''),
            button_label: String(config.button_label || ''),
            sections: Array.isArray(config.sections) ? config.sections : [],
        });
    }
};

const continueSimulation = () => {
    if (!simulation.currentNodeId) {
        finishSimulation();
        return;
    }

    simulation.status = 'running';
    let safetyCounter = 0;

    while (simulation.currentNodeId && safetyCounter < 80) {
        safetyCounter += 1;
        const node = compiledGraph.value.nodeMap[simulation.currentNodeId];

        if (!node) {
            finishSimulation();
            break;
        }

        simulation.visitCounts[node.id] = (simulation.visitCounts[node.id] || 0) + 1;
        if (simulation.visitCounts[node.id] > 2) {
            addSystemStep(t('Simulation stopped to avoid an infinite loop.'));
            finishSimulation();
            break;
        }

        const config = node.config || {};

        if (node.type !== 'trigger' && config.active === false) {
            addSystemStep(translateFlowMessage(t, 'Skipped inactive step: :step', { step: nodeLabel(node) }));
            simulation.currentNodeId = resolveNextNodeId(node.id, 'default');
            continue;
        }

        if (isNodeBlockedByBuilderPolicy(node.type)) {
            addSystemStep(translateFlowMessage(t, 'Preview stopped because the current Flow Builder policy blocks :step.', { step: nodeLabel(node) }));
            finishSimulation();
            break;
        }

        if (enforceCustomerCareWindow.value && !simulation.context.customer_care_window_open && requiresCustomerCareWindow(node.type)) {
            addSystemStep(translateFlowMessage(t, 'Preview stopped because this step needs an open customer care window (:hours hours). Send a template message first.', {
                hours: conversationWindowHours.value,
            }));
            finishSimulation();
            break;
        }

        if (node.type === 'trigger') {
            addSystemStep(t('Flow simulation started.'));
            simulation.currentNodeId = resolveNextNodeId(node.id, 'default');
            continue;
        }

        if (node.type === 'send_text') {
            addAssistantStep(String(config.text || nodeLabel(node)));
            simulation.currentNodeId = resolveNextNodeId(node.id, 'default');
            continue;
        }

        if (node.type === 'send_media') {
            const asset = props.assets?.[String(config.asset_id || '')] || null;
            addAssistantStep(String(config.caption || nodeLabel(node)), {
                media_type: String(config.media_type || asset?.media_kind || ''),
                media_url: asset?.url || null,
                media_name: asset?.original_name || '',
            });
            simulation.currentNodeId = resolveNextNodeId(node.id, 'default');
            continue;
        }

        if (node.type === 'send_buttons') {
            const buttons = Array.isArray(config.buttons)
                ? config.buttons
                    .filter((button) => button?.id && button?.title)
                    .map((button) => ({
                        id: String(button.id),
                        title: String(button.title),
                    }))
                : [];

            addAssistantStep(String(config.body || nodeLabel(node)), {
                header: String(config.header || ''),
                footer: String(config.footer || ''),
                buttons,
            });

            if (!buttons.length) {
                simulation.currentNodeId = resolveNextNodeId(node.id, 'default');
                continue;
            }

            simulation.waiting = {
                kind: 'buttons',
                nodeId: node.id,
                options: buttons,
            };
            simulation.status = 'waiting';
            return;
        }

        if (node.type === 'send_list') {
            const sections = Array.isArray(config.sections) ? config.sections : [];
            const options = flattenListOptions(sections);
            addAssistantStep(String(config.body || nodeLabel(node)), {
                header: String(config.header || ''),
                footer: String(config.footer || ''),
                button_label: String(config.button_label || ''),
                sections,
            });

            if (!options.length) {
                simulation.currentNodeId = resolveNextNodeId(node.id, 'default');
                continue;
            }

            simulation.waiting = {
                kind: 'list',
                nodeId: node.id,
                options,
            };
            simulation.status = 'waiting';
            return;
        }

        if (node.type === 'save_reply_to_field') {
            simulation.waiting = {
                kind: 'free_text',
                nodeId: node.id,
                fieldUuid: String(config.field_uuid || ''),
                saveTarget: String(config.save_target || 'contact_field'),
                variableKey: String(config.variable_key || '').trim(),
            };
            simulation.status = 'waiting';
            return;
        }

        if (node.type === 'update_contact_field') {
            const saveTarget = String(config.save_target || 'contact_field');
            const mode = String(config.mode || config.value_mode || 'save_last_user_message');
            if (mode === 'save_last_user_message' || mode === 'last_input') {
                simulation.waiting = {
                    kind: 'free_text',
                    nodeId: node.id,
                    fieldUuid: String(config.field_uuid || ''),
                    saveTarget,
                    variableKey: String(config.variable_key || '').trim(),
                };
                simulation.status = 'waiting';
                return;
            }

            const nextValue = mode === 'session_variable'
                ? String(simulation.context.flow_variables?.[String(config.source_variable_key || '').trim()] || '')
                : String(config.value || '');

            if (saveTarget === 'session_variable' && String(config.variable_key || '').trim()) {
                simulation.context.flow_variables[String(config.variable_key || '').trim()] = nextValue;
            } else if (config.field_uuid) {
                simulation.context.contact_fields[String(config.field_uuid)] = nextValue;
            }
            addSystemStep(describeSystemNode(node, config, { nextValue }));
            simulation.currentNodeId = resolveNextNodeId(node.id, 'default');
            continue;
        }

        if (node.type === 'condition') {
            const branch = evaluateConditionBranch(config);
            markBranchCovered(node.id, branch);
            addSystemStep(translateFlowMessage(t, 'Condition branch selected: :branch', { branch: branch === 'matched' ? t('Matched') : t('Unmatched') }));
            simulation.currentNodeId = resolveNextNodeId(node.id, branch);
            continue;
        }

        if (node.type === 'human_handoff' || node.type === 'handoff_to_ai_assistant') {
            addSystemStep(describeSystemNode(node, config));
            addSystemStep(
                node.type === 'human_handoff'
                    ? t('Preview paused while customer service takes over this conversation.')
                    : t('Preview paused while the AI assistant takes over this conversation.'),
            );
            finishSimulation();
            break;
        }

        if (node.type === 'end') {
            addSystemStep(t('Preview reached the end of this journey.'));
            finishSimulation();
            break;
        }

        if (['add_to_group', 'remove_from_group', 'send_email', 'delay', 'assign_to_agent'].includes(node.type)) {
            addSystemStep(describeSystemNode(node, config));
            simulation.currentNodeId = resolveNextNodeId(node.id, 'default');
            continue;
        }

        addSystemStep(t('Simulation stopped because this step is not supported.'));
        finishSimulation();
        break;
    }

    if (safetyCounter >= 80) {
        addSystemStep(t('Simulation stopped to avoid an infinite loop.'));
        finishSimulation();
    }
};

const runSimulation = ({ countRun = true } = {}) => {
    if (!canRun.value) {
        return;
    }

    if (countRun) {
        simulation.runCount += 1;
    }
    simulation.status = 'running';
    simulation.currentNodeId = compiledGraph.value.startNodeId;
    simulation.waiting = null;
    simulation.steps = [];
    simulation.visitCounts = {};
    resetContext();
    freeTextReply.value = '';
    selectedListOptionId.value = '';
    invalidInteractiveReply.value = '';

    continueSimulation();
};

const resetSimulation = () => {
    simulation.status = 'idle';
    simulation.currentNodeId = null;
    simulation.waiting = null;
    simulation.steps = [];
    simulation.visitCounts = {};
    simulation.coveredBranches = {};
    resetContext();
    freeTextReply.value = '';
    selectedListOptionId.value = '';
    invalidInteractiveReply.value = '';
};
const syncPreviewSurface = () => {
    clearPreviewSyncTimer();

    const applySync = () => {
        resetSimulation();
        if (!projectedSteps.value.length && canRun.value) {
            runSimulation({ countRun: false });
        }
    };

    if (typeof window === 'undefined') {
        applySync();
        return;
    }

    previewSyncTimer = window.setTimeout(applySync, 220);
};

const selectButtonOption = (option) => {
    if (!simulation.waiting || simulation.waiting.kind !== 'buttons') {
        return;
    }

    simulation.context.selected_button_id = String(option.id);
    simulation.context.last_user_message = String(option.title);
    simulation.context.selected_list_row_id = '';
    simulation.context.selected_list_row_title = '';
    markBranchCovered(simulation.waiting.nodeId, String(option.id));
    addUserStep(String(option.title), { branch: option.id });

    const nodeId = simulation.waiting.nodeId;
    simulation.waiting = null;
    simulation.currentNodeId = resolveNextNodeId(nodeId, String(option.id));
    invalidInteractiveReply.value = '';
    continueSimulation();
};

const submitListOption = () => {
    if (!simulation.waiting || simulation.waiting.kind !== 'list') {
        return;
    }

    const selected = simulation.waiting.options.find((option) => option.id === selectedListOptionId.value);
    if (!selected) {
        return;
    }

    simulation.context.selected_list_row_id = String(selected.id);
    simulation.context.selected_list_row_title = String(selected.title);
    simulation.context.selected_button_id = '';
    simulation.context.last_user_message = String(selected.title);
    markBranchCovered(simulation.waiting.nodeId, String(selected.id));
    addUserStep(String(selected.title), { branch: selected.id });

    const nodeId = simulation.waiting.nodeId;
    simulation.waiting = null;
    simulation.currentNodeId = resolveNextNodeId(nodeId, String(selected.id));
    selectedListOptionId.value = '';
    invalidInteractiveReply.value = '';
    continueSimulation();
};

const submitFreeText = () => {
    const text = freeTextReply.value.trim();
    if (!simulation.waiting || simulation.waiting.kind !== 'free_text' || text === '') {
        return;
    }

    const waitingSnapshot = {
        ...simulation.waiting,
    };

    simulation.context.last_user_message = text;
    simulation.context.selected_button_id = '';
    simulation.context.selected_list_row_id = '';
    simulation.context.selected_list_row_title = '';
    if (waitingSnapshot.saveTarget === 'session_variable' && waitingSnapshot.variableKey) {
        simulation.context.flow_variables[waitingSnapshot.variableKey] = text;
    } else if (waitingSnapshot.fieldUuid) {
        simulation.context.contact_fields[waitingSnapshot.fieldUuid] = text;
    }
    addUserStep(text, { branch: 'default' });
    addSystemStep(describeStoredReply(waitingSnapshot, text));

    const nodeId = waitingSnapshot.nodeId;
    simulation.waiting = null;
    simulation.currentNodeId = resolveNextNodeId(nodeId, 'default');
    freeTextReply.value = '';
    invalidInteractiveReply.value = '';
    continueSimulation();
};

const submitInteractiveInvalidReply = () => {
    const text = invalidInteractiveReply.value.trim();
    if (!simulation.waiting || !['buttons', 'list'].includes(simulation.waiting.kind) || text === '') {
        return;
    }

    const waitingSnapshot = {
        ...simulation.waiting,
        options: Array.isArray(simulation.waiting.options) ? [...simulation.waiting.options] : [],
    };

    simulation.context.last_user_message = text;
    simulation.context.selected_button_id = '';
    simulation.context.selected_list_row_id = '';
    simulation.context.selected_list_row_title = '';
    markBranchCovered(waitingSnapshot.nodeId, 'invalid_reply');
    addUserStep(text, { branch: 'invalid_reply' });

    invalidInteractiveReply.value = '';

    const behavior = invalidReplyBehaviorForWaitingNode(waitingSnapshot.nodeId);
    if (behavior === 'repeat_prompt') {
        addSystemStep(t('Invalid reply detected. Prompt repeated according to the step settings.'));
        repeatInteractivePrompt(waitingSnapshot);
        simulation.waiting = waitingSnapshot;
        simulation.status = 'waiting';
        return;
    }

    if (behavior === 'end_run') {
        addSystemStep(t('Preview ended because this step requires tapping an interactive reply.'));
        finishSimulation();
        return;
    }

    addSystemStep(t('Invalid reply released to fallback outside this flow.'));
    finishSimulation();
};

const bubbleClass = (step) => {
    if (step.kind === 'user') {
        return 'border-emerald-200 bg-emerald-100 text-slate-900 speech-bubble-right';
    }

    if (step.kind === 'assistant') {
        return 'border-slate-200 bg-white text-slate-900 speech-bubble-left';
    }

    return 'border-slate-200 bg-slate-100 text-slate-700';
};

watch(assumeOpenCustomerCareWindow, (value) => {
    simulation.context.customer_care_window_open = value;
});

watch(
    () => branchCoverageItems.value.map((item) => item.key),
    (keys) => {
        const allowed = new Set(keys);
        simulation.coveredBranches = Object.entries(simulation.coveredBranches || {}).reduce((carry, [key, value]) => {
            if (allowed.has(key) && value) {
                carry[key] = true;
            }

            return carry;
        }, {});
    },
    { immediate: true },
);

watch(
    () => [simulation.status, simulation.runCount],
    () => {
        emit('simulation-state-change', {
            state: simulation.status,
            run_count: simulation.runCount,
        });
    },
    { immediate: true },
);

watch(previewSyncSignature, () => {
    syncPreviewSurface();
});

onMounted(() => {
    syncPreviewSurface();
});

onBeforeUnmount(() => {
    clearPreviewSyncTimer();
});
</script>
