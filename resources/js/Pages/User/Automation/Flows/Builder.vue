<template>
    <SettingLayout :aimodule="true" studio-mode :board-only="isBoardOnlyWindow">
        <div :class="[isBoardOnlyWindow ? 'pb-0' : 'pb-10', uiEnhancementsEnabled ? 'px-4 xl:px-6' : '']">
            <div class="lg:sticky lg:top-4 lg:z-20 lg:mb-3 lg:backdrop-blur-sm">
                <FlowBuilderHeaderCard
                    :draft="draft"
                    :save-state-label="saveStateLabel"
                    :validation-errors-count="validation.errors.length"
                    :more-open="moreOpen"
                    :show-open-studio-action="!isBoardOnlyWindow"
                    @update:more-open="moreOpen = $event"
                    @go-back="goBack"
                    @save="saveDraft()"
                    @open-preview="openPreviewModal"
                    @publish="publishFlow"
                    @open-meta-editor="openMetaEditor"
                    @open-studio-window="openStudioInNewWindow"
                    @run-validation="runValidationFromMenu"
                    @duplicate="duplicateFlow"
                    @toggle-pause="togglePause"
                    @delete="deleteFlow"
                />
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6 text-center lg:hidden">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600">
                    <Smartphone class="h-6 w-6" />
                </div>
                <div class="mt-4 text-lg font-semibold text-slate-950">{{ $t('Builder editing is available on desktop screens') }}</div>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    {{ $t('Open this flow from a desktop browser to edit the canvas safely.') }}
                </p>
            </div>
            <div ref="studioWorkspaceRef" class="hidden lg:block">
                <div v-if="!isBoardOnlyWindow" class="relative z-20 mb-3">
                    <FlowReadinessPanel
                        :insights="flowInsights"
                        :plan-limits="props.plan_limits"
                        :collapsed="readinessCollapsed"
                        :blocking-nodes="blockingNodes"
                        :selected-node-id="activeNodeId || ''"
                        @toggle="toggleReadinessPanel"
                        @focus-node="focusReadinessNode"
                    />
                </div>
                <div :class="workspaceShellClass">
                    <div :class="workspaceGridClass" :style="desktopGridStyle">
                        <aside class="min-h-0 min-w-0 overflow-hidden transition-[opacity] duration-200" :class="isLibraryHidden ? 'pointer-events-none opacity-0' : 'opacity-100'">
                            <div v-show="!isLibraryHidden" class="flex h-full flex-col overflow-hidden rounded-[22px] border border-slate-200 bg-white">
                                <div class="border-b border-slate-200 px-3 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-semibold text-slate-950">{{ $t('Step library') }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ $t('Drag or press + to add') }}</div>
                                    </div>
                                    <span class="inline-flex shrink-0 items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-medium text-slate-600">
                                        {{ filteredLibrary.length }}
                                    </span>
                                </div>
                                <div class="mt-3 flex rounded-xl border border-slate-200 bg-slate-50 p-1">
                                    <button type="button" class="flex-1 rounded-[10px] px-3 py-1.5 text-sm font-semibold transition" :class="libraryTab === 'messages' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-600'" @click="libraryTab = 'messages'">{{ $t('Messages') }}</button>
                                    <button type="button" class="flex-1 rounded-[10px] px-3 py-1.5 text-sm font-semibold transition" :class="libraryTab === 'actions' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-600'" @click="libraryTab = 'actions'">{{ $t('Actions') }}</button>
                                </div>
                                <div class="relative mt-3">
                                    <Search class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                    <input
                                        v-model="librarySearch"
                                        type="text"
                                        class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-2 pe-4 ps-10 text-sm text-slate-700 outline-none transition focus:border-emerald-300 focus:bg-white"
                                        :placeholder="$t('Search steps')"
                                    />
                                </div>
                                </div>
                                <div class="min-h-0 flex-1 overflow-y-auto px-2 py-2">
                                    <div v-if="filteredLibrary.length" class="space-y-1.5">
                                        <div v-for="item in filteredLibrary" :key="item.type" draggable="true" class="group flex min-h-[44px] cursor-grab items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-1.5 transition hover:border-slate-300 hover:bg-slate-50 active:cursor-grabbing" @dragstart="startLibraryDrag(item.type, $event)" @dragend="draggingLibraryType = null">
                                            <div class="flex min-w-0 items-center gap-2.5">
                                                <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-700">
                                                    <component :is="iconFor(item.type)" class="h-4 w-4" />
                                                </span>
                                                <div class="truncate text-sm font-medium text-slate-800">{{ labelForLibraryItem(item) }}</div>
                                            </div>
                                            <div class="flex shrink-0 items-center gap-1.5">
                                                <GripVertical class="h-3.5 w-3.5 text-slate-300" />
                                                <button type="button" class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-transparent text-sm font-medium text-slate-500 transition hover:border-slate-200 hover:bg-slate-100 hover:text-slate-800" @click.stop="addNodeFromLibrary(item.type)">+</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="flex h-full min-h-[160px] items-center justify-center rounded-[24px] border border-dashed border-slate-300 bg-slate-50/80 px-5 text-center">
                                        <div>
                                            <div class="text-sm font-semibold text-slate-800">{{ $t('No results') }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </aside>
                        <section class="relative min-h-0 min-w-0 overflow-hidden rounded-[22px] border border-slate-200 bg-white">
                            <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-3.5 py-3">
                                <div class="min-w-0">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <div class="text-sm font-semibold text-slate-950">{{ $t('Canvas') }}</div>
                                        <span v-if="activeCanvasNode" class="inline-flex min-w-0 items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                                            <span class="truncate">{{ resolvedActiveNodeTitle }}</span>
                                        </span>
                                    </div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $t('Drag cards from anywhere on the step. Click once to select and click again to edit inside the card.') }}</div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                    <button
                                        type="button"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-slate-300 hover:text-slate-900"
                                        :title="isLibraryHidden ? $t('Show library') : $t('Hide library')"
                                        @click="toggleLibraryRail()"
                                    >
                                        <component :is="isLibraryHidden ? ChevronsRight : ChevronsLeft" class="h-4 w-4" />
                                    </button>
                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2.5 py-1">{{ canvasStepLabel }}</span>
                                    <span v-if="canvasWarningLabel" class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-amber-700">{{ canvasWarningLabel }}</span>
                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2.5 py-1.5 font-semibold text-slate-600">{{ canvasZoomPercent }}</span>
                                    <button
                                        v-if="activeCanvasNode"
                                        type="button"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-slate-300 hover:text-slate-900"
                                        :title="$t('Focus step')"
                                        @click="focusActiveNodeInView"
                                    >
                                        <Search class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-900"
                                        :title="$t('Open the focused studio area only')"
                                        @click="toggleBrowserFullscreen"
                                    >
                                        <component :is="isBrowserFullscreen ? Minimize2 : Maximize2" class="h-3.5 w-3.5" />
                                        {{ isBrowserFullscreen ? $t('Exit focus') : $t('Focus') }}
                                    </button>
                                </div>
                            </div>
                            <div class="relative h-[calc(100%-61px)] overflow-hidden bg-[radial-gradient(circle,_rgba(148,163,184,0.22)_1px,_transparent_1px)] [background-size:20px_20px]" @dragover.prevent="handleCanvasDragOver" @drop.prevent="handleCanvasDrop">
                                <button
                                    v-if="isLibraryHidden"
                                    type="button"
                                    class="absolute start-3 top-1/2 z-20 inline-flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-900"
                                    :title="$t('Show library')"
                                    @click="toggleLibraryRail(false)"
                                >
                                    <ChevronsRight class="h-4 w-4" />
                                </button>
                                <VueFlow
                                    :nodes="nodes"
                                    :edges="edges"
                                    class="h-full w-full"
                                    :node-types="nodeTypes"
                                    :edge-types="edgeTypes"
                                    :default-viewport="defaultViewport"
                                    :nodes-draggable="true"
                                    :node-drag-threshold="2"
                                    :select-nodes-on-drag="false"
                                    :no-drag-class-name="'nodrag'"
                                    :min-zoom="0.3"
                                    :max-zoom="1.8"
                                    :snap-to-grid="false"
                                    :snap-grid="[1, 1]"
                                    :auto-pan-on-node-drag="true"
                                    fit-view-on-init
                                    @pane-ready="handlePaneReady"
                                    @nodes-change="onNodesChange"
                                    @edges-change="onEdgesChange"
                                    @connect="onConnect"
                                    @node-click="handleNodeClick"
                                    @node-double-click="handleNodeDoubleClick"
                                    @node-drag-start="handleNodeDragStart"
                                    @node-drag-stop="handleNodeDragStop"
                                    @pane-click="handlePaneClick"
                                    @viewport-change-end="handleViewportChangeEnd"
                                >
                                    <Background pattern-color="rgba(148,163,184,0.28)" :gap="20" />
                                    <Controls position="bottom-left" class="!rounded-xl !border !border-slate-200 !bg-white !shadow-sm" />
                                </VueFlow>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
            <FlowMetaEditModal
                :open="metaEditorOpen"
                :name="draft.name || ''"
                :description="draft.description || ''"
                @close="closeMetaEditor"
                @save="saveMetaEditor"
            />
            <FlowPreviewModal
                :open="previewModalOpen"
                :preview="previewData"
                :ui-enhanced="uiEnhancementsEnabled"
                :active-node-label="resolvedActiveNodeTitle"
                :graph="previewGraph"
                :node-titles="previewNodeTitles"
                :assets="assets"
                :contact-fields="props.contact_fields"
                :contact-groups="props.contact_groups"
                :assignable-agents="props.assignable_agents"
                :validation="validation"
                :insights="flowInsights"
                :builder-policy="builderPolicy"
                :runtime-rules="props.builder_runtime?.runtime || {}"
                @close="closePreviewModal"
                @simulation-state-change="handlePreviewSimulationStateChange"
            />
            <FlowExitConfirmModal
                :open="exitConfirmOpen"
                :busy="exitActionBusy"
                :flow-name="draft.name || ''"
                @close="closeExitConfirm"
                @discard="leaveWithoutSaving"
                @save-and-exit="saveAndExit"
            />
            <FlowBuilderDangerModals
                :node-delete-open="nodeDeleteConfirmOpen"
                :node-delete-subject="nodeDeleteTargetTitle"
                :flow-delete-open="flowDeleteConfirmOpen"
                :flow-delete-busy="flowDeleteBusy"
                :flow-name="draft.name || ''"
                @close-node-delete="closeNodeDeleteConfirm"
                @confirm-node-delete="confirmDeleteNode"
                @close-flow-delete="closeFlowDeleteConfirm"
                @confirm-flow-delete="confirmDeleteFlow"
            />
        </div>
    </SettingLayout>
</template>
<script setup>
import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';
import '@vue-flow/controls/dist/style.css';
import '@vue-flow/minimap/dist/style.css';
import '@/Components/AutomationFlows/flowBuilderCanvas.css';
import axios from 'axios';
import { computed, markRaw, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import { VueFlow, applyEdgeChanges, applyNodeChanges } from '@vue-flow/core';
import { ChevronsLeft, ChevronsRight, GripVertical, Maximize2, Minimize2, Search, Smartphone } from 'lucide-vue-next';
import SettingLayout from './../Layout.vue';
import FlowCanvasEdge from '@/Components/AutomationFlows/FlowCanvasEdge.vue';
import FlowCanvasNode from '@/Components/AutomationFlows/FlowCanvasNode.vue';
import FlowBuilderHeaderCard from '@/Components/AutomationFlows/FlowBuilderHeaderCard.vue';
import FlowBuilderDangerModals from '@/Components/AutomationFlows/FlowBuilderDangerModals.vue';
import FlowExitConfirmModal from '@/Components/AutomationFlows/FlowExitConfirmModal.vue';
import FlowMetaEditModal from '@/Components/AutomationFlows/FlowMetaEditModal.vue';
import FlowPreviewModal from '@/Components/AutomationFlows/FlowPreviewModal.vue';
import FlowReadinessPanel from '@/Components/AutomationFlows/FlowReadinessPanel.vue';
import { applyNodeDelete, beginNodeDelete, createLeaveGuard } from '@/Components/AutomationFlows/flowBuilderDanger.js';
import { resolveFlowBuilderDestination, FLOW_INDEX_PATH } from '@/Components/AutomationFlows/flowBuilderRouting.js';
import { collectFlowInsights } from '@/Components/AutomationFlows/flowBuilderInsights.js';
import { buildStartingStepOptions, canInsertNodeIntoExistingPath, composeFlowBuilderUiJson, insertNodeOnCanvasEdge, nextBranchForNode, resolveNodePayload, resolveVueFlowDomEvent, toCanvasEdge, toGraphEdge } from '@/Components/AutomationFlows/flowBuilderStudio.js';
import { normalizeNodeRefreshList, refreshFlowCanvasNodeInternals } from '@/Components/AutomationFlows/flowCanvasLayout.js';
import { buildCanvasEdgeId, provideFlowCanvasRuntime, syncGraphNodePosition } from '@/Components/AutomationFlows/flowCanvasRuntime.js';
import { buildFlowEdge, cloneFlowValue, defaultNodeConfig, makeFlowBuilderUuid } from '@/Components/AutomationFlows/flowBuilderDraft.js';
import { normalizeTriggerStart, pruneOutgoingBranches } from '@/Components/AutomationFlows/flowBuilderGraph.js';
import { flowNodeCategory, flowNodeIcon, flowNodeLabel, resolveFlowNodeTitle } from '@/Components/AutomationFlows/flowBuilderMeta.js';
import { useFlowCanvasSurfaceDrag } from '@/Components/AutomationFlows/useFlowCanvasSurfaceDrag.js';
import { buildNodeErrors, buildValidationSummary, saveStateLabelFor, validationErrorsFromResponse } from '@/Components/AutomationFlows/flowBuilderValidation.js';
const { t } = useI18n();
const props = defineProps({
    flow: { type: Object, required: true },
    library: { type: Array, default: () => [] },
    contact_fields: { type: Array, default: () => [] },
    contact_groups: { type: Array, default: () => [] },
    assignable_agents: { type: Array, default: () => [] },
    preview: { type: Object, default: () => ({ scenario: 'main', steps: [] }) },
    validation: { type: Object, default: () => ({ valid: true, errors: [], warnings: [] }) },
    builder_runtime: { type: Object, default: () => ({}) },
    assets: { type: Object, default: () => ({}) },
    node_secrets: { type: Object, default: () => ({}) },
    plan_limits: { type: Object, default: () => ({}) },
});
const draft = ref(cloneFlowValue(props.flow, {}));
const saveState = ref('saved');
const exitConfirmOpen = ref(false);
const exitActionBusy = ref(false);
const nodeDeleteConfirmOpen = ref(false);
const nodeDeleteTargetId = ref('');
const nodeDeleteTargetTitle = ref('');
const flowDeleteConfirmOpen = ref(false);
const flowDeleteBusy = ref(false);
const studioWorkspaceRef = ref(null);
const metaEditorOpen = ref(false);
const previewModalOpen = ref(false);
const moreOpen = ref(false);
const libraryTab = ref(props.flow?.ui_json?.library?.tab || 'messages');
const librarySearch = ref(props.flow?.ui_json?.library?.search || '');
const readinessCollapsed = ref(Boolean(props.flow?.ui_json?.readiness?.collapsed ?? true));
const isLibraryHidden = ref(Boolean(props.flow?.ui_json?.layout?.library_hidden ?? false));
const draggingLibraryType = ref(null);
const previewData = ref(cloneFlowValue(props.preview, { scenario: 'main', steps: [] }));
const validation = ref(cloneFlowValue(props.validation, { valid: true, errors: [], warnings: [] }));
const assets = ref(cloneFlowValue(props.assets, {}));
const nodeSecrets = ref(cloneFlowValue(props.node_secrets, {}));
const activeNodeId = ref(props.flow?.ui_json?.selection?.active_node_id || 'trigger-1');
const focusedNodeId = ref(null);
const draggingNodeId = ref(null);
const flowInstance = ref(null);
const currentViewport = ref(cloneFlowValue(props.flow?.ui_json?.viewport, { x: 0, y: 0, zoom: 1 }));
const nodes = ref([]);
const edges = ref([]);
const isBrowserFullscreen = ref(false);
const isBoardOnlyWindow = ref(typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('board') === '1');
const previewSimulationState = ref(cloneFlowValue(props.flow?.ui_json?.preview?.simulation, {
    mode: 'interactive_whatsapp_ui',
    state: 'idle',
    run_count: 0,
}));
let previewRefreshTimer = null;
let autosaveTimer = null;
let activeSavePromise = null;
let changesQueuedDuringSave = false;
let removeNavigationGuard = null;
const nodeTypes = { automationCanvasNode: markRaw(FlowCanvasNode) };
const edgeTypes = { automationCanvasEdge: markRaw(FlowCanvasEdge) };
const hasUnsavedChanges = computed(() => ['dirty', 'error'].includes(saveState.value));
const saveStateLabel = computed(() => saveStateLabelFor(saveState.value, t));
const uiEnhancementsEnabled = computed(() => props.builder_runtime?.ui_enhancements_enabled !== false);
const whatsappCompliance = computed(() => props.builder_runtime?.whatsapp_compliance || {});
const builderPolicy = computed(() => props.builder_runtime?.builder_policy || {});
const defaultViewport = computed(() => currentViewport.value || { x: 0, y: 0, zoom: 1 });
const canvasZoomPercent = computed(() => `${Math.round((currentViewport.value?.zoom || 1) * 100)}%`);
const canvasStepLabel = computed(() => `${nodes.value.length} ${t('step(s)')}`);
const canvasWarningLabel = computed(() => {
    const count = validation.value?.warnings?.length || 0;
    return count ? `${count} ${t('warning(s)')}` : '';
});
const minimapNodeColor = (node) => ({
    trigger: '#0ea5e9', condition: '#7c3aed', send_buttons: '#7c3aed', send_list: '#7c3aed',
    assign_to_agent: '#0ea5e9', human_handoff: '#f59e0b', handoff_to_ai_assistant: '#f59e0b', end: '#475569',
}[node?.data?.nodeType] || '#0f766e');
const workspaceShellClass = computed(() => (isBoardOnlyWindow.value
    ? 'border-0 bg-transparent p-0 shadow-none'
    : 'rounded-[28px] border border-slate-200 bg-[linear-gradient(180deg,rgba(248,250,252,0.98),rgba(241,245,249,0.94))] p-2.5 shadow-sm xl:p-3'));
const workspaceGridClass = computed(() => (isBoardOnlyWindow.value
    ? 'grid min-h-[calc(100vh-5.5rem)] gap-4'
    : 'grid gap-2.5 min-h-[640px] lg:min-h-[calc(100vh-12rem)] xl:h-[calc(100vh-11.5rem)]'));
const leftRailWidth = computed(() => (isLibraryHidden.value ? '0px' : (isBoardOnlyWindow.value ? 'clamp(156px, 11vw, 184px)' : 'clamp(160px, 12vw, 188px)')));
const desktopGridStyle = computed(() => ({ gridTemplateColumns: `${leftRailWidth.value} minmax(0, 1fr)` }));
const isPreviewDockVisible = computed(() => previewModalOpen.value);
const filteredLibrary = computed(() => {
    const query = librarySearch.value.trim().toLowerCase();

    return props.library.filter((item) => {
        if (item.category !== libraryTab.value) {
            return false;
        }

        if (!query) {
            return true;
        }

        const haystack = [
            item?.type || '',
            item?.label || '',
            flowNodeLabel(item?.type || '', t) || item?.label || '',
            item?.builder_scope || '',
        ].join(' ').toLowerCase();

        return haystack.includes(query);
    });
});
const activeCanvasNode = computed(() => nodes.value.find((node) => node.id === activeNodeId.value) || null);
const resolveNodeTitle = (source = {}, fallback = '') => resolveFlowNodeTitle({
    nodeType: source?.nodeType || source?.type,
    type: source?.nodeType || source?.type,
    title: source?.title,
    label: source?.label,
}, t) || fallback;
const resolvedActiveNodeTitle = computed(() => resolveNodeTitle(activeCanvasNode.value?.data, ''));
const blockingNodes = computed(() => nodes.value
    .map((node) => {
        const errors = Array.isArray(node?.data?.errors) ? node.data.errors.filter(Boolean) : [];
        if (!errors.length) {
            return null;
        }

        return {
            id: node.id,
            title: resolveNodeTitle(node?.data, node.id),
            firstError: errors[0],
            errorCount: errors.length,
        };
    })
    .filter(Boolean));
const labelForType = (type) => flowNodeLabel(type, t);
const labelForLibraryItem = (item) => flowNodeLabel(item?.type || '', t) || item?.label || '';
const categoryForType = (type) => flowNodeCategory(type, t);
const iconFor = (type) => flowNodeIcon(type);
const autosaveDebounceMs = Math.max(400, Number(props.builder_runtime?.autosave_debounce_ms || 1200));
const syncCanvasAfterLayoutChange = () => {
    nextTick(() => {
        if (!flowInstance.value) {
            return;
        }

        if (currentViewport.value && typeof flowInstance.value.setViewport === 'function') {
            flowInstance.value.setViewport(cloneFlowValue(currentViewport.value, { x: 0, y: 0, zoom: 1 }));
        }
    });
};
const toggleLibraryRail = (explicit = null) => {
    const nextState = explicit === null ? !isLibraryHidden.value : Boolean(explicit);
    if (isLibraryHidden.value === nextState) {
        return;
    }
    isLibraryHidden.value = nextState;
    markDraftDirty();
    syncCanvasAfterLayoutChange();
};
const focusActiveNodeInView = () => {
    const node = activeCanvasNode.value;
    if (!node || !flowInstance.value) {
        return;
    }
    if (flowInstance.value.setCenter) {
        const nodeWidth = Number(node?.dimensions?.width || 296);
        const nodeHeight = Number(node?.dimensions?.height || 228);
        const centerX = Number(node?.position?.x || 0) + (nodeWidth / 2);
        const centerY = Number(node?.position?.y || 0) + (nodeHeight / 2);
        flowInstance.value.setCenter(centerX, centerY, {
            zoom: Math.max(Number(currentViewport.value?.zoom || 1), 0.85),
            duration: 180,
        });
        return;
    }
    flowInstance.value?.fitView?.({
        nodes: [node],
        padding: 0.32,
        duration: 180,
    });
};
const applyValidationErrorsFromResponse = (error) => {
    const nextErrors = validationErrorsFromResponse(error);
    if (!nextErrors.length) return;
    validation.value = {
        valid: false,
        errors: [...new Set(nextErrors)],
        warnings: validation.value?.warnings || [],
    };
};
const clearAutosaveTimer = () => {
    if (!autosaveTimer) return;
    clearTimeout(autosaveTimer);
    autosaveTimer = null;
};
const queueAutosave = () => {
    clearAutosaveTimer();
    autosaveTimer = window.setTimeout(() => persistDraft({ autosave: true }), autosaveDebounceMs);
};
const markDraftDirty = () => {
    draft.value.has_unpublished_changes = true;
    if (activeSavePromise) {
        changesQueuedDuringSave = true;
        return;
    }
    saveState.value = 'dirty';
    queueAutosave();
};
const openMetaEditor = () => {
    metaEditorOpen.value = true;
};
const closeMetaEditor = () => {
    metaEditorOpen.value = false;
};
const openPreviewModal = () => {
    previewModalOpen.value = true;
    moreOpen.value = false;
};
const closePreviewModal = () => {
    previewModalOpen.value = false;
};
const collapseInlineNode = (nodeId = focusedNodeId.value) => {
    if (!nodeId || focusedNodeId.value !== nodeId) {
        focusedNodeId.value = null;
        return;
    }

    focusedNodeId.value = null;
};
const saveMetaEditor = (payload = {}) => {
    draft.value.name = (payload.name || '').trim() || t('Automation name');
    draft.value.description = (payload.description || '').trim();
    metaEditorOpen.value = false;
};
const toGraphNode = (node) => {
    const fallbackNode = (draft.value?.graph_json?.nodes || []).find((graphNode) => graphNode.id === node.id) || {};
    const fallbackUi = fallbackNode.ui || {};
    const nodeData = node.data || {};
    return {
        id: node.id,
        type: nodeData.nodeType || fallbackNode.type || 'send_text',
        position: {
            x: Number(node.position?.x || 0),
            y: Number(node.position?.y || 0),
        },
        config: cloneFlowValue(nodeData.config, fallbackNode.config || {}),
        ui: {
            title: nodeData.title || nodeData.label || fallbackUi.title || null,
        },
    };
};
const decorateNodeData = (graphNode, graphNodes, graphEdges) => {
    const currentAsset = assets.value[graphNode.config?.asset_id] || null;

    return {
        nodeType: graphNode.type,
        config: cloneFlowValue(graphNode.config, {}),
        title: resolveNodeTitle({
            type: graphNode.type,
            title: graphNode.ui?.title,
            label: labelForType(graphNode.type),
        }, labelForType(graphNode.type)),
        label: labelForType(graphNode.type),
        category: categoryForType(graphNode.type),
        errors: buildNodeErrors({
            graphNode,
            graphEdges,
            assets: assets.value,
            nodeSecrets: nodeSecrets.value,
            compliance: whatsappCompliance.value,
            builderPolicy: builderPolicy.value,
            assignableAgents: props.assignable_agents,
            handoffCapabilities: props.builder_runtime?.handoff_capabilities || {},
            t,
        }),
        contactFields: props.contact_fields,
        contactGroups: props.contact_groups,
        assignableAgents: props.assignable_agents,
        asset: currentAsset,
        nodeSecret: cloneFlowValue(nodeSecrets.value[graphNode.id], {}),
        compliance: whatsappCompliance.value,
        runtime: props.builder_runtime?.runtime || {},
        handoffCapabilities: props.builder_runtime?.handoff_capabilities || {},
        startingSteps: graphNode.type === 'trigger'
            ? buildStartingStepOptions(graphNodes, graphNode.id, (node) => resolveNodeTitle({
                type: node.type,
                title: node.ui?.title,
                label: labelForType(node.type),
            }, labelForType(node.type)))
            : [],
        connectedBranches: graphEdges
            .filter((edge) => String(edge?.source_id || '') === String(graphNode.id || ''))
            .map((edge) => String(edge?.branch || '').trim())
            .filter(Boolean),
        onUpdate: updateNode,
        onDelete: deleteNode,
        onDuplicate: duplicateNode,
        onRename: renameNode,
        onQuickAdd: quickAddNode,
        onUploadAsset: uploadNodeAsset,
        onRemoveAsset: removeNodeAsset,
        onUpdateSecret: updateNodeSecret,
        onRemapBranches: remapNodeBranchIds,
        onPruneBranches: pruneNodeBranches,
        onUpdateStartingStep: updateTriggerStartingStep,
    };
};
const createCanvasNode = (graphNode, graphNodes, graphEdges, existingNode = null) => ({
    ...(existingNode || {}),
    id: graphNode.id,
    type: 'automationCanvasNode',
    position: cloneFlowValue(graphNode.position, existingNode?.position || { x: 160, y: 160 }),
    draggable: true,
    selectable: true,
    dragHandle: '.flow-card-drag-handle',
    data: decorateNodeData(graphNode, graphNodes, graphEdges),
});
const decorateNodes = (graphNodes, graphEdges, previousNodes = []) => {
    const previousNodeById = new Map((Array.isArray(previousNodes) ? previousNodes : []).map((node) => [node.id, node]));

    return graphNodes.map((graphNode) => createCanvasNode(
        graphNode,
        graphNodes,
        graphEdges,
        previousNodeById.get(graphNode.id) || null,
    ));
};
const recalculateValidation = (graphNodes, graphEdges) => {
    validation.value = buildValidationSummary({
        graphNodes,
        graphEdges,
        assets: assets.value,
        nodeSecrets: nodeSecrets.value,
        compliance: whatsappCompliance.value,
        builderPolicy: builderPolicy.value, runtime: props.builder_runtime?.runtime || {},
        assignableAgents: props.assignable_agents,
        handoffCapabilities: props.builder_runtime?.handoff_capabilities || {},
        t,
    });
};
const syncCanvasPresentation = ({
    nodeIds = [],
    edgeNodeIds = nodeIds,
    markDirty = false,
    refreshPreviewAfter = false,
    refreshNodeInternals = false,
} = {}) => {
    const graph = draft.value.graph_json || { start_node_id: 'trigger-1', nodes: [], edges: [] };
    const graphNodes = Array.isArray(graph.nodes) ? graph.nodes : [];
    const graphEdges = Array.isArray(graph.edges) ? graph.edges : [];
    const normalizedNodeIds = normalizeNodeRefreshList(nodeIds);
    const normalizedEdgeNodeIds = normalizeNodeRefreshList(edgeNodeIds);

    if (normalizedNodeIds.length) {
        const nodeIdSet = new Set(normalizedNodeIds);
        const previousNodeById = new Map(nodes.value.map((node) => [node.id, node]));

        nodes.value = graphNodes.map((graphNode) => (
            nodeIdSet.has(graphNode.id) || !previousNodeById.has(graphNode.id)
                ? createCanvasNode(graphNode, graphNodes, graphEdges, previousNodeById.get(graphNode.id) || null)
                : previousNodeById.get(graphNode.id)
        )).filter(Boolean);
    }

    if (normalizedEdgeNodeIds.length) {
        const edgeNodeIdSet = new Set(normalizedEdgeNodeIds);
        const graphEdgeById = new Map(graphEdges.map((edge) => [buildCanvasEdgeId(edge), edge]));
        const edgeIdsToRefresh = new Set(
            graphEdges
                .filter((edge) => edgeNodeIdSet.has(edge.source_id) || edgeNodeIdSet.has(edge.target_id))
                .map((edge) => buildCanvasEdgeId(edge)),
        );

        if (edgeIdsToRefresh.size) {
            edges.value = edges.value.map((edge) => (
                edgeIdsToRefresh.has(edge.id)
                    ? toCanvasEdge(graphEdgeById.get(edge.id), graphNodes, { onInsertNode: insertNodeOnEdge, t })
                    : edge
            ));
        }
    }

    recalculateValidation(graphNodes, graphEdges);

    if (refreshNodeInternals) {
        void refreshFlowCanvasNodeInternals(flowInstance.value, normalizedNodeIds);
    }

    if (markDirty) {
        markDraftDirty();
    }

    if (refreshPreviewAfter) {
        schedulePreviewRefresh();
    }
};
const schedulePreviewRefresh = () => {
    if (!isPreviewDockVisible.value) return;
    if (previewRefreshTimer) clearTimeout(previewRefreshTimer);
    previewRefreshTimer = window.setTimeout(() => refreshPreview(), 220);
};
const rebuildCanvas = (graph, {
    markDirty = false,
    refreshPreviewAfter = false,
    nodeIdsToRefresh = [],
} = {}) => {
    const normalized = normalizeTriggerStart(graph.nodes || [], graph.edges || []);
    const nextActiveNodeId = normalized.nodes.some((node) => node.id === activeNodeId.value) ? activeNodeId.value : null;
    const nextFocusedNodeId = normalized.nodes.some((node) => node.id === focusedNodeId.value) ? focusedNodeId.value : null;
    if (activeNodeId.value !== nextActiveNodeId) activeNodeId.value = nextActiveNodeId;
    if (focusedNodeId.value !== nextFocusedNodeId) focusedNodeId.value = nextFocusedNodeId;
    draft.value.graph_json = cloneFlowValue(normalized, {});
    nodes.value = decorateNodes(normalized.nodes, normalized.edges, nodes.value);
    edges.value = normalized.edges.map((edge) => toCanvasEdge(edge, normalized.nodes, { onInsertNode: insertNodeOnEdge, t }));
    recalculateValidation(normalized.nodes, normalized.edges);
    const refreshIds = normalizeNodeRefreshList(nodeIdsToRefresh);
    if (refreshIds.length) {
        void refreshFlowCanvasNodeInternals(flowInstance.value, refreshIds);
    }
    if (markDirty) markDraftDirty();
    if (refreshPreviewAfter) schedulePreviewRefresh();
};
const rebuildDirtyCanvas = (graph, nodeIdsToRefresh = [], refreshPreviewAfter = true) => rebuildCanvas(graph, {
    markDirty: true,
    refreshPreviewAfter,
    nodeIdsToRefresh,
});
const rebuildCurrentCanvas = (nodeIdsToRefresh = [], refreshPreviewAfter = true) => rebuildCanvas(graphFromCanvas(), {
    refreshPreviewAfter,
    nodeIdsToRefresh,
});
const graphFromCanvas = () => ({
    start_node_id: draft.value.graph_json?.start_node_id || 'trigger-1',
    nodes: nodes.value.map(toGraphNode),
    edges: edges.value.map(toGraphEdge),
});
const committedCanvasGraph = computed(() => draft.value.graph_json || {
    start_node_id: 'trigger-1',
    nodes: [],
    edges: [],
});
const previewGraph = computed(() => committedCanvasGraph.value);
const previewNodeTitles = computed(() => (Array.isArray(committedCanvasGraph.value?.nodes) ? committedCanvasGraph.value.nodes : []).reduce((carry, node) => {
    if (node?.id) {
        carry[node.id] = resolveNodeTitle({
            type: node.type,
            title: node.ui?.title,
            label: labelForType(node.type),
        }, node.id);
    }
    return carry;
}, {}));
const flowInsights = computed(() => collectFlowInsights({
    graph: committedCanvasGraph.value,
    planLimits: props.plan_limits,
    validation: validation.value,
    t,
}));
const composeUiJson = () => composeFlowBuilderUiJson({
    activeNodeId: activeNodeId.value,
    currentViewport: currentViewport.value,
    isLibraryHidden: isLibraryHidden.value,
    isRightDockHidden: true,
    lastNonPreviewRightDockTab: 'inspector',
    librarySearch: librarySearch.value,
    libraryTab: libraryTab.value,
    previewScenario: previewData.value?.scenario || 'main',
    previewSimulationState: previewSimulationState.value,
    readinessCollapsed: readinessCollapsed.value,
    rightDockTab: 'inspector',
    uiJson: draft.value.ui_json || {},
});
const updateNode = (nodeId, patch) => {
    draft.value.graph_json = {
        ...(draft.value.graph_json || {}),
        nodes: (draft.value.graph_json?.nodes || []).map((node) => {
            if (node.id !== nodeId) {
                return node;
            }
            return {
                ...node,
                config: {
                    ...(node.config || {}),
                    ...(patch.config || {}),
                },
                ui: {
                    ...(node.ui || {}),
                    ...(patch.ui || {}),
                },
            };
        }),
    };
    syncCanvasPresentation({
        nodeIds: [nodeId],
        edgeNodeIds: [nodeId],
        markDirty: true,
        refreshPreviewAfter: true,
        refreshNodeInternals: true,
    });
};
const updateNodeSecret = (nodeId, patch) => {
    nodeSecrets.value = {
        ...nodeSecrets.value,
        [nodeId]: {
            ...(nodeSecrets.value[nodeId] || {}),
            ...patch,
        },
    };
    syncCanvasPresentation({
        nodeIds: [nodeId],
        edgeNodeIds: [],
        markDirty: true,
        refreshPreviewAfter: true,
        refreshNodeInternals: true,
    });
};
const remapNodeBranchIds = (nodeId, mappings = []) => {
    const normalizedMappings = (Array.isArray(mappings) ? mappings : [])
        .map((entry) => ({
            from: String(entry?.from || '').trim(),
            to: String(entry?.to || '').trim(),
        }))
        .filter((entry) => entry.from !== '' && entry.to !== '' && entry.from !== entry.to);
    if (!normalizedMappings.length) {
        return;
    }
    const graph = graphFromCanvas();
    let changed = false;
    graph.edges = graph.edges.map((edge) => {
        if (edge.source_id !== nodeId) {
            return edge;
        }
        const mapping = normalizedMappings.find((entry) => entry.from === String(edge.branch || '').trim());
        if (!mapping) {
            return edge;
        }
        changed = true;
        return {
            ...edge,
            branch: mapping.to,
        };
    });
    if (changed) {
        rebuildDirtyCanvas(graph, [nodeId]);
    }
};
const pruneNodeBranches = (nodeId, branchIds = []) => {
    const graph = graphFromCanvas();
    const nextGraph = pruneOutgoingBranches(graph, nodeId, branchIds);

    if ((nextGraph.edges || []).length === (graph.edges || []).length) {
        return;
    }

    rebuildDirtyCanvas(nextGraph, [nodeId]);
};
const renameNode = (nodeId) => {
    const current = (draft.value.graph_json?.nodes || []).find((node) => node.id === nodeId);
    if (!current || current.type === 'trigger') {
        return;
    }
    const nextTitle = window.prompt(t('Rename node'), resolveNodeTitle({
        type: current.type,
        title: current.ui?.title,
        label: labelForType(current.type),
    }, labelForType(current.type)));
    if (!nextTitle) {
        return;
    }
    draft.value.graph_json = {
        ...(draft.value.graph_json || {}),
        nodes: (draft.value.graph_json?.nodes || []).map((node) => (node.id === nodeId ? {
            ...node,
            ui: {
                ...(node.ui || {}),
                title: nextTitle.trim(),
            },
        } : node)),
    };
    syncCanvasPresentation({
        nodeIds: [nodeId],
        edgeNodeIds: [nodeId],
        markDirty: true,
        refreshPreviewAfter: true,
        refreshNodeInternals: true,
    });
};
const duplicateNode = (nodeId) => {
    const graph = graphFromCanvas();
    const current = graph.nodes.find((node) => node.id === nodeId);
    if (!current || current.type === 'trigger') {
        return;
    }
    const duplicatedId = makeFlowBuilderUuid(current.type);
    graph.nodes.push({
        ...cloneFlowValue(current),
        id: duplicatedId,
        position: {
            x: Number(current.position?.x || 0) + 80,
            y: Number(current.position?.y || 0) + 80,
        },
        ui: {
            ...(current.ui || {}),
            title: `${resolveNodeTitle({
                type: current.type,
                title: current.ui?.title,
                label: labelForType(current.type),
            }, labelForType(current.type))} ${t('Copy')}`,
        },
    });
    if (current.type === 'send_email' && nodeSecrets.value[nodeId]) {
        nodeSecrets.value = {
            ...nodeSecrets.value,
            [duplicatedId]: cloneFlowValue(nodeSecrets.value[nodeId], {}),
        };
    }
    activeNodeId.value = duplicatedId;
    focusedNodeId.value = duplicatedId;
    rebuildDirtyCanvas(graph, [nodeId, duplicatedId]);
    void nextTick(() => focusActiveNodeInView());
};
const deleteNode = (nodeId) => {
    const pendingDelete = beginNodeDelete({
        graph: graphFromCanvas(),
        nodeId,
        resolveNodeTitle,
        labelForType,
    });

    if (!pendingDelete) return;

    nodeDeleteTargetId.value = pendingDelete.nodeId;
    nodeDeleteTargetTitle.value = pendingDelete.title;
    nodeDeleteConfirmOpen.value = true;
};
const closeNodeDeleteConfirm = () => { nodeDeleteConfirmOpen.value = false; nodeDeleteTargetId.value = ''; nodeDeleteTargetTitle.value = ''; };
const confirmDeleteNode = () => {
    const nodeId = nodeDeleteTargetId.value;
    if (!nodeId) {
        closeNodeDeleteConfirm();
        return;
    }

    const nextState = applyNodeDelete({
        graph: graphFromCanvas(),
        nodeId,
        nodeSecrets: nodeSecrets.value,
        activeNodeId: activeNodeId.value,
    });

    nodeSecrets.value = nextState.nodeSecrets;
    activeNodeId.value = nextState.activeNodeId;
    closeNodeDeleteConfirm();
    rebuildDirtyCanvas(nextState.graph);
};
const updateTriggerStartingStep = (nodeId, targetId) => {
    draft.value.graph_json = {
        ...(draft.value.graph_json || {}),
        nodes: (draft.value.graph_json?.nodes || []).map((node) => (node.id === nodeId ? {
            ...node,
            config: {
                ...(node.config || {}),
                starting_step: targetId,
            },
        } : node)),
    };
    syncCanvasPresentation({
        nodeIds: [nodeId],
        edgeNodeIds: [nodeId],
        markDirty: true,
        refreshPreviewAfter: true,
        refreshNodeInternals: true,
    });
};
const addNodeFromLibrary = (type, position = null) => {
    const graph = graphFromCanvas();
    const anchorNode = graph.nodes.find((node) => node.id === activeNodeId.value);
    const newNode = {
        id: makeFlowBuilderUuid(type),
        type,
        position: position || {
            x: anchorNode ? Number(anchorNode.position?.x || 0) + 360 : 420,
            y: anchorNode ? Number(anchorNode.position?.y || 0) : 180 + (graph.nodes.length * 42),
        },
        config: defaultNodeConfig(type, t),
        ui: {},
    };
    graph.nodes.push(newNode);
    if (anchorNode && anchorNode.type !== 'trigger') {
        const branch = nextBranchForNode(anchorNode, graph.edges);
        const existingBranchEdge = graph.edges.find((edge) => edge.source_id === anchorNode.id && edge.branch === branch) || null;
        if (!existingBranchEdge) {
            graph.edges.push(buildFlowEdge(anchorNode.id, newNode.id, branch));
        } else if (canInsertNodeIntoExistingPath(type)) {
            graph.edges = graph.edges.filter((edge) => !(edge.source_id === anchorNode.id && edge.branch === branch));
            graph.edges.push(buildFlowEdge(anchorNode.id, newNode.id, branch));
            graph.edges.push(buildFlowEdge(newNode.id, existingBranchEdge.target_id, 'default'));
        }
    } else {
        const trigger = graph.nodes.find((node) => node.type === 'trigger');
        if (trigger && !trigger.config?.starting_step && type !== 'trigger') {
            trigger.config = {
                ...(trigger.config || {}),
                starting_step: newNode.id,
            };
        }
    }
    activeNodeId.value = newNode.id;
    focusedNodeId.value = newNode.id;
    rebuildDirtyCanvas(graph, [anchorNode?.id, newNode.id]);
    void nextTick(() => focusActiveNodeInView());
};
const insertNodeOnEdge = (edgeId, type = 'send_text') => {
    const nextState = insertNodeOnCanvasEdge({ graph: graphFromCanvas(), edgeId, type, makeNodeId: makeFlowBuilderUuid, defaultNodeConfig, t });
    if (!nextState) return;
    activeNodeId.value = nextState.newNodeId;
    focusedNodeId.value = nextState.newNodeId;
    rebuildDirtyCanvas(nextState.graph, nextState.refreshNodeIds);
    void nextTick(() => focusActiveNodeInView());
};
const quickAddNode = (sourceNodeId, type) => {
    activeNodeId.value = sourceNodeId;
    addNodeFromLibrary(type);
};
const startLibraryDrag = (type, event) => {
    draggingLibraryType.value = type;
    event?.dataTransfer?.setData('application/x-automation-node-type', type);
    if (event?.dataTransfer) {
        event.dataTransfer.effectAllowed = 'copy';
    }
};
const handleCanvasDragOver = (event) => {
    if (event?.dataTransfer) {
        event.dataTransfer.dropEffect = 'copy';
    }
};
const handleCanvasDrop = (event) => {
    const type = event?.dataTransfer?.getData('application/x-automation-node-type') || draggingLibraryType.value;
    draggingLibraryType.value = null;
    if (!type) {
        return;
    }
    let position = { x: 420, y: 180 };
    if (flowInstance.value?.screenToFlowCoordinate) {
        position = flowInstance.value.screenToFlowCoordinate({ x: event.clientX, y: event.clientY });
    } else if (flowInstance.value?.project) {
        position = flowInstance.value.project({ x: event.clientX, y: event.clientY });
    }
    addNodeFromLibrary(type, position);
};
const uploadNodeAsset = async (nodeId, file, mediaKind = null) => {
    const formData = new FormData();
    formData.append('file', file);
    if (mediaKind) {
        formData.append('media_kind', mediaKind);
    }
    const response = await axios.post(`/automation/flows/${draft.value.uuid}/assets`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
    assets.value = {
        ...assets.value,
        [response.data.asset.uuid]: response.data.asset,
    };
    updateNode(nodeId, {
        config: {
            asset_id: response.data.asset.uuid,
            media_type: response.data.asset.media_kind || mediaKind || 'image',
        },
    });
};
const removeNodeAsset = async (nodeId) => {
    const graph = graphFromCanvas();
    const currentNode = graph.nodes.find((node) => node.id === nodeId);
    const assetId = currentNode?.config?.asset_id;
    if (assetId) {
        await axios.delete(`/automation/flows/${draft.value.uuid}/assets/${assetId}`);
        const nextAssets = cloneFlowValue(assets.value, {});
        delete nextAssets[assetId];
        assets.value = nextAssets;
    }
    updateNode(nodeId, {
        config: {
            asset_id: '',
        },
    });
};
const visualNodeChangeTypes = new Set(['position', 'dimensions', 'select']);
const persistedEdgeChangeTypes = new Set(['add', 'remove', 'replace']);
const {
    beginNodeSurfaceDrag,
    openNodeSurface: openCanvasNodeSurface,
    requestNodeActionGuard: suppressCanvasClickSideEffects,
    selectNodeSurface: selectCanvasNodeSurface,
    teardownSurfaceNodeDrag,
    isCanvasClickSuppressed,
    isSurfaceDragActive,
} = useFlowCanvasSurfaceDrag({
    nodes,
    draft,
    flowInstance,
    currentViewport,
    moreOpen,
    activeNodeId,
    focusedNodeId,
    draggingNodeId,
    markDraftDirty,
    openNode: (nodeId) => focusCanvasNode(nodeId, {
        refreshPreviewAfter: false,
        openInline: true,
        recenter: true,
    }),
});
const syncDraggedNodePosition = (payload, maybeNode = null) => {
    const nodePayload = resolveNodePayload(payload, maybeNode);
    const nodeId = nodePayload?.node?.id || null;
    const nextPosition = nodePayload?.node?.position || null;
    if (!nodeId || !nextPosition) return null;
    const nextGraph = syncGraphNodePosition(draft.value.graph_json, nodeId, nextPosition);
    if (nextGraph === draft.value.graph_json) return null;
    draft.value.graph_json = nextGraph;
    return nodeId;
};
const isNodeActionEvent = (payload) => {
    if (isCanvasClickSuppressed()) return true;
    const target = resolveVueFlowDomEvent(payload)?.target;
    return Boolean(target?.closest?.('[data-flow-node-action="true"], input, textarea, select, button, a, [contenteditable="true"]'));
};
const isNodeSurfaceEvent = (payload) => Boolean(resolveVueFlowDomEvent(payload)?.target?.closest?.('.vue-flow__node'));
const onNodesChange = (changes) => {
    if (!Array.isArray(changes) || !changes.length) return;
    if (isSurfaceDragActive()) return;
    const visualChanges = changes.filter((change) => visualNodeChangeTypes.has(change.type));
    if (!visualChanges.length) return;
    nodes.value = applyNodeChanges(visualChanges, nodes.value);
};
const onEdgesChange = (changes) => {
    const persistedChanges = changes.filter((change) => persistedEdgeChangeTypes.has(change.type));
    if (!persistedChanges.length) return;
    const changedEdges = applyEdgeChanges(persistedChanges, edges.value);
    rebuildDirtyCanvas({
        start_node_id: draft.value.graph_json?.start_node_id || 'trigger-1',
        nodes: nodes.value.map(toGraphNode),
        edges: changedEdges.map(toGraphEdge),
    });
};
const onConnect = (connection) => {
    const graph = graphFromCanvas();
    const nextEdge = {
        source_id: connection.source,
        target_id: connection.target,
        branch: connection.sourceHandle || 'default',
    };
    graph.edges = graph.edges.filter((edge) => !(edge.source_id === nextEdge.source_id && edge.branch === nextEdge.branch));
    graph.edges.push(buildFlowEdge(nextEdge.source_id, nextEdge.target_id, nextEdge.branch));
    rebuildDirtyCanvas(graph);
};
const handleNodeDragStart = (payload, maybeNode = null) => {
    const nodeId = resolveNodePayload(payload, maybeNode)?.node?.id || null;
    draggingNodeId.value = nodeId;
    suppressCanvasClickSideEffects();
    moreOpen.value = false;
};
const handleNodeDragStop = (payload, maybeNode = null) => {
    const nodeId = syncDraggedNodePosition(payload, maybeNode) || resolveNodePayload(payload, maybeNode)?.node?.id;
    draggingNodeId.value = null;
    if (!nodeId) return;
    markDraftDirty();
};
const focusCanvasNode = (nodeId, { refreshPreviewAfter = true, openInline = true, recenter = false } = {}) => {
    if (!nodeId) return;
    moreOpen.value = false;
    activeNodeId.value = nodeId;
    if (openInline) {
        focusedNodeId.value = nodeId;
    } else if (focusedNodeId.value && focusedNodeId.value !== nodeId) {
        focusedNodeId.value = null;
    }
    if (refreshPreviewAfter && isPreviewDockVisible.value) {
        schedulePreviewRefresh();
    }
    if (recenter) {
        void nextTick(() => focusActiveNodeInView());
    }
};
const toggleInlineNode = (nodeId) => {
    if (!nodeId) return;
    moreOpen.value = false;
    activeNodeId.value = nodeId;
    if (focusedNodeId.value === nodeId) {
        collapseInlineNode(nodeId);
        return;
    }
    focusCanvasNode(nodeId, {
        refreshPreviewAfter: false,
        openInline: true,
    });
};
const handleNodeClick = (payload, maybeNode = null) => {
    if (draggingNodeId.value) return;
    const { event, node } = resolveNodePayload(payload, maybeNode);
    if (!node?.id || isNodeActionEvent(event)) return;
    moreOpen.value = false;
    activeNodeId.value = node.id;
    if (focusedNodeId.value && focusedNodeId.value !== node.id) {
        focusedNodeId.value = null;
    }
};
const handleNodeDoubleClick = (payload, maybeNode = null) => {
    if (draggingNodeId.value) return;
    const { event, node } = resolveNodePayload(payload, maybeNode);
    if (!node?.id || isNodeActionEvent(event)) return;
    focusCanvasNode(node.id, {
        refreshPreviewAfter: false,
        openInline: true,
        recenter: true,
    });
};
const handlePaneClick = (payload) => { if (isNodeActionEvent(payload) || isNodeSurfaceEvent(payload)) return; moreOpen.value = false; focusedNodeId.value = null; };
const handleNodeActionPointerDown = () => suppressCanvasClickSideEffects();
provideFlowCanvasRuntime({
    uiEnhanced: uiEnhancementsEnabled,
    activeNodeId,
    focusedNodeId,
    draggingNodeId,
    hasFocusedNode: computed(() => Boolean(focusedNodeId.value)),
    isNodeActive: (nodeId) => activeNodeId.value === nodeId,
    isNodeFocused: (nodeId) => focusedNodeId.value === nodeId,
    isNodeDragging: (nodeId) => draggingNodeId.value === nodeId,
    requestNodeActionGuard: handleNodeActionPointerDown,
    beginNodeSurfaceDrag,
    selectNodeSurface: selectCanvasNodeSurface,
    openNodeSurface: openCanvasNodeSurface,
    toggleNodeInline: toggleInlineNode,
    collapseNodeInline: collapseInlineNode,
});
const handlePaneReady = (instance) => {
    flowInstance.value = instance;
    void refreshFlowCanvasNodeInternals(flowInstance.value, nodes.value.map((node) => node.id).filter(Boolean));
    if (currentViewport.value && instance?.setViewport) {
        instance.setViewport(currentViewport.value);
        return;
    }
    instance?.fitView?.();
};
const handleViewportChangeEnd = (viewport) => {
    currentViewport.value = cloneFlowValue(viewport, currentViewport.value);
};
const savePayload = () => ({
    name: draft.value.name,
    description: draft.value.description,
    graph_json: graphFromCanvas(),
    ui_json: composeUiJson(),
    node_secrets: nodeSecrets.value,
});
const runValidation = async () => {
    try {
        const response = await axios.post(`/automation/flows/${draft.value.uuid}/validate`, {
            graph_json: graphFromCanvas(),
            node_secrets: nodeSecrets.value,
        });
        validation.value = response.data;
        return true;
    } catch (error) {
        applyValidationErrorsFromResponse(error);
        return false;
    }
};
const refreshPreview = async () => {
    try {
        const response = await axios.post(`/automation/flows/${draft.value.uuid}/preview`, {
            graph_json: graphFromCanvas(),
            focus_node_id: activeNodeId.value,
        });
        previewData.value = response.data;
        return true;
    } catch {
        return false;
    }
};
const persistDraft = async ({ autosave = false, refreshAfter = true } = {}) => {
    if (activeSavePromise) {
        const inFlightResult = await activeSavePromise;
        if (!autosave && saveState.value === 'dirty') {
            return persistDraft({ autosave, refreshAfter });
        }
        return inFlightResult;
    }
    clearAutosaveTimer();
    if (!autosave) {
        moreOpen.value = false;
    }
    activeSavePromise = (async () => {
        saveState.value = autosave ? 'autosaving' : 'saving';
        try {
            const payload = savePayload();
            if (autosave) {
                await axios.post(`/automation/flows/${draft.value.uuid}/autosave`, payload);
            } else {
                await axios.put(`/automation/flows/${draft.value.uuid}`, payload);
            }
            draft.value.graph_json = graphFromCanvas();
            draft.value.ui_json = composeUiJson();
            draft.value.has_unpublished_changes = true;
            saveState.value = 'saved';
            await runValidation();
            if (refreshAfter || isPreviewDockVisible.value) {
                await refreshPreview();
            }
            return true;
        } catch (error) {
            saveState.value = 'error';
            applyValidationErrorsFromResponse(error);
            return false;
        }
    })();
    try {
        return await activeSavePromise;
    } finally {
        activeSavePromise = null;
        if (changesQueuedDuringSave) {
            changesQueuedDuringSave = false;
            saveState.value = 'dirty';
            queueAutosave();
        }
    }
};
const saveDraft = async (refreshAfter = true) => persistDraft({ autosave: false, refreshAfter });
const { closeExitConfirm, leaveWithoutSaving, saveAndExit, handleInertiaBefore, promptLeaveToIndex } = createLeaveGuard({
    exitConfirmOpen,
    exitActionBusy,
    hasUnsavedChanges,
    saveDraft,
});
const publishFlow = async () => {
    clearAutosaveTimer();
    const saved = await saveDraft(false);
    if (!saved || saveState.value === 'dirty') {
        return;
    }
    try {
        await axios.post(`/automation/flows/${draft.value.uuid}/publish`);
        draft.value.status = 'published';
        draft.value.has_unpublished_changes = false;
        saveState.value = 'saved';
        await runValidation();
        if (isPreviewDockVisible.value) {
            await refreshPreview();
        }
    } catch (error) {
        saveState.value = 'error';
        applyValidationErrorsFromResponse(error);
    }
};
const togglePause = async () => {
    const pause = draft.value.status !== 'paused';
    const response = await axios.post(`/automation/flows/${draft.value.uuid}/pause`, { pause });
    draft.value.status = response.data.flow_status;
    moreOpen.value = false;
};
const duplicateFlow = async () => {
    moreOpen.value = false;
    clearAutosaveTimer();
    if (hasUnsavedChanges.value) {
        const saved = await saveDraft(false);
        if (!saved) {
            return;
        }
    }
    const response = await axios.post(`${FLOW_INDEX_PATH}/${draft.value.uuid}/duplicate`);
    router.visit(resolveFlowBuilderDestination(response.data));
};
const deleteFlow = () => {
    moreOpen.value = false;
    flowDeleteConfirmOpen.value = true;
};
const closeFlowDeleteConfirm = () => { if (!flowDeleteBusy.value) flowDeleteConfirmOpen.value = false; };
const confirmDeleteFlow = async () => {
    if (flowDeleteBusy.value) {
        return;
    }

    flowDeleteBusy.value = true;
    try {
        clearAutosaveTimer();
        const response = await axios.delete(`${FLOW_INDEX_PATH}/${draft.value.uuid}`);
        flowDeleteBusy.value = false;
        flowDeleteConfirmOpen.value = false;
        saveState.value = 'saved';
        router.visit(resolveFlowBuilderDestination(response.data));
    } catch {
        flowDeleteBusy.value = false;
    }
};
const runValidationFromMenu = async () => {
    moreOpen.value = false;
    await runValidation();
};
const handlePreviewSimulationStateChange = (payload = {}) => {
    previewSimulationState.value = {
        ...(previewSimulationState.value || {}),
        mode: 'interactive_whatsapp_ui',
        state: payload.state || previewSimulationState.value?.state || 'idle',
        run_count: Number(payload.run_count ?? previewSimulationState.value?.run_count ?? 0),
    };
};
const focusReadinessNode = (nodeId) => {
    if (!nodeId) {
        return;
    }

    focusCanvasNode(nodeId, { recenter: true });
};
const openStudioInNewWindow = () => {
    if (typeof window === 'undefined') {
        return;
    }

    const nextUrl = new URL(window.location.href);
    nextUrl.searchParams.set('board', '1');
    window.open(nextUrl.toString(), '_blank', 'noopener,noreferrer');
};
const syncFullscreenState = () => {
    if (typeof document === 'undefined') {
        return;
    }

    isBrowserFullscreen.value = Boolean(document.fullscreenElement);
};
const toggleBrowserFullscreen = async () => {
    if (typeof document === 'undefined') {
        return;
    }

    const fullscreenTarget = studioWorkspaceRef.value instanceof HTMLElement
        ? studioWorkspaceRef.value
        : document.documentElement;

    try {
        if (document.fullscreenElement) {
            await document.exitFullscreen?.();
        } else {
            await fullscreenTarget?.requestFullscreen?.();
        }
    } catch {
        return;
    }

    syncFullscreenState();
};
const toggleReadinessPanel = () => {
    readinessCollapsed.value = !readinessCollapsed.value;
    markDraftDirty();
};
const goBack = () => { moreOpen.value = false; promptLeaveToIndex(); };
const beforeUnloadHandler = (event) => {
    if (!hasUnsavedChanges.value) return;
    event.preventDefault();
    event.returnValue = '';
};
watch(previewModalOpen, (value) => {
    if (value) schedulePreviewRefresh();
});
watch(() => [draft.value.name, draft.value.description], () => {
    markDraftDirty();
});
watch(activeNodeId, () => {
    if (isPreviewDockVisible.value) schedulePreviewRefresh();
});
onMounted(() => {
    window.addEventListener('beforeunload', beforeUnloadHandler);
    document.addEventListener('fullscreenchange', syncFullscreenState);
    removeNavigationGuard = router.on('before', handleInertiaBefore);
    syncFullscreenState();
});
onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', beforeUnloadHandler);
    document.removeEventListener('fullscreenchange', syncFullscreenState);
    if (typeof removeNavigationGuard === 'function') removeNavigationGuard();
    clearAutosaveTimer();
    if (previewRefreshTimer) clearTimeout(previewRefreshTimer);
    teardownSurfaceNodeDrag();
});
rebuildCanvas(cloneFlowValue(props.flow?.graph_json, {
    start_node_id: 'trigger-1',
    nodes: [],
    edges: [],
}));
</script>
