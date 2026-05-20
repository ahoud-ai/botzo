import { nextTick } from 'vue';

export const normalizeNodeRefreshList = (nodeIds = []) => [...new Set(
    (Array.isArray(nodeIds) ? nodeIds : []).filter(Boolean),
)];

export const refreshFlowCanvasNodeInternals = async (instance, nodeIds = []) => {
    const ids = normalizeNodeRefreshList(nodeIds);

    if (!instance?.updateNodeInternals || !ids.length) {
        return;
    }

    await nextTick();

    if (typeof window !== 'undefined' && typeof window.requestAnimationFrame === 'function') {
        await new Promise((resolve) => window.requestAnimationFrame(() => resolve()));
        await new Promise((resolve) => window.requestAnimationFrame(() => resolve()));
    }

    ids.forEach((nodeId) => instance.updateNodeInternals(nodeId));
};
