import { MarkerType } from '@vue-flow/core';
import { buildFlowEdge } from '@/Components/AutomationFlows/flowBuilderDraft.js';
import { buildFlowNodeSourceHandles } from '@/Components/AutomationFlows/flowNodePresenter.js';

const resolveBranchId = (edge = {}) => edge.branch || edge.sourceHandle || 'default';
const findGraphNodeById = (graphNodes = [], nodeId = '') => graphNodes.find((node) => String(node?.id || '') === String(nodeId || '')) || null;
const EDGE_TONES = {
    default: {
        stroke: '#0f766e',
        badgeClass: 'border-slate-200 bg-white/95 text-slate-700',
        actionClass: 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 hover:text-slate-900',
    },
    choice: {
        stroke: '#7c3aed',
        badgeClass: 'border-violet-200 bg-violet-50/95 text-violet-700',
        actionClass: 'border-violet-200 bg-white text-violet-600 hover:border-violet-300 hover:text-violet-800',
    },
    positive: {
        stroke: '#059669',
        badgeClass: 'border-emerald-200 bg-emerald-50/95 text-emerald-700',
        actionClass: 'border-emerald-200 bg-white text-emerald-600 hover:border-emerald-300 hover:text-emerald-800',
    },
    warning: {
        stroke: '#d97706',
        badgeClass: 'border-amber-200 bg-amber-50/95 text-amber-700',
        actionClass: 'border-amber-200 bg-white text-amber-600 hover:border-amber-300 hover:text-amber-800',
    },
    handoff: {
        stroke: '#0ea5e9',
        badgeClass: 'border-sky-200 bg-sky-50/95 text-sky-700',
        actionClass: 'border-sky-200 bg-white text-sky-600 hover:border-sky-300 hover:text-sky-800',
    },
};

const resolveFlowEdgeTone = (sourceNode, branchId) => {
    if (sourceNode?.type === 'condition') {
        return branchId === 'matched' ? EDGE_TONES.positive : EDGE_TONES.warning;
    }

    if (['send_buttons', 'send_list'].includes(sourceNode?.type)) {
        return EDGE_TONES.choice;
    }

    if (['assign_to_agent', 'human_handoff', 'handoff_to_ai_assistant'].includes(sourceNode?.type)) {
        return EDGE_TONES.handoff;
    }

    return EDGE_TONES.default;
};

const resolveFlowEdgeLabel = (edge, graphNodes = [], t = (value) => value) => {
    const sourceNode = findGraphNodeById(graphNodes, edge.source_id);
    const branchId = resolveBranchId(edge);
    const sourceHandles = buildFlowNodeSourceHandles({
        nodeType: sourceNode?.type || '',
        buttons: sourceNode?.config?.buttons || [],
        listSections: sourceNode?.config?.sections || [],
        t,
    });

    return sourceHandles.find((handle) => handle.id === branchId)?.label || t('Next');
};

const shouldShowFlowEdgeLabel = (sourceNode, branchId) => (
    sourceNode?.type === 'condition'
    || ['send_buttons', 'send_list'].includes(sourceNode?.type)
    || branchId !== 'default'
);

export const toGraphEdge = (edge) => ({
    id: edge.id,
    source_id: edge.source_id || edge.source,
    target_id: edge.target_id || edge.target,
    branch: resolveBranchId(edge),
});

export const toCanvasEdge = (edge, graphNodes = [], { onInsertNode = null, t = (value) => value } = {}) => {
    const branchId = resolveBranchId(edge);
    const sourceNode = findGraphNodeById(graphNodes, edge.source_id);
    const tone = resolveFlowEdgeTone(sourceNode, branchId);

    return {
        id: edge.id || `edge-${edge.source_id}-${branchId}-${edge.target_id}`,
        source: edge.source_id,
        target: edge.target_id,
        sourceHandle: branchId,
        targetHandle: 'target',
        type: 'automationCanvasEdge',
        markerEnd: { type: MarkerType.ArrowClosed, width: 18, height: 18 },
        style: { stroke: tone.stroke, strokeWidth: 2.4 },
        data: {
            branchId,
            branchLabel: resolveFlowEdgeLabel(edge, graphNodes, t),
            badgeClass: tone.badgeClass,
            actionClass: tone.actionClass,
            canInsert: typeof onInsertNode === 'function' && Boolean(edge.source_id && edge.target_id),
            onInsertNode,
            showLabel: shouldShowFlowEdgeLabel(sourceNode, branchId),
        },
    };
};

export const buildStartingStepOptions = (graphNodes, triggerId, resolveNodeTitle) => graphNodes
    .filter((node) => node.id !== triggerId)
    .map((node) => ({
        id: node.id,
        label: resolveNodeTitle(node),
    }));

export const nextBranchForNode = (graphNode, graphEdges) => {
    const outgoing = graphEdges.filter((edge) => edge.source_id === graphNode.id);

    if (graphNode.type === 'condition') {
        return ['matched', 'unmatched'].find((branch) => !outgoing.some((edge) => edge.branch === branch)) || 'matched';
    }

    if (graphNode.type === 'send_buttons') {
        const buttonIds = (graphNode.config?.buttons || []).map((button) => button.id).filter(Boolean);
        return buttonIds.find((branch) => !outgoing.some((edge) => edge.branch === branch)) || buttonIds[0] || 'default';
    }

    if (graphNode.type === 'send_list') {
        const rowIds = (graphNode.config?.sections || []).flatMap((section) => section.rows || []).map((row) => row.id).filter(Boolean);
        return rowIds.find((branch) => !outgoing.some((edge) => edge.branch === branch)) || rowIds[0] || 'default';
    }

    return 'default';
};

export const canInsertNodeIntoExistingPath = (type) => ![
    'trigger',
    'condition',
    'send_buttons',
    'send_list',
    'human_handoff',
    'handoff_to_ai_assistant',
    'end',
].includes(type);

export const insertNodeOnCanvasEdge = ({
    defaultNodeConfig,
    edgeId,
    graph,
    makeNodeId,
    t = (value) => value,
    type = 'send_text',
}) => {
    const sourceGraph = {
        ...graph,
        nodes: Array.isArray(graph?.nodes) ? [...graph.nodes] : [],
        edges: Array.isArray(graph?.edges) ? [...graph.edges] : [],
    };
    const currentEdge = sourceGraph.edges.find((edge) => edge.id === edgeId) || null;
    if (!currentEdge?.source_id || !currentEdge?.target_id) return null;

    const branchId = resolveBranchId(currentEdge);
    const sourceNode = findGraphNodeById(sourceGraph.nodes, currentEdge.source_id);
    const targetNode = findGraphNodeById(sourceGraph.nodes, currentEdge.target_id);
    const newNodeId = makeNodeId(type);
    const newNode = {
        id: newNodeId,
        type,
        position: {
            x: Math.round((Number(sourceNode?.position?.x || 0) + Number(targetNode?.position?.x || 0)) / 2),
            y: Math.round((Number(sourceNode?.position?.y || 0) + Number(targetNode?.position?.y || 0)) / 2),
        },
        config: defaultNodeConfig(type, t),
        ui: {},
    };

    sourceGraph.nodes.push(newNode);
    sourceGraph.edges = sourceGraph.edges.filter((edge) => (
        edge.id !== currentEdge.id
        && !(edge.source_id === currentEdge.source_id && edge.target_id === currentEdge.target_id && resolveBranchId(edge) === branchId)
    ));
    sourceGraph.edges.push(buildFlowEdge(currentEdge.source_id, newNodeId, branchId));
    sourceGraph.edges.push(buildFlowEdge(newNodeId, currentEdge.target_id, 'default'));

    return {
        graph: sourceGraph,
        newNodeId,
        refreshNodeIds: [currentEdge.source_id, newNodeId, currentEdge.target_id].filter(Boolean),
    };
};

export const resolveVueFlowDomEvent = (payload) => payload?.event || payload || null;

export const resolveNodePayload = (payload, maybeNode = null) => (
    payload?.node
        ? payload
        : (maybeNode?.id ? { event: payload || null, node: maybeNode } : { event: payload || null, node: null })
);

export const composeFlowBuilderUiJson = ({
    activeNodeId,
    currentViewport,
    isLibraryHidden,
    isRightDockHidden,
    lastNonPreviewRightDockTab,
    librarySearch,
    libraryTab,
    previewScenario,
    previewSimulationState,
    readinessCollapsed,
    rightDockTab,
    uiJson = {},
}) => ({
    ...uiJson,
    surface: { variant: 'canvas' },
    mode: 'simple',
    readiness: {
        ...(uiJson.readiness || {}),
        collapsed: readinessCollapsed,
    },
    preview: {
        ...(uiJson.preview || {}),
        selected_scenario: previewScenario || 'main',
        collapsed: false,
        mode: 'whatsapp',
        simulation: {
            ...(uiJson.preview?.simulation || {}),
            mode: 'interactive_whatsapp_ui',
            state: previewSimulationState?.state || 'idle',
            run_count: Number(previewSimulationState?.run_count || 0),
        },
    },
    library: {
        tab: libraryTab,
        search: librarySearch,
    },
    layout: {
        ...(uiJson.layout || {}),
        library_hidden: isLibraryHidden,
        right_dock_hidden: isRightDockHidden,
    },
    right_dock: {
        tab: rightDockTab,
        last_non_preview_tab: lastNonPreviewRightDockTab,
    },
    viewport: currentViewport,
    selection: {
        active_node_id: activeNodeId,
    },
    canvas: {
        compact_cards: true,
    },
});
