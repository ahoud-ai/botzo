import { buildFlowEdge, cloneFlowValue, defaultNodeConfig } from '@/Components/AutomationFlows/flowBuilderDraft.js';

export const normalizeTriggerStart = (graphNodes, graphEdges, t = (value) => value) => {
    const nodesCopy = cloneFlowValue(graphNodes, []) || [];
    const edgesCopy = cloneFlowValue(graphEdges, []) || [];

    let trigger = nodesCopy.find((node) => node.type === 'trigger');
    if (!trigger) {
        trigger = {
            id: 'trigger-1',
            type: 'trigger',
            position: { x: 80, y: 120 },
            config: defaultNodeConfig('trigger', t),
            ui: { expanded: true },
        };
        nodesCopy.unshift(trigger);
    }

    trigger.id = trigger.id || 'trigger-1';
    trigger.config = { ...defaultNodeConfig('trigger', t), ...(trigger.config || {}) };

    const availableTargets = nodesCopy.filter((node) => node.id !== trigger.id);
    let startingStep = trigger.config.starting_step || '';
    const triggerEdge = edgesCopy.find((edge) => edge.source_id === trigger.id);

    if (!startingStep && triggerEdge?.target_id) {
        startingStep = triggerEdge.target_id;
    }

    if (!startingStep && availableTargets.length === 1) {
        startingStep = availableTargets[0].id;
    }

    if (startingStep && !availableTargets.some((node) => node.id === startingStep)) {
        startingStep = availableTargets[0]?.id || '';
    }

    trigger.config.starting_step = startingStep;

    const withoutTriggerEdges = edgesCopy.filter((edge) => edge.source_id !== trigger.id);

    if (startingStep) {
        withoutTriggerEdges.unshift(buildFlowEdge(trigger.id, startingStep, 'default'));
    }

    return {
        start_node_id: trigger.id,
        nodes: nodesCopy,
        edges: withoutTriggerEdges,
    };
};

const normalizeBranchIds = (branchIds = []) => [...new Set(
    (Array.isArray(branchIds) ? branchIds : [])
        .map((value) => String(value || '').trim())
        .filter(Boolean),
)];

export const pruneOutgoingBranches = (graph, nodeId, branchIds = []) => {
    const normalizedNodeId = String(nodeId || '').trim();
    const normalizedBranchIds = normalizeBranchIds(branchIds);
    const graphCopy = cloneFlowValue(graph, {
        start_node_id: 'trigger-1',
        nodes: [],
        edges: [],
    }) || {
        start_node_id: 'trigger-1',
        nodes: [],
        edges: [],
    };

    if (!normalizedNodeId || !normalizedBranchIds.length) {
        return graphCopy;
    }

    const branchSet = new Set(normalizedBranchIds);
    const currentEdges = Array.isArray(graphCopy.edges) ? graphCopy.edges : [];

    graphCopy.edges = currentEdges.filter((edge) => {
        const sourceId = String(edge?.source_id || edge?.source || '').trim();
        const branch = String(edge?.branch || edge?.sourceHandle || 'default').trim();

        return !(sourceId === normalizedNodeId && branchSet.has(branch));
    });

    return graphCopy;
};
