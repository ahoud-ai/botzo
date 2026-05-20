<template>
    <SettingLayout :aimodule="true">
        <div class="mx-auto max-w-[1140px] pb-12">
            <section class="rounded-[26px] border border-slate-200 bg-white px-5 py-5 shadow-sm lg:px-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div class="min-w-0">
                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-600">
                            {{ $t('Automation journeys') }}
                        </span>
                        <h2 class="mt-3 max-w-3xl text-[2rem] font-semibold tracking-tight text-slate-950">
                            {{ $t('Build WhatsApp journeys that stay clear for your team and easy for every customer.') }}
                        </h2>
                        <p class="mt-2.5 max-w-2xl text-sm leading-6 text-slate-600">
                            {{ $t('Start with a proven goal, then shape every message, branch, and handoff inside one focused studio.') }}
                        </p>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <span
                                v-for="card in summaryCards"
                                :key="card.label"
                                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700"
                            >
                                <component :is="card.icon" class="h-3.5 w-3.5" />
                                <span>{{ card.value }}</span>
                                <span class="text-slate-500">{{ card.label }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 xl:shrink-0">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-2xl px-4 py-2.5 text-sm font-semibold transition"
                            :class="builderReady ? 'bg-slate-950 text-white hover:opacity-90' : 'cursor-not-allowed border border-slate-200 bg-slate-100 text-slate-400'"
                            :disabled="!builderReady"
                            @click="openCreateModal()"
                        >
                            <Plus class="h-4 w-4" />
                            {{ $t('Create journey') }}
                        </button>
                        <button
                            v-if="latestUpdatedFlow"
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold transition"
                            :class="builderReady ? 'text-slate-700 hover:border-slate-300 hover:text-slate-950' : 'cursor-not-allowed text-slate-400'"
                            :disabled="!builderReady"
                            @click="openFlow(latestUpdatedFlow.uuid)"
                        >
                            <PlayCircle class="h-4 w-4" />
                            {{ $t('Continue latest journey') }}
                        </button>
                    </div>
                </div>

                <div v-if="!builderReady" class="mt-5 rounded-2xl border border-amber-200 bg-amber-50/90 px-4 py-3 text-sm text-amber-900">
                    <div class="font-semibold">{{ $t('Flow Builder is not ready yet on this environment.') }}</div>
                    <div class="mt-1 leading-6">{{ readiness.message }}</div>
                </div>
            </section>

            <section class="mt-4 grid gap-4 xl:grid-cols-[284px,minmax(0,1fr)]">
                <aside class="rounded-[24px] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $t('Find and continue') }}</div>
                    <h3 class="mt-2 text-lg font-semibold text-slate-950">{{ $t('Open the right journey in seconds.') }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        {{ $t('Search by name or filter by status, then jump back into the studio without scanning a crowded table.') }}
                    </p>

                    <div class="mt-5 space-y-3">
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-slate-700">{{ $t('Search') }}</span>
                            <div class="relative">
                                <Search class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    v-model="filters.search"
                                    type="text"
                                    class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-3 pe-4 ps-10 text-sm text-slate-700 outline-none transition focus:border-slate-300 focus:bg-white"
                                    :placeholder="$t('Search journeys')"
                                    @input="applyFilters"
                                />
                            </div>
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-slate-700">{{ $t('Journey status') }}</span>
                            <select
                                v-model="filters.status"
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-300 focus:bg-white"
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

                    <div class="mt-4 rounded-[20px] border border-slate-200 bg-slate-50/80 p-3.5">
                        <div class="text-xs font-semibold text-slate-500">{{ $t('Last edited') }}</div>
                        <div v-if="latestUpdatedFlow" class="mt-3">
                            <div class="line-clamp-1 text-sm font-semibold text-slate-950">{{ latestUpdatedFlow.name }}</div>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass(latestUpdatedFlow.status)">
                                    {{ labelStatus(latestUpdatedFlow.status) }}
                                </span>
                                <span v-if="latestUpdatedFlow.goal_preset" class="inline-flex rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600">
                                    {{ goalPresetLabel(latestUpdatedFlow.goal_preset) }}
                                </span>
                            </div>
                            <div class="mt-3 text-xs leading-5 text-slate-500">{{ formatDate(latestUpdatedFlow.updated_at) }}</div>
                            <button
                                type="button"
                                class="mt-4 inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-950"
                                :disabled="!builderReady"
                                :class="builderReady ? '' : 'cursor-not-allowed text-slate-400'"
                                @click="openFlow(latestUpdatedFlow.uuid)"
                            >
                                <PlayCircle class="h-4 w-4" />
                                {{ $t('Open studio') }}
                            </button>
                        </div>
                        <div v-else class="mt-3 text-sm leading-6 text-slate-500">
                            {{ $t('No automation journeys yet. Create your first ready-to-edit WhatsApp journey to start.') }}
                        </div>
                    </div>
                </aside>

                <div class="rounded-[24px] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $t('Start with a proven goal') }}</div>
                            <h3 class="mt-2 text-lg font-semibold text-slate-950">{{ $t('Choose the closest journey, then customize every step.') }}</h3>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                                {{ $t('These starters are ready to edit, shorten, and adapt inside the studio.') }}
                            </p>
                        </div>
                        <div class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                            {{ goalPresetCards.length }} {{ $t('Templates') }}
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <button
                            v-for="option in goalPresetCards"
                            :key="option.value"
                            type="button"
                            class="group relative overflow-hidden rounded-[20px] border border-slate-200 bg-slate-50/70 p-3.5 text-start transition hover:border-slate-300 hover:bg-white"
                            :disabled="!builderReady"
                            :class="builderReady ? '' : 'cursor-not-allowed opacity-70'"
                            @click="openCreateModal(option.value)"
                        >
                            <div class="absolute inset-x-0 top-0 h-1.5" :class="option.barClass"></div>
                            <div class="flex items-center justify-between gap-3">
                                <div class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border" :class="option.iconClass">
                                    <component :is="option.icon" class="h-4 w-4" />
                                </div>
                                <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-500">
                                    {{ $t('Ready to edit') }}
                                </span>
                            </div>

                            <div class="mt-3 text-base font-semibold text-slate-950">{{ option.label }}</div>
                            <div class="mt-1.5 line-clamp-2 text-sm leading-6 text-slate-500">{{ option.description }}</div>
                            <div class="mt-3 rounded-2xl border border-slate-200 bg-white/90 px-3 py-2 text-xs leading-5 text-slate-600">
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

            <section class="mt-4 rounded-[24px] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $t('Recent journeys') }}</div>
                        <h3 class="mt-2 text-lg font-semibold text-slate-950">{{ $t('Open, duplicate, or clean up any journey from one calm view.') }}</h3>
                    </div>
                    <div class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                        {{ visibleRows.length }} {{ $t('visible') }}
                    </div>
                </div>

                <div v-if="visibleRows.length" class="mt-4 space-y-3">
                    <article
                        v-for="row in visibleRows"
                        :key="row.uuid"
                        class="rounded-[20px] border border-slate-200 bg-slate-50/55 px-3.5 py-3.5 transition hover:border-slate-300 hover:bg-white"
                    >
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass(row.status)">
                                        {{ labelStatus(row.status) }}
                                    </span>
                                    <span v-if="row.goal_preset" class="inline-flex rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600">
                                        {{ goalPresetLabel(row.goal_preset) }}
                                    </span>
                                    <span v-if="row.has_unpublished_changes" class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">
                                        {{ $t('Unpublished draft') }}
                                    </span>
                                </div>

                                <div class="mt-2.5 line-clamp-1 text-base font-semibold text-slate-950">{{ row.name }}</div>
                                <div class="mt-1.5 line-clamp-2 text-sm leading-6 text-slate-500">
                                    {{ row.description || $t('Ready to edit inside the studio.') }}
                                </div>

                                <div class="mt-2.5 flex flex-wrap items-center gap-3 text-xs text-slate-500">
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
                                    :class="builderReady ? 'bg-slate-950 text-white hover:opacity-90' : 'cursor-not-allowed border border-slate-200 bg-slate-100 text-slate-400'"
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
                    class="mt-5 flex min-h-[220px] items-center justify-center rounded-[24px] border border-dashed border-slate-300 bg-slate-50/50 px-6 text-center"
                >
                    <div class="max-w-md">
                        <div class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600">
                            <Workflow class="h-5 w-5" />
                        </div>
                        <div class="mt-4 text-lg font-semibold text-slate-950">{{ $t('No automation journeys yet. Create your first ready-to-edit WhatsApp journey to start.') }}</div>
                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            {{ $t('Choose a goal above and the studio will prepare the first structure for you.') }}
                        </p>
                    </div>
                </div>
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
import FlowDangerConfirmModal from '@/Components/AutomationFlows/FlowDangerConfirmModal.vue';
import FlowCreateModal from '@/Components/AutomationFlows/FlowCreateModal.vue';
import FlowListRowMenu from '@/Components/AutomationFlows/FlowListRowMenu.vue';
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
});

const goalPresetVisuals = {
    sales_qualification: {
        icon: Sparkles,
        barClass: 'bg-sky-400',
        iconClass: 'border-sky-200 bg-sky-50 text-sky-700',
        textClass: 'text-sky-700',
    },
    support_routing: {
        icon: Bot,
        barClass: 'bg-violet-400',
        iconClass: 'border-violet-200 bg-violet-50 text-violet-700',
        textClass: 'text-violet-700',
    },
    appointment_booking: {
        icon: CalendarDays,
        barClass: 'bg-emerald-400',
        iconClass: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        textClass: 'text-emerald-700',
    },
    seller_intake: {
        icon: Building2,
        barClass: 'bg-amber-400',
        iconClass: 'border-amber-200 bg-amber-50 text-amber-700',
        textClass: 'text-amber-700',
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
const publishedFlowsCount = computed(() => visibleRows.value.filter((row) => row?.status === 'published').length);
const draftFlowsCount = computed(() => visibleRows.value.filter((row) => row?.status === 'draft').length);
const summaryCards = computed(() => ([
    {
        label: t('Visible journeys'),
        value: totalFlows.value,
        help: t('Journeys currently visible in this workspace.'),
        icon: Workflow,
        iconClass: 'border-sky-200 bg-sky-50 text-sky-700',
    },
    {
        label: t('Published'),
        value: publishedFlowsCount.value,
        help: t('Journeys already live for customers.'),
        icon: Sparkles,
        iconClass: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    },
    {
        label: t('Drafts to finish'),
        value: draftFlowsCount.value,
        help: t('Journeys still waiting for the final editing pass.'),
        icon: Clock3,
        iconClass: 'border-amber-200 bg-amber-50 text-amber-700',
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

const labelStatus = (status) => ({
    draft: t('Draft'),
    published: t('Published'),
    paused: t('Paused'),
    archived: t('Archived'),
}[status] || status);

const goalPresetLabel = (value) => flowGoalPresetLabel(value, t);

const statusClass = (status) => ({
    draft: 'border border-amber-200 bg-amber-50 text-amber-700',
    published: 'border border-emerald-200 bg-emerald-50 text-emerald-700',
    paused: 'border border-slate-200 bg-slate-100 text-slate-700',
    archived: 'border border-rose-200 bg-rose-50 text-rose-700',
}[status] || 'border border-slate-200 bg-slate-100 text-slate-700');

const formatDate = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleString();
};
</script>
