<?php

namespace App\Services\AutomationFlows;

use Illuminate\Support\Arr;

class AutomationFlowGraphCompiler
{
    public function compile(array $graph): array
    {
        $nodes = collect(Arr::get($graph, 'nodes', []))
            ->filter(fn ($node) => is_array($node))
            ->map(function (array $node): array {
                return [
                    'id' => trim((string) ($node['id'] ?? '')),
                    'type' => trim((string) ($node['type'] ?? '')),
                    'config' => is_array(Arr::get($node, 'config', [])) ? Arr::get($node, 'config', []) : [],
                    'position' => is_array(Arr::get($node, 'position', [])) ? Arr::get($node, 'position', []) : [],
                ];
            })
            ->filter(fn (array $node) => $node['id'] !== '' && $node['type'] !== '')
            ->mapWithKeys(fn ($node) => [
                $node['id'] => [
                    'id' => $node['id'],
                    'type' => $node['type'],
                    'config' => $node['config'],
                    'position' => $node['position'],
                ],
            ])
            ->all();

        $edges = collect(Arr::get($graph, 'edges', []))
            ->filter(fn ($edge) => is_array($edge))
            ->map(function (array $edge) {
                return [
                    'id' => $edge['id'] ?? null,
                    'source_id' => trim((string) ($edge['source_id'] ?? $edge['source'] ?? '')),
                    'target_id' => trim((string) ($edge['target_id'] ?? $edge['target'] ?? '')),
                    'branch' => trim((string) ($edge['branch'] ?? $edge['sourceHandle'] ?? 'default')),
                ];
            })
            ->filter(fn (array $edge) => filled($edge['source_id']) && filled($edge['target_id']))
            ->filter(fn (array $edge) => isset($nodes[$edge['source_id']]) && isset($nodes[$edge['target_id']]))
            ->values();

        $startNodeId = trim((string) Arr::get($graph, 'start_node_id', ''));
        if ($startNodeId === '' || ! isset($nodes[$startNodeId])) {
            $startNodeId = array_key_first($nodes);
        }

        return [
            'start_node_id' => $startNodeId,
            'nodes' => $nodes,
            'edges' => $edges->all(),
            'adjacency' => $edges->groupBy('source_id')->map(fn ($group) => $group->values()->all())->all(),
        ];
    }
}
