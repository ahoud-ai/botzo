<?php

namespace App\Http\Requests;

use App\Rules\AllowedPhoneCountryValidation;
use App\Rules\UniqueEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUser extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        if ($this->isMethod('put')) {
            $rules = [
                'first_name' => 'required',
                'last_name' => 'required',
                'email' => ['required', 'email', new UniqueEmail($this->route('user'))],
            ];

            $rules['phone'] = ['nullable', 'string', 'max:255'];

            if ($this->filled('phone')) {
                $rules['phone'][] = new AllowedPhoneCountryValidation;
            }

            if ($this->filled('password')) {
                $rules['password'] = 'required|confirmed|min:8';
            }

            return $rules;
        }

        $creationMode = (string) $this->input('creation_mode', ($this->has('organization_name') ? 'owner_new_org' : 'user_only'));

        $rules = [
            'creation_mode' => ['required', 'string', 'in:user_only,owner_new_org,employee_existing_org'],
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => ['required', 'email', new UniqueEmail],
            'password' => 'required|confirmed|min:8',
            'send_registration_email' => ['nullable', 'boolean'],
        ];

        $rules['phone'] = ['nullable', 'string', 'max:255'];

        if ($this->filled('phone')) {
            $rules['phone'][] = new AllowedPhoneCountryValidation;
        }

        if ($creationMode === 'owner_new_org') {
            $rules['organization_name'] = ['required', 'string', 'max:255'];
            $rules['organization_billing_mode'] = ['required', 'string', 'in:now,later'];
            $rules['organization_plan_uuid'] = [
                Rule::requiredIf($this->input('organization_billing_mode') === 'now'),
                'nullable',
                'exists:subscription_plans,uuid',
            ];
        }

        if ($creationMode === 'employee_existing_org') {
            $rules['company_uuid'] = [
                'required',
                'string',
                Rule::exists('organizations', 'uuid')->where(function ($query) {
                    return $query->where('organization_type', 'main')->whereNull('deleted_at');
                }),
            ];
            $rules['assignments'] = ['required', 'array', 'min:1'];
            $rules['assignments.*.organization_uuid'] = ['required', 'string', 'distinct'];
            $rules['assignments.*.organization_role_id'] = ['required', 'integer', 'exists:organization_roles,id'];
        }

        return $rules;
    }
}
