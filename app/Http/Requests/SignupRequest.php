<?php

namespace App\Http\Requests;

use App\Rules\UniqueEmail;
use App\Rules\AllowedPhoneCountryValidation;
use Illuminate\Foundation\Http\FormRequest;

class SignupRequest extends FormRequest
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
            'first_name' => 'required',
            'last_name' => 'required',
        ];

        if ($this->isMethod('put')) {
            $rules['email'] = ['required', 'email', new UniqueEmail($this->route('user'))];
        } else {
            $rules['email'] = ['required', 'email', new UniqueEmail];

            if ($this->has('organization_name')) {
                $rules['organization_name'] = 'required';
            }

            if ($this->has('phone')) {
                $rules['phone'] = [
                    'required',
                    'string',
                    'max:255',
                    new AllowedPhoneCountryValidation,
                ];
            }
        }

        // Only require password if it exists in the request
        if ($this->has('password')) {
            $rules['password'] = 'required|confirmed';
        }

        return $rules;
    }
}
