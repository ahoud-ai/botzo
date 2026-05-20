<?php

namespace App\Http\Requests\AutomationFlows;

use App\Http\Requests\AutomationFlows\Concerns\ValidatesAutomationFlowGraphPayload;
use Illuminate\Foundation\Http\FormRequest;

class SaveAutomationFlowRequest extends FormRequest
{
    use ValidatesAutomationFlowGraphPayload;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'graph_json' => ['required', 'array'],
            'graph_json.nodes' => ['required', 'array'],
            'graph_json.nodes.*' => ['array'],
            'graph_json.nodes.*.id' => ['nullable', 'string'],
            'graph_json.nodes.*.type' => ['nullable', 'string'],
            'graph_json.nodes.*.position' => ['nullable', 'array'],
            'graph_json.nodes.*.config' => ['nullable', 'array'],
            'graph_json.edges' => ['required', 'array'],
            'graph_json.edges.*' => ['array'],
            'graph_json.edges.*.source_id' => ['nullable', 'string'],
            'graph_json.edges.*.source' => ['nullable', 'string'],
            'graph_json.edges.*.target_id' => ['nullable', 'string'],
            'graph_json.edges.*.target' => ['nullable', 'string'],
            'graph_json.edges.*.branch' => ['nullable', 'string'],
            'graph_json.start_node_id' => ['nullable', 'string'],
            'ui_json' => ['nullable', 'array'],
            'node_secrets' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $this->addAutomationFlowGraphStructureValidation($validator);
    }
}
