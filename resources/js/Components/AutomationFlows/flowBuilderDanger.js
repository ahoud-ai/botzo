import { router } from '@inertiajs/vue3';
import { cloneFlowValue } from '@/Components/AutomationFlows/flowBuilderDraft.js';
import { FLOW_INDEX_PATH } from '@/Components/AutomationFlows/flowBuilderRouting.js';

export const beginNodeDelete = ({ graph, nodeId, resolveNodeTitle, labelForType }) => {
    const current = graph.nodes.find((node) => node.id === nodeId);
    if (!current || current.type === 'trigger') {
        return null;
    }

    return {
        nodeId,
        title: resolveNodeTitle(current.data, labelForType(current.type)),
    };
};

export const applyNodeDelete = ({
    graph,
    nodeId,
    nodeSecrets,
    activeNodeId,
}) => {
    const nextGraph = cloneFlowValue(graph, { start_node_id: 'trigger-1', nodes: [], edges: [] });
    nextGraph.nodes = nextGraph.nodes.filter((node) => node.id !== nodeId);
    nextGraph.edges = nextGraph.edges.filter((edge) => edge.source_id !== nodeId && edge.target_id !== nodeId);

    const nextSecrets = cloneFlowValue(nodeSecrets, {});
    if (nextSecrets[nodeId]) {
        delete nextSecrets[nodeId];
    }

    return {
        graph: nextGraph,
        nodeSecrets: nextSecrets,
        activeNodeId: activeNodeId === nodeId ? null : activeNodeId,
    };
};

export const clonePendingLeaveVisit = (visit = null) => {
    if (!visit) {
        return null;
    }

    return {
        url: visit.url,
        method: visit.method || 'get',
        data: visit.data instanceof FormData ? visit.data : cloneFlowValue(visit.data, {}),
        replace: Boolean(visit.replace),
        preserveScroll: Boolean(visit.preserveScroll),
        preserveState: Boolean(visit.preserveState),
        only: Array.isArray(visit.only) ? [...visit.only] : [],
        headers: cloneFlowValue(visit.headers, {}),
        errorBag: visit.errorBag || '',
        forceFormData: Boolean(visit.forceFormData),
        queryStringArrayFormat: visit.queryStringArrayFormat || 'brackets',
    };
};

export const pendingLeaveVisitPayload = (visit = null) => {
    if (!visit?.url) {
        return null;
    }

    return {
        url: visit.url,
        options: {
            method: visit.method,
            data: visit.data,
            replace: visit.replace,
            preserveScroll: visit.preserveScroll,
            preserveState: visit.preserveState,
            only: visit.only,
            headers: visit.headers,
            errorBag: visit.errorBag,
            forceFormData: visit.forceFormData,
            queryStringArrayFormat: visit.queryStringArrayFormat,
        },
    };
};

export const createLeaveGuard = ({
    exitConfirmOpen,
    exitActionBusy,
    hasUnsavedChanges,
    saveDraft,
}) => {
    let pendingLeaveVisit = null;
    let bypassLeaveGuard = false;

    const visitFlowIndex = () => {
        pendingLeaveVisit = null;
        bypassLeaveGuard = true;
        router.visit(FLOW_INDEX_PATH);
    };
    const visitPendingLeaveDestination = () => {
        const payload = pendingLeaveVisitPayload(pendingLeaveVisit);
        if (!payload) {
            visitFlowIndex();
            return;
        }

        pendingLeaveVisit = null;
        bypassLeaveGuard = true;
        router.visit(payload.url, payload.options);
    };

    return {
        closeExitConfirm: () => {
            if (exitActionBusy.value) return;
            pendingLeaveVisit = null;
            exitConfirmOpen.value = false;
        },
        leaveWithoutSaving: () => {
            if (exitActionBusy.value) return;
            exitConfirmOpen.value = false;
            visitPendingLeaveDestination();
        },
        saveAndExit: async () => {
            if (exitActionBusy.value) return;
            exitActionBusy.value = true;
            const saved = await saveDraft(false);
            if (saved) {
                exitConfirmOpen.value = false;
                visitPendingLeaveDestination();
                return;
            }

            exitActionBusy.value = false;
        },
        handleInertiaBefore: (event) => {
            if (bypassLeaveGuard) {
                bypassLeaveGuard = false;
                return true;
            }
            if (!hasUnsavedChanges.value) return true;

            event?.preventDefault?.();
            pendingLeaveVisit = clonePendingLeaveVisit(event?.detail?.visit || null);
            exitConfirmOpen.value = true;
            return false;
        },
        promptLeaveToIndex: () => {
            if (!hasUnsavedChanges.value) {
                visitFlowIndex();
                return;
            }

            pendingLeaveVisit = null;
            exitConfirmOpen.value = true;
        },
    };
};
