<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyEmployee extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.organization_uuid' => ['required', 'string', 'distinct'],
            'assignments.*.organization_role_id' => ['required', 'integer', 'exists:organization_roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'assignments.required' => __('At least one assignment is required.'),
            'assignments.min' => __('At least one assignment is required.'),
            'assignments.*.organization_uuid.required' => __('Select a workspace for each assignment.'),
            'assignments.*.organization_uuid.distinct' => __('Each workspace can only be assigned once.'),
            'assignments.*.organization_role_id.required' => __('Select a role for each assignment.'),
        ];
    }
}
