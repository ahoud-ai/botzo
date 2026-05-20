<?php

namespace App\Http\Requests\AutomationFlows;

use App\Services\AutomationFlows\AutomationFlowStarterTemplateService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAutomationFlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'goal_preset' => [
                'nullable',
                'string',
                'max:80',
                Rule::in(AutomationFlowStarterTemplateService::supportedGoalPresets()),
            ],
        ];
    }
}
