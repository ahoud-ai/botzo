<?php

namespace App\Http\Requests\AutomationFlows\Concerns;

trait ValidatesAutomationFlowGraphPayload
{
    protected function addAutomationFlowGraphStructureValidation($validator): void
    {
        $validator->after(function ($validator): void {
            $graph = $this->input('graph_json');

            if (! is_array($graph)) {
                return;
            }

            foreach (($graph['nodes'] ?? []) as $index => $node) {
                if (! is_array($node)) {
                    $validator->errors()->add("graph_json.nodes.{$index}", __('Each flow step must be a valid object.'));

                    continue;
                }

                if (trim((string) ($node['id'] ?? '')) === '') {
                    $validator->errors()->add("graph_json.nodes.{$index}.id", __('Each flow step needs a valid step ID.'));
                }

                if (trim((string) ($node['type'] ?? '')) === '') {
                    $validator->errors()->add("graph_json.nodes.{$index}.type", __('Each flow step needs a valid step type.'));
                }
            }

            foreach (($graph['edges'] ?? []) as $index => $edge) {
                if (! is_array($edge)) {
                    $validator->errors()->add("graph_json.edges.{$index}", __('Each flow connection must be a valid object.'));

                    continue;
                }

                $sourceId = trim((string) ($edge['source_id'] ?? $edge['source'] ?? ''));
                $targetId = trim((string) ($edge['target_id'] ?? $edge['target'] ?? ''));

                if ($sourceId === '') {
                    $validator->errors()->add("graph_json.edges.{$index}.source_id", __('Each flow connection needs a source step.'));
                }

                if ($targetId === '') {
                    $validator->errors()->add("graph_json.edges.{$index}.target_id", __('Each flow connection needs a target step.'));
                }
            }
        });
    }
}
