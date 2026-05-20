import { inject, provide } from 'vue';

const FLOW_CANVAS_RUNTIME_KEY = Symbol('flow-canvas-runtime');

const normalizeCoordinate = (value) => {
    const numeric = Number(value);
    return Number.isFinite(numeric) ? numeric : 0;
};

export const buildCanvasEdgeId = (edge = {}) => (
    edge.id || `edge-${edge.source_id}-${edge.branch || edge.sourceHandle || 'default'}-${edge.target_id}`
);

export const syncGraphNodePosition = (graph = {}, nodeId = '', nextPosition = null) => {
    const normalizedNodeId = String(nodeId || '').trim();

    if (!normalizedNodeId || !nextPosition || typeof graph !== 'object' || graph === null) {
        return graph;
    }

    const sourceNodes = Array.isArray(graph.nodes) ? graph.nodes : [];
    const targetPosition = {
        x: normalizeCoordinate(nextPosition.x),
        y: normalizeCoordinate(nextPosition.y),
    };

    let changed = false;

    const nodes = sourceNodes.map((node) => {
        if (String(node?.id || '') !== normalizedNodeId) {
            return node;
        }

        const currentX = normalizeCoordinate(node?.position?.x);
        const currentY = normalizeCoordinate(node?.position?.y);

        if (currentX === targetPosition.x && currentY === targetPosition.y) {
            return node;
        }

        changed = true;

        return {
            ...node,
            position: targetPosition,
        };
    });

    return changed ? { ...graph, nodes } : graph;
};

export const provideFlowCanvasRuntime = (context) => {
    provide(FLOW_CANVAS_RUNTIME_KEY, context);
};

export const useFlowCanvasRuntime = () => {
    const context = inject(FLOW_CANVAS_RUNTIME_KEY, null);

    if (!context) {
        throw new Error('Flow canvas runtime context is not available.');
    }

    return context;
};

export const tryUseFlowCanvasRuntime = () => inject(FLOW_CANVAS_RUNTIME_KEY, null);
