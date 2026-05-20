import { cloneFlowValue } from '@/Components/AutomationFlows/flowBuilderDraft.js';
import { syncGraphNodePosition } from '@/Components/AutomationFlows/flowCanvasRuntime.js';

const ACTION_CLICK_SUPPRESS_DURATION_MS = 450;
const SURFACE_DRAG_THRESHOLD_PX = 4;

export const useFlowCanvasSurfaceDrag = ({
    nodes,
    draft,
    flowInstance,
    currentViewport,
    moreOpen,
    activeNodeId,
    focusedNodeId,
    draggingNodeId,
    markDraftDirty,
    openNode,
}) => {
    let suppressCanvasClicksUntil = 0;
    let suppressSurfaceNodeClickUntil = 0;
    let activeSurfaceDrag = null;

    const suppressCanvasClickSideEffects = () => {
        suppressCanvasClicksUntil = Date.now() + ACTION_CLICK_SUPPRESS_DURATION_MS;
    };
    const findCanvasNode = (nodeId) => nodes.value.find((node) => node.id === nodeId) || null;
    const projectPointerToCanvas = (clientX, clientY) => {
        if (flowInstance.value?.screenToFlowCoordinate) {
            return flowInstance.value.screenToFlowCoordinate({ x: clientX, y: clientY });
        }

        if (flowInstance.value?.project) {
            return flowInstance.value.project({ x: clientX, y: clientY });
        }

        const zoom = Number(currentViewport.value?.zoom || 1) || 1;
        const viewportX = Number(currentViewport.value?.x || 0);
        const viewportY = Number(currentViewport.value?.y || 0);

        return {
            x: (clientX - viewportX) / zoom,
            y: (clientY - viewportY) / zoom,
        };
    };
    const patchCanvasNodePosition = (nodeId, nextPosition) => {
        if (!nodeId || !nextPosition) {
            return;
        }

        const targetPosition = cloneFlowValue(nextPosition, { x: 0, y: 0 });

        nodes.value = nodes.value.map((node) => (
            node.id === nodeId
                ? {
                    ...node,
                    position: targetPosition,
                }
                : node
        ));
    };
    const selectNodeSurface = (nodeId) => {
        if (!nodeId || Date.now() < suppressSurfaceNodeClickUntil) {
            return;
        }

        moreOpen.value = false;
        activeNodeId.value = nodeId;

        if (focusedNodeId.value && focusedNodeId.value !== nodeId) {
            focusedNodeId.value = null;
        }
    };
    const openNodeSurface = (nodeId) => {
        if (!nodeId || Date.now() < suppressSurfaceNodeClickUntil) {
            return;
        }

        openNode(nodeId);
    };
    function handleSurfaceNodeDragMove(event) {
        const state = activeSurfaceDrag;

        if (!state) {
            return;
        }

        if (
            state.pointerId !== null
            && typeof event.pointerId === 'number'
            && event.pointerId !== state.pointerId
        ) {
            return;
        }

        const currentPoint = projectPointerToCanvas(event.clientX, event.clientY);
        const screenDeltaX = event.clientX - state.startClient.x;
        const screenDeltaY = event.clientY - state.startClient.y;

        if (!state.moved && Math.hypot(screenDeltaX, screenDeltaY) < SURFACE_DRAG_THRESHOLD_PX) {
            return;
        }

        if (!state.moved) {
            state.moved = true;
            draggingNodeId.value = state.nodeId;
            suppressCanvasClickSideEffects();
        }

        event.preventDefault();

        patchCanvasNodePosition(state.nodeId, {
            x: state.startNodePosition.x + (currentPoint.x - state.startPoint.x),
            y: state.startNodePosition.y + (currentPoint.y - state.startPoint.y),
        });
    }
    const teardownSurfaceNodeDrag = () => {
        if (typeof window !== 'undefined') {
            window.removeEventListener('pointermove', handleSurfaceNodeDragMove, true);
            window.removeEventListener('pointerup', finishSurfaceNodeDrag, true);
            window.removeEventListener('pointercancel', finishSurfaceNodeDrag, true);
            window.removeEventListener('mousemove', handleSurfaceNodeDragMove, true);
            window.removeEventListener('mouseup', finishSurfaceNodeDrag, true);
        }

        const state = activeSurfaceDrag;
        activeSurfaceDrag = null;

        return state;
    };
    function finishSurfaceNodeDrag(event = null) {
        const state = activeSurfaceDrag;

        if (!state) {
            return;
        }

        if (
            state.pointerId !== null
            && typeof event?.pointerId === 'number'
            && event.pointerId !== state.pointerId
        ) {
            return;
        }

        teardownSurfaceNodeDrag();

        if (!state.moved) {
            draggingNodeId.value = null;
            return;
        }

        const currentNode = findCanvasNode(state.nodeId);
        const finalPosition = currentNode?.position || null;

        draggingNodeId.value = null;
        suppressSurfaceNodeClickUntil = Date.now() + ACTION_CLICK_SUPPRESS_DURATION_MS;

        if (!finalPosition) {
            return;
        }

        const nextGraph = syncGraphNodePosition(draft.value.graph_json, state.nodeId, finalPosition);

        if (nextGraph !== draft.value.graph_json) {
            draft.value.graph_json = nextGraph;
            markDraftDirty();
        }
    }
    const beginNodeSurfaceDrag = (nodeId, event) => {
        if (!nodeId || !event || event.button !== 0) {
            return;
        }

        const currentNode = findCanvasNode(nodeId);

        if (!currentNode) {
            return;
        }

        const startPoint = projectPointerToCanvas(event.clientX, event.clientY);

        if (!startPoint) {
            return;
        }

        if (activeSurfaceDrag) {
            teardownSurfaceNodeDrag();
        }

        activeSurfaceDrag = {
            nodeId,
            pointerId: typeof event.pointerId === 'number' ? event.pointerId : null,
            startClient: { x: event.clientX, y: event.clientY },
            startPoint,
            startNodePosition: cloneFlowValue(currentNode.position, { x: 0, y: 0 }),
            moved: false,
        };

        moreOpen.value = false;
        activeNodeId.value = nodeId;
        suppressCanvasClickSideEffects();
        suppressSurfaceNodeClickUntil = 0;

        event.preventDefault();
        event.stopPropagation();

        if (typeof window === 'undefined') {
            return;
        }

        window.addEventListener('pointermove', handleSurfaceNodeDragMove, true);
        window.addEventListener('pointerup', finishSurfaceNodeDrag, true);
        window.addEventListener('pointercancel', finishSurfaceNodeDrag, true);
        window.addEventListener('mousemove', handleSurfaceNodeDragMove, true);
        window.addEventListener('mouseup', finishSurfaceNodeDrag, true);
    };

    return {
        beginNodeSurfaceDrag,
        openNodeSurface,
        requestNodeActionGuard: suppressCanvasClickSideEffects,
        selectNodeSurface,
        teardownSurfaceNodeDrag,
        isCanvasClickSuppressed: () => Date.now() < suppressCanvasClicksUntil,
        isSurfaceDragActive: () => Boolean(activeSurfaceDrag),
    };
};
