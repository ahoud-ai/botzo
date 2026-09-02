<template>
    <section class="relative">
        <div class="rounded-[24px] border border-[color-mix(in_srgb,var(--ui-border)_90%,transparent)] bg-[var(--flow-panel-bg)] px-4 py-3.5 shadow-[var(--ui-shadow-1)] backdrop-blur">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full border border-[var(--ui-border)] bg-[var(--ui-surface-soft)] px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-[var(--ui-muted)]">
                            {{ $t('Launch checklist') }}
                        </span>
                        <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold" :class="statusPillClass">
                            {{ statusLabel }}
                        </span>
                    </div>

                    <div class="mt-3">
                        <p class="min-w-0 line-clamp-2 text-sm font-semibold leading-6 text-[var(--ui-text)]">
                            {{ launchPanelMessage }}
                        </p>
                        <p class="mt-1 text-xs leading-5 text-[var(--ui-muted)]">
                            {{ launchSubtitle }}
                        </p>
                    </div>

                    <div v-if="collapsedBlockingNodeCards.length" class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="node in collapsedBlockingNodeCards"
                            :key="node.id"
                            type="button"
                            class="inline-flex max-w-full items-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-semibold transition"
                            :class="node.id === selectedNodeId ? 'border-[color-mix(in_srgb,var(--ui-danger)_45%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-danger)_12%,var(--ui-surface))] text-[var(--ui-danger)]' : 'border-[var(--ui-border)] bg-[var(--ui-surface)] text-[var(--ui-text)] hover:border-[var(--ui-border-strong)]'"
                            @click="$emit('focus-node', node.id)"
                        >
                            <span class="truncate max-w-[180px]">{{ node.title }}</span>
                            <span class="rounded-full border border-[color-mix(in_srgb,var(--ui-danger)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-danger)_12%,var(--ui-surface))] px-1.5 py-0.5 text-[10px] text-[var(--ui-danger)]">
                                {{ node.errorCount }}
                            </span>
                        </button>
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-[var(--ui-border)] bg-[var(--ui-surface)] px-3 py-2 text-xs font-semibold text-[var(--ui-text)] transition hover:border-[var(--ui-border-strong)] hover:text-[var(--ui-text)]"
                    @click="$emit('toggle')"
                >
                    <span>{{ collapsed ? $t('Show details') : $t('Hide details') }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" class="transition-transform duration-150" :class="collapsed ? 'rotate-180' : ''">
                        <path d="m6 9l6 6l6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
            </div>
        </div>

        <Transition
            enter-active-class="transition duration-180 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-2 opacity-0"
        >
            <div v-if="!collapsed" class="absolute inset-x-0 top-full z-30 mt-3">
                <div class="overflow-hidden rounded-[28px] border border-[var(--ui-border)] bg-[var(--ui-surface)] shadow-[0_24px_64px_color-mix(in_srgb,var(--ui-text)_16%,transparent)]">
                    <div class="max-h-[min(70vh,38rem)] overflow-y-auto px-4 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[var(--ui-muted)]">{{ $t('Launch checklist') }}</div>
                                <div class="mt-1 text-lg font-semibold text-[var(--ui-text)]">{{ launchTitle }}</div>
                                <div class="mt-1 text-sm leading-6 text-[var(--ui-muted)]">{{ launchSubtitle }}</div>
                            </div>
                            <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold" :class="statusPillClass">
                                {{ statusLabel }}
                            </span>
                        </div>

                        <div class="mt-4 grid gap-3 md:grid-cols-3">
                            <div v-for="metric in metricCards" :key="metric.label" class="rounded-[20px] border border-[var(--ui-border)] bg-[color-mix(in_srgb,var(--ui-surface-soft)_85%,transparent)] px-4 py-3.5">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[var(--ui-muted)]">{{ metric.label }}</div>
                                <div class="mt-2 text-2xl font-semibold text-[var(--ui-text)]">{{ metric.value }}</div>
                                <div class="mt-1 text-xs leading-5 text-[var(--ui-muted)]">{{ metric.help }}</div>
                            </div>
                        </div>

                        <div class="mt-4 rounded-[22px] border px-4 py-4" :class="launchPanelClass">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] opacity-75">{{ $t('Focus now') }}</div>
                                    <div class="mt-2 text-sm font-semibold leading-6">{{ launchPanelMessage }}</div>
                                </div>
                            </div>
                        </div>

                        <div v-if="visibleSuggestions.length" class="mt-4 flex flex-wrap gap-2">
                            <span
                                v-for="(suggestion, index) in visibleSuggestions"
                                :key="`${suggestion}-${index}`"
                                class="inline-flex items-center rounded-full border border-[var(--ui-border)] bg-[var(--ui-surface)] px-3 py-1.5 text-[11px] font-medium text-[var(--ui-muted)]"
                            >
                                {{ suggestion }}
                            </span>
                        </div>

                        <div v-if="blockingNodeCards.length" class="mt-4">
                            <div class="mb-2 text-[11px] font-semibold uppercase tracking-[0.16em] text-[var(--ui-muted)]">{{ $t('Publish blockers') }}</div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="node in blockingNodeCards"
                                    :key="node.id"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-semibold transition"
                                    :class="node.id === selectedNodeId ? 'border-[color-mix(in_srgb,var(--ui-danger)_45%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-danger)_12%,var(--ui-surface))] text-[var(--ui-danger)]' : 'border-[var(--ui-border)] bg-[var(--ui-surface)] text-[var(--ui-text)] hover:border-[var(--ui-border-strong)]'"
                                    @click="$emit('focus-node', node.id)"
                                >
                                    <span class="truncate">{{ node.title }}</span>
                                    <span class="rounded-full border border-[color-mix(in_srgb,var(--ui-danger)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-danger)_12%,var(--ui-surface))] px-1.5 py-0.5 text-[10px] text-[var(--ui-danger)]">
                                        {{ node.errorCount }}
                                    </span>
                                </button>
                            </div>
                        </div>

                        <div v-if="visibleBlockers.length" class="mt-4 rounded-[22px] border border-[color-mix(in_srgb,var(--ui-danger)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-danger)_10%,var(--ui-surface))] p-4">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[var(--ui-danger)]">{{ $t('What still blocks publishing') }}</div>
                            <ul class="mt-3 space-y-2 text-sm text-[var(--ui-danger)]">
                                <li v-for="(blocker, index) in visibleBlockers" :key="`${blocker}-${index}`" class="flex items-start gap-2">
                                    <span class="mt-2 h-1.5 w-1.5 rounded-full bg-[var(--ui-danger)]"></span>
                                    <span>{{ blocker }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { translateFlowMessage } from '@/Components/AutomationFlows/flowBuilderCopy.js';

defineEmits(['toggle', 'focus-node']);

const props = defineProps({
    insights: {
        type: Object,
        required: true,
    },
    planLimits: {
        type: Object,
        default: () => ({}),
    },
    collapsed: {
        type: Boolean,
        default: true,
    },
    blockingNodes: {
        type: Array,
        default: () => [],
    },
    selectedNodeId: {
        type: String,
        default: '',
    },
});

const { t } = useI18n();

const blockerCount = computed(() => props.insights?.blockers?.length ?? 0);
const statusLabel = computed(() => (
    blockerCount.value
        ? translateFlowMessage(t, ':count blocker(s)', { count: blockerCount.value })
        : t('Ready for launch')
));
const statusPillClass = computed(() => (
    blockerCount.value
        ? 'border-[color-mix(in_srgb,var(--ui-danger)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-danger)_12%,var(--ui-surface))] text-[var(--ui-danger)]'
        : 'border-[color-mix(in_srgb,var(--ui-success)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-success)_12%,var(--ui-surface))] text-[var(--ui-success)]'
));
const launchTitle = computed(() => (
    blockerCount.value
        ? t('Fix the final blockers before launch')
        : t('This flow is ready for the final launch pass')
));
const launchSubtitle = computed(() => (
    blockerCount.value
        ? t('Finish the remaining red issues, then run one last customer preview before publishing.')
        : t('Review the final customer path, then publish when the team is ready.')
));
const launchPanelMessage = computed(() => props.insights?.primaryAction || t('Run one quick preview, then publish when ready.'));
const launchPanelClass = computed(() => (
    blockerCount.value
        ? 'border-[color-mix(in_srgb,var(--ui-danger)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-danger)_10%,var(--ui-surface))] text-[var(--ui-danger)]'
        : 'border-[color-mix(in_srgb,var(--ui-success)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-success)_10%,var(--ui-surface))] text-[var(--ui-success)]'
));
const metricCards = computed(() => ([
    {
        label: t('Steps'),
        value: props.insights?.nodeCount ?? 0,
        help: t('Total steps currently inside this journey.'),
    },
    {
        label: t('Customer choices'),
        value: props.insights?.totalChoices ?? 0,
        help: t('Interactive paths the customer can actively choose from.'),
    },
    {
        label: t('Warnings'),
        value: props.insights?.warnings?.length ?? 0,
        help: t('Non-blocking issues still worth reviewing before launch.'),
    },
]));
const blockingNodeCards = computed(() => (
    Array.isArray(props.blockingNodes)
        ? props.blockingNodes.slice(0, 4)
        : []
));
const collapsedBlockingNodeCards = computed(() => blockingNodeCards.value.slice(0, 2));
const visibleBlockers = computed(() => (
    Array.isArray(props.insights?.blockers)
        ? props.insights.blockers.slice(0, 5)
        : []
));
const visibleSuggestions = computed(() => (
    Array.isArray(props.insights?.suggestions)
        ? props.insights.suggestions.slice(0, 4)
        : []
));
</script>
