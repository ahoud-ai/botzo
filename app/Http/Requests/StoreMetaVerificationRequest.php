<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMetaVerificationRequest extends FormRequest
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
            'full_name' => 'required|string|max:191',
            'business_name' => 'required|string|max:191',
            'legal_company_name' => 'required|string|max:191',
            'commercial_register_number' => 'nullable|string|max:60',
            'phone' => 'required|string|max:30',
            'whatsapp_number' => 'required|string|max:30',
            'email' => 'required|email|max:191',
            'website_url' => 'nullable|url|max:255',
            'notes' => 'nullable|string|max:2000',
            'commercial_register_document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'id_proof_document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'ownership_proof_document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];
    }
}
