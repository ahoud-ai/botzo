<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBillingTransaction extends FormRequest
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
            'uuid' => ['required', 'exists:organizations,uuid'],
            'type' => ['required', Rule::in(['credit', 'debit', 'payment'])],
            'amount' => ['required', 'numeric', 'gt:0'],
        ];

        if ($this->input('type') == 'payment') {
            $rules['method'] = ['required', Rule::in(['manual', 'bank'])];
        } else {
            $rules['description'] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }
}
