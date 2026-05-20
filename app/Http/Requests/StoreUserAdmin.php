<?php

namespace App\Http\Requests;

use App\Rules\AllowedPhoneCountryValidation;
use App\Rules\RoleExists;
use App\Rules\UniqueEmail;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserAdmin extends FormRequest
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
            'role' => 'required',
            'phone' => ['nullable', 'string', 'max:255'],
        ];

        if ($this->isMethod('post')) {
            $rules['email'] = ['required', 'email', new UniqueEmail];
            $rules['password'] = 'required|confirmed|min:8';
        } elseif ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules['email'] = ['required', 'email', new UniqueEmail($this->route('user'))];

            if ($this->filled('password')) {
                $rules['password'] = 'required|confirmed|min:8';
            }
        }

        if ($this->filled('phone')) {
            $rules['phone'][] = new AllowedPhoneCountryValidation;
        }

        return $rules;
    }
}
