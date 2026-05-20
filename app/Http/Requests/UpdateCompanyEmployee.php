<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyEmployee extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'email' => ['required', 'email'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.organization_uuid' => ['required', 'string', 'distinct'],
            'assignments.*.organization_role_id' => ['required', 'integer', 'exists:organization_roles,id'],
        ];

        if ($this->filled('password')) {
            $rules['password'] = ['required', 'confirmed', 'min:8'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'assignments.required' => __('At least one assignment is required.'),
            'assignments.min' => __('At least one assignment is required.'),
            'assignments.*.organization_uuid.required' => __('Select a workspace for each assignment.'),
            'assignments.*.organization_uuid.distinct' => __('Each workspace can only be assigned once.'),
            'assignments.*.organization_role_id.required' => __('Select a role for each assignment.'),
            'password.confirmed' => __('Password confirmation does not match.'),
        ];
    }
}
