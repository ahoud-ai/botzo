<?php

namespace App\Http\Requests;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreWhatsappProfile extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The limits are the ones Meta documents for whatsapp_business_profile.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'profile_picture_url' => [],
            'about' => ['nullable', 'string', 'max:139'],
            'address' => ['nullable', 'string', 'max:256'],
            'description' => ['nullable', 'string', 'max:512'],
            'email' => ['nullable', 'email', 'max:128'],
            'industry' => ['nullable', 'string'],
            'websites' => ['nullable', 'array', 'max:2'],
            'websites.*' => ['nullable', 'url:http,https', 'max:256'],
        ];
    }

    protected function withValidator($validator)
    {
        if ($this->hasFile('profile_picture_url')) {
            $validator->addRules([
                'profile_picture_url' => [
                    'file',     // Validates it as a file
                    'image',    // Ensures it is an image
                    'mimes:jpg,png', // Restricts file types to JPG and PNG
                    'dimensions:min_width=192,min_height=192', // Validates dimensions
                    'max:5120', // Same 5 MB limit as the upload field
                ],
            ]);
        }

        $validator->after(function (Validator $validator): void {
            if ($this->has('about') && ! $this->filled('about') && $this->savedAbout() !== '') {
                $validator->errors()->add('about', __('WhatsApp does not allow an empty about text. Write a new one instead.'));
            }
        });
    }

    private function savedAbout(): string
    {
        $metadata = Organization::where('id', session()->get('current_organization'))->value('metadata');

        return (string) data_get(json_decode((string) $metadata, true), 'whatsapp.business_profile.about', '');
    }
}
