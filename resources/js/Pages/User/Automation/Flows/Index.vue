<template>
    <SettingLayout :aimodule="true">
        <div class="mx-auto max-w-[1140px] pb-12">
            <section class="rounded-[26px] border border-[var(--ui-border)] bg-[var(--ui-surface)] px-5 py-5 shadow-[var(--ui-shadow-1)] lg:px-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div class="min-w-0">
                        <span class="inline-flex items-center rounded-full border border-[var(--ui-border)] bg-[var(--ui-surface-soft)] px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-[var(--ui-muted)]">
                            {{ $t('Automation journeys') }}
                        </span>
                        <h2 class="mt-3 max-w-3xl text-[2rem] font-semibold tracking-tight text-[var(--ui-text)]">
                            {{ $t('Build WhatsApp journeys that stay clear for your team and easy for every customer.') }}
                        </h2>
                        <p class="mt-2.5 max-w-2xl text-sm leading-6 text-[var(--ui-muted)]">
                            {{ $t('Start with a proven goal, then shape every message, branch, and handoff inside one focused studio.') }}
                        </p>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <span
                                v-for="card in summaryCards"
                                :key="card.label"
                                class="inline-flex items-center gap-2 rounded-full border border-[var(--ui-border)] bg-[var(--ui-surface-soft)] px-3 py-2 text-xs font-semibold text-[var(--ui-text)]"
                            >
                                <component :is="card.icon" class="h-3.5 w-3.5" />
                                <span>{{ card.value }}</span>
                                <span class="text-[var(--ui-muted)]">{{ card.label }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 xl:shrink-0">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-2xl px-4 py-2.5 text-sm font-semibold transition"
                            :class="builderReady ? 'bg-[var(--ui-primary)] text-white hover:opacity-90' : 'cursor-not-allowed border border-[var(--ui-border)] bg-[var(--ui-surface-soft)] text-[var(--ui-muted)]'"
                            :disabled="!builderReady"
                            @click="openCreateModal()"
                        >
                            <Plus class="h-4 w-4" />
                            {{ $t('Create journey') }}
                        </button>
                        <button
                            v-if="latestUpdatedFlow"
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-2.5 text-sm font-semibold transition"
                            :class="builderReady ? 'text-[var(--ui-text)] hover:border-[var(--ui-border-strong)] hover:text-[var(--ui-text)]' : 'cursor-not-allowed text-[var(--ui-muted)]'"
                            :disabled="!builderReady"
                            @click="openFlow(latestUpdatedFlow.uuid)"
                        >
                            <PlayCircle class="h-4 w-4" />
                            {{ $t('Continue latest journey') }}
                        </button>
                    </div>
                </div>

                <div v-if="!builderReady" class="mt-5 rounded-2xl border border-[color-mix(in_srgb,var(--ui-warning)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-warning)_10%,var(--ui-surface))] px-4 py-3 text-sm text-[var(--ui-warning)]">
                    <div class="font-semibold">{{ $t('Flow Builder is not ready yet on this environment.') }}</div>
                    <div class="mt-1 leading-6">{{ readiness.message }}</div>
                </div>
            </section>

            <section class="mt-4 grid gap-4 xl:grid-cols-[284px,minmax(0,1fr)]">
                <aside class="rounded-[24px] border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-4 shadow-[var(--ui-shadow-1)]">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[var(--ui-muted)]">{{ $t('Find and continue') }}</div>
                    <h3 class="mt-2 text-lg font-semibold text-[var(--ui-text)]">{{ $t('Open the right journey in seconds.') }}</h3>
                    <p class="mt-2 text-sm leading-6 text-[var(--ui-muted)]">
                        {{ $t('Search by name or filter by status, then jump back into the studio without scanning a crowded table.') }}
                    </p>

                    <div class="mt-5 space-y-3">
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-[var(--ui-text)]">{{ $t('Search') }}</span>
                            <div class="relative">
                                <Search class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--ui-muted)]" />
                                <input
                                    v-model="filters.search"
                                    type="text"
                                    class="w-full rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface-soft)] py-3 pe-4 ps-10 text-sm text-[var(--ui-text)] outline-none transition focus:border-[var(--ui-border-strong)] focus:bg-[var(--ui-surface)]"
                                    :placeholder="$t('Search journeys')"
                                    @input="applyFilters"
                                />
                            </div>
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-[var(--ui-text)]">{{ $t('Journey status') }}</span>
                            <select
                                v-model="filters.status"
                                class="w-full rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface-soft)] px-4 py-3 text-sm text-[var(--ui-text)] outline-none transition focus:border-[var(--ui-border-strong)] focus:bg-[var(--ui-surface)]"
                                @change="applyFilters"
                            >
                                <option value="">{{ $t('All statuses') }}</option>
                                <option value="draft">{{ $t('Draft') }}</option>
                                <option value="published">{{ $t('Published') }}</option>
                                <option value="paused">{{ $t('Paused') }}</option>
                                <option value="archived">{{ $t('Archived') }}</option>
                            </select>
                        </label>
                    </div>

                    <div class="mt-4 rounded-[20px] border border-[var(--ui-border)] bg-[color-mix(in_srgb,var(--ui-surface-soft)_80%,transparent)] p-3.5">
                        <div class="text-xs font-semibold text-[var(--ui-muted)]">{{ $t('Last edited') }}</div>
                        <div v-if="latestUpdatedFlow" class="mt-3">
                            <div class="line-clamp-1 text-sm font-semibold text-[var(--ui-text)]">{{ latestUpdatedFlow.name }}</div>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <AutomationStatusBadge :status="latestUpdatedFlow.status" />
                                <span v-if="latestUpdatedFlow.goal_preset" class="inline-flex rounded-full border border-[var(--ui-border)] bg-[var(--ui-surface)] px-3 py-1 text-xs font-medium text-[var(--ui-muted)]">
                                    {{ goalPresetLabel(latestUpdatedFlow.goal_preset) }}
                                </span>
                            </div>
                            <div class="mt-3 text-xs leading-5 text-[var(--ui-muted)]">{{ formatDate(latestUpdatedFlow.updated_at) }}</div>
                            <button
                                type="button"
                                class="mt-4 inline-flex items-center gap-2 rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-2.5 text-sm font-semibold text-[var(--ui-text)] transition hover:border-[var(--ui-border-strong)] hover:text-[var(--ui-text)]"
                                :disabled="!builderReady"
                                :class="builderReady ? '' : 'cursor-not-allowed text-[var(--ui-muted)]'"
                                @click="openFlow(latestUpdatedFlow.uuid)"
                            >
                                <PlayCircle class="h-4 w-4" />
                                {{ $t('Open studio') }}
                            </button>
                        </div>
                        <div v-else class="mt-3 text-sm leading-6 text-[var(--ui-muted)]">
                            {{ $t('No automation journeys yet. Create your first ready-to-edit WhatsApp journey to start.') }}
                        </div>
                    </div>
                </aside>

                <div class="rounded-[24px] border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-4 shadow-[var(--ui-shadow-1)]">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[var(--ui-muted)]">{{ $t('Start with a proven goal') }}</div>
                            <h3 class="mt-2 text-lg font-semibold text-[var(--ui-text)]">{{ $t('Choose the closest journey, then customize every step.') }}</h3>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-[var(--ui-muted)]">
                                {{ $t('These starters are ready to edit, shorten, and adapt inside the studio.') }}
                            </p>
                        </div>
                        <div class="inline-flex items-center rounded-full border border-[var(--ui-border)] bg-[var(--ui-surface-soft)] px-3 py-1 text-xs font-semibold text-[var(--ui-muted)]">
                            {{ goalPresetCards.length }} {{ $t('Templates') }}
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <button
                            v-for="option in goalPresetCards"
                            :key="option.value"
                            type="button"
                            class="group relative overflow-hidden rounded-[20px] border border-[var(--ui-border)] bg-[color-mix(in_srgb,var(--ui-surface-soft)_70%,transparent)] p-3.5 text-start transition hover:border-[var(--ui-border-strong)] hover:bg-[var(--ui-surface)]"
                            :disabled="!builderReady"
                            :class="builderReady ? '' : 'cursor-not-allowed opacity-70'"
                            @click="openCreateModal(option.value)"
                        >
                            <div class="absolute inset-x-0 top-0 h-1.5" :class="option.barClass"></div>
                            <div class="flex items-center justify-between gap-3">
                                <div class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border" :class="option.iconClass">
                                    <component :is="option.icon" class="h-4 w-4" />
                                </div>
                                <span class="inline-flex items-center rounded-full border border-[var(--ui-border)] bg-[var(--ui-surface)] px-2.5 py-1 text-[11px] font-semibold text-[var(--ui-muted)]">
                                    {{ $t('Ready to edit') }}
                                </span>
                            </div>

                            <div class="mt-3 text-base font-semibold text-[var(--ui-text)]">{{ option.label }}</div>
                            <div class="mt-1.5 line-clamp-2 text-sm leading-6 text-[var(--ui-muted)]">{{ option.description }}</div>
                            <div class="mt-3 rounded-2xl border border-[var(--ui-border)] bg-[color-mix(in_srgb,var(--ui-surface)_90%,transparent)] px-3 py-2 text-xs leading-5 text-[var(--ui-muted)]">
                                {{ option.placeholder }}
                            </div>

                            <div class="mt-2.5 inline-flex items-center gap-2 text-sm font-semibold" :class="option.textClass">
                                <span>{{ $t('Start with this goal') }}</span>
                                <ArrowUpRight class="h-4 w-4 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                            </div>
                        </button>
                    </div>
                </div>
            </section>

            <section class="mt-4 rounded-[24px] border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-4 shadow-[var(--ui-shadow-1)]">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[var(--ui-muted)]">{{ $t('Recent journeys') }}</div>
                        <h3 class="mt-2 text-lg font-semibold text-[var(--ui-text)]">{{ $t('Open, duplicate, or clean up any journey from one calm view.') }}</h3>
                    </div>
                    <div class="inline-flex items-center rounded-full border border-[var(--ui-border)] bg-[var(--ui-surface-soft)] px-3 py-1 text-xs font-semibold text-[var(--ui-muted)]">
                        {{ totalFlows }} {{ $t('visible') }}
                    </div>
                </div>

                <div v-if="visibleRows.length" class="mt-4 space-y-3">
                    <article
                        v-for="row in visibleRows"
                        :key="row.uuid"
                        class="rounded-[20px] border border-[var(--ui-border)] bg-[color-mix(in_srgb,var(--ui-surface-soft)_55%,transparent)] px-3.5 py-3.5 transition hover:border-[var(--ui-border-strong)] hover:bg-[var(--ui-surface)]"
                    >
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <AutomationStatusBadge :status="row.status" />
                                    <span v-if="row.goal_preset" class="inline-flex rounded-full border border-[var(--ui-border)] bg-[var(--ui-surface)] px-3 py-1 text-xs font-medium text-[var(--ui-muted)]">
                                        {{ goalPresetLabel(row.goal_preset) }}
                                    </span>
                                    <span v-if="row.has_unpublished_changes" class="inline-flex rounded-full border border-[color-mix(in_srgb,var(--ui-warning)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-warning)_12%,var(--ui-surface))] px-3 py-1 text-xs font-semibold text-[var(--ui-warning)]">
                                        {{ $t('Unpublished draft') }}
                                    </span>
                                </div>

                                <div class="mt-2.5 line-clamp-1 text-base font-semibold text-[var(--ui-text)]">{{ row.name }}</div>
                                <div class="mt-1.5 line-clamp-2 text-sm leading-6 text-[var(--ui-muted)]">
                                    {{ row.description || $t('Ready to edit inside the studio.') }}
                                </div>

                                <div class="mt-2.5 flex flex-wrap items-center gap-3 text-xs text-[var(--ui-muted)]">
                                    <span>{{ $t('Runs') }}: {{ row.runs_count }}</span>
                                    <span>{{ formatDate(row.updated_at) }}</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 lg:shrink-0">
                                <FlowListRowMenu
                                    v-if="builderReady"
                                    @open="openFlow(row.uuid)"
                                    @duplicate="duplicateFlow(row.uuid)"
                                    @delete="deleteFlow(row)"
                                />
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-2xl px-4 py-2.5 text-sm font-semibold transition"
                                    :class="builderReady ? 'bg-[var(--ui-primary)] text-white hover:opacity-90' : 'cursor-not-allowed border border-[var(--ui-border)] bg-[var(--ui-surface-soft)] text-[var(--ui-muted)]'"
                                    :disabled="!builderReady"
                                    @click="openFlow(row.uuid)"
                                >
                                    <PlayCircle class="h-4 w-4" />
                                    {{ $t('Open studio') }}
                                </button>
                            </div>
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="mt-5 flex min-h-[220px] items-center justify-center rounded-[24px] border border-dashed border-[var(--ui-border-strong)] bg-[color-mix(in_srgb,var(--ui-surface-soft)_50%,transparent)] px-6 text-center"
                >
                    <div class="max-w-md">
                        <div class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] text-[var(--ui-muted)]">
                            <Workflow class="h-5 w-5" />
                        </div>
                        <div class="mt-4 text-lg font-semibold text-[var(--ui-text)]">{{ $t('No automation journeys yet. Create your first ready-to-edit WhatsApp journey to start.') }}</div>
                        <p class="mt-2 text-sm leading-6 text-[var(--ui-muted)]">
                            {{ $t('Choose a goal above and the studio will prepare the first structure for you.') }}
                        </p>
                    </div>
                </div>

                <Pagination :pagination="props.rows" />
            </section>
        </div>

        <FlowCreateModal
            :loading="createLoading"
            :open="createModalOpen"
            :initial-goal-preset="selectedGoalPreset"
            @update:loading="createLoading = $event"
            @close="createModalOpen = false"
            @created="handleCreated"
        />
        <FlowDangerConfirmModal
            :open="deleteConfirmOpen"
            :busy="deleteBusy"
            :title="$t('Delete automation?')"
            :message="$t('This automation will be removed permanently from your workspace. Published versions and drafts cannot be restored after deletion.')"
            :subject="pendingDeleteName || $t('Automation')"
            :note="$t('This action cannot be undone.')"
            :confirm-label="$t('Delete automation')"
            :cancel-label="$t('Keep automation')"
            :busy-label="$t('Deleting...')"
            @close="closeDeleteConfirm"
            @confirm="confirmDeleteFlow"
        />
    </SettingLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import {
    ArrowUpRight,
    Bot,
    Building2,
    CalendarDays,
    Clock3,
    PlayCircle,
    Plus,
    Search,
    Sparkles,
    Workflow,
} from 'lucide-vue-next';
import SettingLayout from './../Layout.vue';
import Pagination from '@/Components/Pagination.vue';
import FlowDangerConfirmModal from '@/Components/AutomationFlows/FlowDangerConfirmModal.vue';
import FlowCreateModal from '@/Components/AutomationFlows/FlowCreateModal.vue';
import FlowListRowMenu from '@/Components/AutomationFlows/FlowListRowMenu.vue';
import AutomationStatusBadge from '@/Components/AutomationFlows/Ui/AutomationStatusBadge.vue';
import { resolveFlowBuilderDestination, FLOW_INDEX_PATH } from '@/Components/AutomationFlows/flowBuilderRouting.js';
import {
    flowGoalPresetDescription,
    flowGoalPresetLabel,
    flowGoalPresetOptions,
    flowGoalPresetPlaceholder,
} from '@/Components/AutomationFlows/flowBuilderGoalPresets.js';

const { t } = useI18n();

const props = defineProps({
    rows: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    flowBuilderReadiness: {
        type: Object,
        default: () => ({}),
    },
    statusCounts: {
        type: Object,
        default: () => ({}),
    },
});

const goalPresetVisuals = {
    sales_qualification: {
        icon: Sparkles,
        barClass: 'bg-[var(--ui-secondary)]',
        iconClass: 'border-[color-mix(in_srgb,var(--ui-secondary)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-secondary)_12%,var(--ui-surface))] text-[var(--ui-secondary)]',
        textClass: 'text-[var(--ui-secondary)]',
    },
    support_routing: {
        icon: Bot,
        barClass: 'bg-[var(--flow-tone-branch)]',
        iconClass: 'border-[color-mix(in_srgb,var(--flow-tone-branch)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--flow-tone-branch)_12%,var(--ui-surface))] text-[var(--flow-tone-branch)]',
        textClass: 'text-[var(--flow-tone-branch)]',
    },
    appointment_booking: {
        icon: CalendarDays,
        barClass: 'bg-[var(--ui-success)]',
        iconClass: 'border-[color-mix(in_srgb,var(--ui-success)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-success)_12%,var(--ui-surface))] text-[var(--ui-success)]',
        textClass: 'text-[var(--ui-success)]',
    },
    seller_intake: {
        icon: Building2,
        barClass: 'bg-[var(--ui-warning)]',
        iconClass: 'border-[color-mix(in_srgb,var(--ui-warning)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-warning)_12%,var(--ui-surface))] text-[var(--ui-warning)]',
        textClass: 'text-[var(--ui-warning)]',
    },
};

const createModalOpen = ref(false);
const createLoading = ref(false);
const deleteConfirmOpen = ref(false);
const deleteBusy = ref(false);
const pendingDeleteUuid = ref('');
const pendingDeleteName = ref('');
const selectedGoalPreset = ref('sales_qualification');
const filters = reactive({
    search: props.filters?.search || '',
    status: props.filters?.status || '',
});

let searchTimeout = null;
const readiness = computed(() => props.flowBuilderReadiness || {});
const builderReady = computed(() => readiness.value?.builder_ready !== false);
const visibleRows = computed(() => Array.isArray(props.rows?.data) ? props.rows.data : []);
const totalFlows = computed(() => Number(props.rows?.total ?? visibleRows.value.length ?? 0));
const latestUpdatedFlow = computed(() => visibleRows.value[0] || null);
const publishedFlowsCount = computed(() => Number(props.statusCounts?.published ?? 0));
const draftFlowsCount = computed(() => Number(props.statusCounts?.draft ?? 0));
const summaryCards = computed(() => ([
    {
        label: t('Visible journeys'),
        value: totalFlows.value,
        help: t('Journeys currently visible in this workspace.'),
        icon: Workflow,
        iconClass: 'border-[color-mix(in_srgb,var(--ui-secondary)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-secondary)_12%,var(--ui-surface))] text-[var(--ui-secondary)]',
    },
    {
        label: t('Published'),
        value: publishedFlowsCount.value,
        help: t('Journeys already live for customers.'),
        icon: Sparkles,
        iconClass: 'border-[color-mix(in_srgb,var(--ui-success)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-success)_12%,var(--ui-surface))] text-[var(--ui-success)]',
    },
    {
        label: t('Drafts to finish'),
        value: draftFlowsCount.value,
        help: t('Journeys still waiting for the final editing pass.'),
        icon: Clock3,
        iconClass: 'border-[color-mix(in_srgb,var(--ui-warning)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-warning)_12%,var(--ui-surface))] text-[var(--ui-warning)]',
    },
]));
const goalPresetCards = computed(() => flowGoalPresetOptions(t).map((option) => ({
    ...option,
    description: flowGoalPresetDescription(option.value, t),
    placeholder: flowGoalPresetPlaceholder(option.value, t),
    ...(goalPresetVisuals[option.value] || {}),
})));

const openCreateModal = (preset = 'sales_qualification') => {
    if (!builderReady.value) {
        return;
    }

    selectedGoalPreset.value = preset;
    createModalOpen.value = true;
};

const applyFilters = () => {
    window.clearTimeout(searchTimeout);
    searchTimeout = window.setTimeout(() => {
        router.get(FLOW_INDEX_PATH, filters, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 250);
};

const handleCreated = (payload) => {
    createModalOpen.value = false;
    router.visit(resolveFlowBuilderDestination(payload));
};

const openFlow = (uuid) => {
    if (!builderReady.value) {
        return;
    }

    router.visit(`${FLOW_INDEX_PATH}/${uuid}`);
};

const duplicateFlow = async (uuid) => {
    const response = await axios.post(`${FLOW_INDEX_PATH}/${uuid}/duplicate`);
    router.visit(resolveFlowBuilderDestination(response.data));
};

const deleteFlow = (row = null) => {
    pendingDeleteUuid.value = row?.uuid || '';
    pendingDeleteName.value = row?.name || '';
    deleteConfirmOpen.value = true;
};

const closeDeleteConfirm = () => {
    if (deleteBusy.value) {
        return;
    }

    deleteConfirmOpen.value = false;
    pendingDeleteUuid.value = '';
    pendingDeleteName.value = '';
};

const confirmDeleteFlow = async () => {
    if (deleteBusy.value || !pendingDeleteUuid.value) {
        return;
    }

    deleteBusy.value = true;
    try {
        await axios.delete(`${FLOW_INDEX_PATH}/${pendingDeleteUuid.value}`);
        deleteBusy.value = false;
        closeDeleteConfirm();
        router.reload({ only: ['rows'] });
    } catch {
        deleteBusy.value = false;
    }
};

const goalPresetLabel = (value) => flowGoalPresetLabel(value, t);

const formatDate = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleString();
};
</script>
