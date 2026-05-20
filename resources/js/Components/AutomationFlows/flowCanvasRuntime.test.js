import test from 'node:test';
import assert from 'node:assert/strict';
import { buildCanvasEdgeId, syncGraphNodePosition } from './flowCanvasRuntime.js';

test('buildCanvasEdgeId prefers explicit ids and falls back to source-branch-target', () => {
    assert.equal(buildCanvasEdgeId({ id: 'edge-123', source_id: 'a', target_id: 'b', branch: 'default' }), 'edge-123');
    assert.equal(buildCanvasEdgeId({ source_id: 'node-a', target_id: 'node-b', branch: 'matched' }), 'edge-node-a-matched-node-b');
});

test('syncGraphNodePosition only updates the targeted graph node', () => {
    const sourceGraph = {
        start_node_id: 'trigger-1',
        nodes: [
            { id: 'trigger-1', type: 'trigger', position: { x: 140, y: 220 }, config: {} },
            { id: 'node-2', type: 'send_text', position: { x: 420, y: 220 }, config: {} },
        ],
        edges: [],
    };

    const nextGraph = syncGraphNodePosition(sourceGraph, 'node-2', { x: 512, y: 318 });

    assert.notEqual(nextGraph, sourceGraph);
    assert.deepEqual(nextGraph.nodes[0].position, { x: 140, y: 220 });
    assert.deepEqual(nextGraph.nodes[1].position, { x: 512, y: 318 });
    assert.equal(nextGraph.nodes[0], sourceGraph.nodes[0]);
});

test('syncGraphNodePosition returns the original graph when nothing changes', () => {
    const sourceGraph = {
        nodes: [
            { id: 'node-1', position: { x: 240, y: 180 } },
        ],
        edges: [],
    };

    const nextGraph = syncGraphNodePosition(sourceGraph, 'node-1', { x: 240, y: 180 });

    assert.equal(nextGraph, sourceGraph);
});
