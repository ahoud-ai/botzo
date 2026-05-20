<?php

namespace App\Http\Requests;

use App\Rules\NotRoleUser;
use App\Rules\NotUniqueEmail;
use App\Rules\UniqueEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganization extends FormRequest
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
        $rules = [
            'name' => 'required',
            'plan' => [
                Rule::requiredIf(
                    $this->input('organization_type') !== 'branch'
                    && $this->input('billing_setup_mode', 'later') === 'now'
                ),
                'nullable',
                Rule::exists('subscription_plans', 'uuid')->where(function ($query) {
                    return $query
                        ->where('status', 'active')
                        ->whereNull('deleted_at');
                }),
            ],
            'organization_type' => ['required', Rule::in(['main', 'branch'])],
            'billing_setup_mode' => [
                Rule::requiredIf($this->isMethod('post') && $this->input('organization_type') !== 'branch'),
                'nullable',
                Rule::in(['now', 'later']),
            ],
            'parent_organization_uuid' => [
                Rule::requiredIf($this->input('organization_type') === 'branch'),
                'nullable',
                Rule::exists('organizations', 'uuid')->where(function ($query) {
                    return $query->where('organization_type', 'main')->whereNull('deleted_at');
                }),
            ],
        ];

        if ($this->isMethod('post')) {
            if ($this->input('create_user') == 1) {
                $rules += [
                    'email' => ['required', 'email', new UniqueEmail],
                    'first_name' => 'required',
                    'last_name' => 'required',
                    'password' => 'required|confirmed|min:8',
                    'send_registration_email' => ['nullable', 'boolean'],
                ];
            } else {
                $rules['owner_user_id'] = ['nullable', 'integer', Rule::exists('users', 'id')->where(function ($query) {
                    return $query->where('role', 'user')->whereNull('deleted_at');
                })];
                $rules['email'] = [
                    Rule::requiredIf(!$this->filled('owner_user_id')),
                    'nullable',
                    'email',
                    new NotUniqueEmail,
                ];
            }
        }

        return $rules;
    }
}
