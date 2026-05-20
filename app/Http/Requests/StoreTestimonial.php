<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTestimonial extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $position = $this->input('position');
        $review = $this->input('review');

        $this->merge([
            'name_ar' => $this->input('name_ar') ?? ($name !== null ? $name : null),
            'name_en' => $this->input('name_en') ?? ($name !== null ? $name : null),
            'position_ar' => $this->input('position_ar') ?? ($position !== null ? $position : null),
            'position_en' => $this->input('position_en') ?? ($position !== null ? $position : null),
            'review_ar' => $this->input('review_ar') ?? ($review !== null ? $review : null),
            'review_en' => $this->input('review_en') ?? ($review !== null ? $review : null),
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
            'name' => 'nullable|string|max:255',
            'name_ar' => 'nullable|string|max:255|required_without:name_en',
            'name_en' => 'nullable|string|max:255|required_without:name_ar',
            'position' => 'nullable|string|max:255',
            'position_ar' => 'nullable|string|max:255|required_without:position_en',
            'position_en' => 'nullable|string|max:255|required_without:position_ar',
            'review' => 'nullable|string',
            'review_ar' => 'nullable|string|required_without:review_en',
            'review_en' => 'nullable|string|required_without:review_ar',
            'rating' => 'required|integer|min:1|max:5',
            'status' => 'required|in:0,1',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ];

        return $rules;
    }
}
