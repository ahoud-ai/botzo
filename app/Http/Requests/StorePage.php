<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePage extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $name = $this->normalizeText($this->input('name'));
        $nameAr = $this->normalizeText($this->input('name_ar'));
        $nameEn = $this->normalizeText($this->input('name_en'));

        $content = $this->normalizeText($this->input('content'));
        $contentAr = $this->normalizeText($this->input('content_ar'));
        $contentEn = $this->normalizeText($this->input('content_en'));

        $this->merge([
            'name' => $nameEn ?? $nameAr ?? $name,
            'content' => $contentEn ?? $contentAr ?? $content,
        ]);
    }

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
            'name' => [
                'nullable',
                'string',
                'max:128',
                Rule::unique('pages')->ignore($this->route('id'))
            ],
            'name_ar' => 'nullable|string|max:128|required_without_all:name,name_en',
            'name_en' => 'nullable|string|max:128|required_without_all:name,name_ar',
            'content' => 'nullable|string|max:200000',
            'content_ar' => 'nullable|string|max:200000',
            'content_en' => 'nullable|string|max:200000',
        ];

        if ($this->isMethod('put')) {
            $rules['content'] = 'nullable|string|max:200000|required_without_all:content_ar,content_en';
            $rules['content_ar'] = 'nullable|string|max:200000|required_without_all:content,content_en';
            $rules['content_en'] = 'nullable|string|max:200000|required_without_all:content,content_ar';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => __('The name has already been taken.'),
            'name_ar.required_without_all' => __('At least one name field is required.'),
            'name_en.required_without_all' => __('At least one name field is required.'),
            'content.required_without_all' => __('At least one content field is required.'),
            'content_ar.required_without_all' => __('At least one content field is required.'),
            'content_en.required_without_all' => __('At least one content field is required.'),
        ];
    }

    private function normalizeText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
