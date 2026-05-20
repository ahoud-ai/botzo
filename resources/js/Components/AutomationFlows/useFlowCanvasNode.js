import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useVueFlow } from '@vue-flow/core';
import { useI18n } from 'vue-i18n';
import {
    flowNodeCategory,
    flowNodeCustomerOutcome,
    flowNodeHasActiveToggle,
    flowNodeIcon,
    flowNodeSummary,
    flowNodeVisualTone,
    resolveFlowNodeTitle,
} from '@/Components/AutomationFlows/flowBuilderMeta.js';
import { translateFlowMessage } from '@/Components/AutomationFlows/flowBuilderCopy.js';
import {
    buildFlowNodeSourceHandles,
    flowNodeLooksLikeMedia,
    resolveFlowNodeTypeFromData,
} from '@/Components/AutomationFlows/flowNodePresenter.js';
import { tryUseFlowCanvasRuntime } from '@/Components/AutomationFlows/flowCanvasRuntime.js';

const MENU_GAP = 8;
const MENU_FALLBACK_WIDTH = 176;
const MENU_FALLBACK_HEIGHT = 136;
const SURFACE_INTERACTIVE_SELECTOR = [
    '[data-flow-node-action="true"]',
    '[data-flow-node-drag-disabled="true"]',
    '.nodrag',
    'input',
    'textarea',
    'select',
    'button',
    'a',
    '[contenteditable="true"]',
    '[role="textbox"]',
    '[role="combobox"]',
    '[role="listbox"]',
    '[role="option"]',
    '[role="switch"]',
    '[role="slider"]',
    '[role="spinbutton"]',
    '[aria-haspopup="listbox"]',
    '[data-headlessui-state]',
].join(', ');
const clamp = (value, min, max) => Math.min(Math.max(value, min), max);
const makeBadge = (label, className) => ({ label, className });

export const useFlowCanvasNode = (props) => {
    const { t } = useI18n();
    const { updateNodeInternals } = useVueFlow();
    const canvasRuntime = tryUseFlowCanvasRuntime();

    const menuOpen = ref(false);
    const menuTriggerRef = ref(null);
    const menuPosition = ref({ top: 0, left: 0 });
    const setMenuTriggerRef = (element) => {
        menuTriggerRef.value = element || null;
    };

    const nodeType = computed(() => resolveFlowNodeTypeFromData(props.data || {}));
    const config = computed(() => props.data?.config || {});
    const asset = computed(() => props.data?.asset || null);
    const errors = computed(() => props.data?.errors || []);
    const visibleErrors = computed(() => errors.value.slice(0, 2));
    const hiddenErrorCount = computed(() => Math.max(0, errors.value.length - visibleErrors.value.length));
    const isTrigger = computed(() => nodeType.value === 'trigger');
    const isInactive = computed(() => flowNodeHasActiveToggle(nodeType.value) && config.value.active === false);
    const uiEnhanced = computed(() => (
        canvasRuntime?.uiEnhanced?.value
        ?? (props.data?.uiEnhanced !== false)
    ));
    const isActive = computed(() => (
        canvasRuntime?.isNodeActive?.(props.id)
        ?? props.data?.isActive
        ?? false
    ) === true);
    const isFocused = computed(() => (
        canvasRuntime?.isNodeFocused?.(props.id)
        ?? props.data?.isFocused
        ?? false
    ) === true);
    const hasFocusedNode = computed(() => (
        canvasRuntime?.hasFocusedNode?.value
        ?? props.data?.hasFocusedNode
        ?? false
    ) === true);
    const isDragging = computed(() => (
        canvasRuntime?.isNodeDragging?.(props.id)
        ?? props.data?.isDragging
        ?? false
    ) === true);
    const displayTitle = computed(() => resolveFlowNodeTitle({
        nodeType: nodeType.value,
        type: nodeType.value,
        title: props.data?.title,
        label: props.data?.label,
    }, t));
    const nodeCategoryLabel = computed(() => flowNodeCategory(nodeType.value, t));
    const IconComponent = computed(() => flowNodeIcon(nodeType.value));
    const cardTone = computed(() => flowNodeVisualTone(nodeType.value));
    const resolvedDisplayType = computed(() => (flowNodeLooksLikeMedia(nodeType.value, config.value, props.data) ? 'send_media' : nodeType.value));
    const compactSummary = computed(() => flowNodeSummary(resolvedDisplayType.value, config.value, { asset: asset.value }, t));
    const customerOutcomeText = computed(() => flowNodeCustomerOutcome(resolvedDisplayType.value, config.value, { asset: asset.value }, t));
    const canvasPreviewText = computed(() => compactSummary.value || '');
    const buttons = computed(() => Array.isArray(config.value.buttons) ? config.value.buttons : []);
    const listSections = computed(() => Array.isArray(config.value.sections) ? config.value.sections : []);
    const sourceHandles = computed(() => buildFlowNodeSourceHandles({
        nodeType: nodeType.value,
        buttons: buttons.value,
        listSections: listSections.value,
        t,
    }));
    const connectedBranchIds = computed(() => new Set(
        (Array.isArray(props.data?.connectedBranches) ? props.data.connectedBranches : [])
            .map((value) => String(value || '').trim())
            .filter(Boolean),
    ));
    const branchPreviewItems = computed(() => sourceHandles.value.map((handle) => ({
        id: handle.id,
        label: handle.label,
        connected: connectedBranchIds.value.has(handle.id),
    })));
    const visibleBranchPreviewItems = computed(() => branchPreviewItems.value.slice(0, 3));
    const hiddenBranchPreviewCount = computed(() => Math.max(0, branchPreviewItems.value.length - visibleBranchPreviewItems.value.length));
    const hiddenBranchPreviewLabel = computed(() => translateFlowMessage(t, '+ :count more path(s)', { count: hiddenBranchPreviewCount.value }));
    const sourceHandleSignature = computed(() => sourceHandles.value.map((handle) => handle.id).join('|'));
    const branchCoverage = computed(() => ({
        total: branchPreviewItems.value.length,
        linked: branchPreviewItems.value.filter((branch) => branch.connected).length,
        remaining: Math.max(0, branchPreviewItems.value.length - branchPreviewItems.value.filter((branch) => branch.connected).length),
    }));
    const showBranchHealth = computed(() => branchCoverage.value.total > 1 || branchCoverage.value.remaining > 0);
    const flowDelayMinutes = computed(() => Math.max(0, Number(config.value.minutes || 0)));
    const hiddenIssuesLabel = computed(() => translateFlowMessage(t, '+ :count more issue(s)', { count: hiddenErrorCount.value }));
    const statusBadge = computed(() => {
        if (errors.value.length) {
            return makeBadge(t('Needs attention'), 'border-rose-200 bg-rose-50 text-rose-700');
        }

        if (isInactive.value) {
            return makeBadge(t('Paused'), 'border-slate-200 bg-slate-100 text-slate-600');
        }

        return null;
    });
    const journeyBadge = computed(() => {
        if (nodeType.value === 'trigger') {
            return makeBadge(t('Entry step'), 'border-sky-200 bg-sky-50 text-sky-700');
        }

        if (nodeType.value === 'send_buttons' || nodeType.value === 'send_list') {
            return makeBadge(translateFlowMessage(t, ':count choice(s)', { count: branchCoverage.value.total }), 'border-violet-200 bg-violet-50 text-violet-700');
        }

        if (nodeType.value === 'condition') {
            return makeBadge(t('Split path'), 'border-violet-200 bg-violet-50 text-violet-700');
        }

        if (nodeType.value === 'save_reply_to_field') {
            return makeBadge(t('Waiting for customer'), 'border-amber-200 bg-amber-50 text-amber-700');
        }

        if (['add_to_group', 'remove_from_group', 'update_contact_field'].includes(nodeType.value)) {
            return makeBadge(t('CRM update'), 'border-cyan-200 bg-cyan-50 text-cyan-700');
        }

        if (nodeType.value === 'assign_to_agent') {
            return makeBadge(t('Team handoff'), 'border-cyan-200 bg-cyan-50 text-cyan-700');
        }

        if (['human_handoff', 'handoff_to_ai_assistant'].includes(nodeType.value)) {
            return makeBadge(t('Automation stops'), 'border-amber-200 bg-amber-50 text-amber-700');
        }

        if (nodeType.value === 'delay' && flowDelayMinutes.value > 0) {
            return makeBadge(translateFlowMessage(t, 'Waits :count min', { count: flowDelayMinutes.value }), 'border-amber-200 bg-amber-50 text-amber-700');
        }

        if (nodeType.value === 'send_email') {
            return makeBadge(t('Email step'), 'border-cyan-200 bg-cyan-50 text-cyan-700');
        }

        if (nodeType.value === 'end') {
            return makeBadge(t('Finish point'), 'border-slate-200 bg-slate-100 text-slate-600');
        }

        return null;
    });
    const branchCoverageBadge = computed(() => {
        if (!branchCoverage.value.total) {
            return null;
        }

        const missing = Math.max(0, branchCoverage.value.total - branchCoverage.value.linked);
        if (branchCoverage.value.total <= 1 && missing === 0) {
            return null;
        }

        return missing > 0
            ? makeBadge(translateFlowMessage(t, ':count path(s) missing', { count: missing }), 'border-amber-200 bg-amber-50 text-amber-700')
            : null;
    });
    const primaryCardMetric = computed(() => journeyBadge.value || branchCoverageBadge.value || null);
    const cardMetrics = computed(() => [primaryCardMetric.value].filter(Boolean));
    const primaryCardIssue = computed(() => visibleErrors.value[0] || (hiddenErrorCount.value > 0 ? hiddenIssuesLabel.value : ''));
    const previewClampClass = computed(() => (isFocused.value ? 'line-clamp-3' : 'line-clamp-2'));
    const wrapperClass = computed(() => (
        isDragging.value
            ? 'z-[16]'
            : isFocused.value
                ? 'z-[14]'
                : 'z-[1]'
    ));
    const articleClass = computed(() => ([
        uiEnhanced.value ? 'flow-card-shell-enhanced' : '',
        isFocused.value ? 'w-[420px] max-w-[420px] rounded-[24px]' : 'w-[236px] max-w-[236px]',
        hasFocusedNode.value && !isFocused.value ? 'opacity-55 saturate-75' : '',
        isDragging.value
            ? 'border-emerald-300 ring-2 ring-emerald-200 shadow-[0_16px_34px_rgba(16,185,129,0.18)] scale-[1.01]'
            : isFocused.value
                ? 'border-emerald-300 ring-2 ring-emerald-100 shadow-[0_20px_48px_rgba(15,23,42,0.16)]'
                : props.selected || isActive.value
                    ? 'border-emerald-200 shadow-[0_10px_22px_rgba(16,185,129,0.10)]'
                    : 'border-slate-200 hover:border-slate-300 hover:shadow-[0_10px_20px_rgba(15,23,42,0.08)]',
    ]));
    const handleClass = computed(() => cardTone.value.handleClass || '!bg-emerald-700');
    const menuStyle = computed(() => ({ top: `${menuPosition.value.top}px`, left: `${menuPosition.value.left}px` }));

    const handleActionPointerDown = () => {
        canvasRuntime?.requestNodeActionGuard?.();
    };
    const isInteractiveSurfaceEvent = (event) => Boolean(
        event?.target?.closest?.(SURFACE_INTERACTIVE_SELECTOR),
    );
    const isNativeDragHandleEvent = (event) => Boolean(
        event?.target?.closest?.('[data-flow-node-drag-handle="true"]'),
    );
    const handleSurfacePointerDown = (event) => {
        if (isInteractiveSurfaceEvent(event) || isNativeDragHandleEvent(event)) {
            return;
        }

        canvasRuntime?.beginNodeSurfaceDrag?.(props.id, event);
    };
    const handleSurfaceClick = (event) => {
        if (isInteractiveSurfaceEvent(event)) {
            return;
        }

        canvasRuntime?.selectNodeSurface?.(props.id);
    };
    const handleSurfaceDoubleClick = (event) => {
        if (isInteractiveSurfaceEvent(event)) {
            return;
        }

        canvasRuntime?.openNodeSurface?.(props.id);
    };
    const toggleInlineEditor = () => {
        closeMenu();
        handleActionPointerDown();
        canvasRuntime?.toggleNodeInline?.(props.id);
    };
    const syncMenuPosition = () => {
        if (typeof window === 'undefined') {
            return;
        }

        const triggerEl = menuTriggerRef.value;
        if (!triggerEl || typeof triggerEl.getBoundingClientRect !== 'function') {
            return;
        }

        const rect = triggerEl.getBoundingClientRect();
        const panelWidth = MENU_FALLBACK_WIDTH;
        const panelHeight = MENU_FALLBACK_HEIGHT;
        const viewportWidth = window.innerWidth || 0;
        const viewportHeight = window.innerHeight || 0;
        const left = clamp(rect.right - panelWidth, MENU_GAP, Math.max(MENU_GAP, viewportWidth - panelWidth - MENU_GAP));

        let top = rect.bottom + MENU_GAP;
        if (top + panelHeight > viewportHeight - MENU_GAP) {
            top = Math.max(MENU_GAP, rect.top - panelHeight - MENU_GAP);
        }

        menuPosition.value = { top: Math.round(top), left: Math.round(left) };
    };
    const closeMenu = () => {
        menuOpen.value = false;
    };
    const toggleMenu = () => {
        handleActionPointerDown();
        menuOpen.value = !menuOpen.value;
        if (menuOpen.value) {
            void nextTick(syncMenuPosition);
        }
    };
    const handleWindowResize = () => {
        if (menuOpen.value) {
            syncMenuPosition();
        }
    };
    const handleWindowScroll = () => {
        if (menuOpen.value) {
            syncMenuPosition();
        }
    };
    const handleEscapeKey = (event) => {
        if (event?.key === 'Escape' && menuOpen.value) {
            closeMenu();
        }
    };
    const handleDocumentPointerDown = (event) => {
        if (!menuOpen.value) {
            return;
        }

        const target = event?.target;
        if (menuTriggerRef.value?.contains(target) || target?.closest?.('.flow-node-menu-layer')) {
            return;
        }

        closeMenu();
    };
    const refreshInternals = () => {
        if (!props.id || typeof updateNodeInternals !== 'function') {
            return;
        }

        void nextTick(() => {
            if (typeof window === 'undefined' || typeof window.requestAnimationFrame !== 'function') {
                updateNodeInternals(props.id);
                return;
            }

            window.requestAnimationFrame(() => {
                updateNodeInternals(props.id);
            });
        });
    };

    watch(menuOpen, (isOpen) => {
        if (typeof window === 'undefined') {
            return;
        }

        if (isOpen) {
            window.addEventListener('pointerdown', handleDocumentPointerDown, true);
            window.addEventListener('resize', handleWindowResize, { passive: true });
            window.addEventListener('scroll', handleWindowScroll, true);
            window.addEventListener('keydown', handleEscapeKey);
            void nextTick(syncMenuPosition);
            return;
        }

        window.removeEventListener('pointerdown', handleDocumentPointerDown, true);
        window.removeEventListener('resize', handleWindowResize);
        window.removeEventListener('scroll', handleWindowScroll, true);
        window.removeEventListener('keydown', handleEscapeKey);
    });
    watch(() => [sourceHandleSignature.value, compactSummary.value, primaryCardIssue.value, displayTitle.value, isFocused.value], refreshInternals);
    onMounted(refreshInternals);
    onBeforeUnmount(() => {
        if (typeof window === 'undefined') {
            return;
        }

        window.removeEventListener('pointerdown', handleDocumentPointerDown, true);
        window.removeEventListener('resize', handleWindowResize);
        window.removeEventListener('scroll', handleWindowScroll, true);
        window.removeEventListener('keydown', handleEscapeKey);
    });

    const handleDuplicate = () => {
        closeMenu();
        props.data?.onDuplicate?.(props.id);
    };
    const handleRename = () => {
        closeMenu();
        props.data?.onRename?.(props.id);
    };
    const handleDelete = () => {
        closeMenu();
        props.data?.onDelete?.(props.id);
    };
    const collapseInlineEditor = () => {
        closeMenu();
        canvasRuntime?.collapseNodeInline?.(props.id);
    };

    return {
        IconComponent,
        articleClass,
        branchCoverage,
        cardMetrics,
        cardTone,
        canvasPreviewText,
        collapseInlineEditor,
        compactSummary,
        customerOutcomeText,
        displayTitle,
        handleActionPointerDown,
        handleSurfaceClick,
        handleSurfaceDoubleClick,
        handleSurfacePointerDown,
        handleClass,
        handleDelete,
        handleDuplicate,
        handleRename,
        hiddenBranchPreviewCount,
        hiddenBranchPreviewLabel,
        isFocused,
        isTrigger,
        menuOpen,
        menuStyle,
        menuTriggerRef,
        nodeCategoryLabel,
        primaryCardIssue,
        previewClampClass,
        setMenuTriggerRef,
        showBranchHealth,
        sourceHandles,
        statusBadge,
        toggleInlineEditor,
        toggleMenu,
        visibleBranchPreviewItems,
        wrapperClass,
    };
};
