<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFaq extends FormRequest
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
            'question' => 'nullable|string|required_without_all:question_ar,question_en',
            'question_ar' => 'nullable|string|required_without_all:question,question_en',
            'question_en' => 'nullable|string|required_without_all:question,question_ar',
            'answer' => 'nullable|string|required_without_all:answer_ar,answer_en',
            'answer_ar' => 'nullable|string|required_without_all:answer,answer_en',
            'answer_en' => 'nullable|string|required_without_all:answer,answer_ar',
            'status' => 'required|numeric',
        ];

        return $rules;
    }
}
