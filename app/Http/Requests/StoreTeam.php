<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\OrganizationRole;

class StoreTeam extends FormRequest
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
        return [
            'email' => [
                'required',
                'email', // Added the email validation rule
                Rule::unique('users', 'email')->where(function ($query) {
                    $organizationId = session()->get('current_organization');

                    $query->whereIn('id', function ($subQuery) use ($organizationId) {
                        $subQuery->select('user_id')
                            ->from('teams')
                            ->where('deleted_at', null)
                            ->where('organization_id', $organizationId);
                    });
                }),
            ],
            'organization_role_id' => 'required|exists:organization_roles,id'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $organizationId = (int) session()->get('current_organization');
            $roleId = (int) $this->input('organization_role_id');

            if ($organizationId <= 0 || $roleId <= 0) {
                return;
            }

            $selectedRole = OrganizationRole::query()->find($roleId);

            if ($selectedRole?->isOwnerRole()) {
                $validator->errors()->add(
                    'organization_role_id',
                    __('The Owner role can only be assigned automatically when the workspace is created.')
                );

                return;
            }

            $isAllowedRole = OrganizationRole::query()
                ->where('id', $roleId)
                ->where('organization_id', $organizationId)
                ->exists();

            if (! $isAllowedRole) {
                $validator->errors()->add(
                    'organization_role_id',
                    __('The selected role is not available for this organization.')
                );
            }
        });
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'email.unique' => __('User with this email already exists in your team.'),
        ];
    }
}
