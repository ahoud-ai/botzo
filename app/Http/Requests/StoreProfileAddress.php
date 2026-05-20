<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProfileAddress extends FormRequest
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
        $organizationId = (int) ($this->session()->get('current_organization') ?? 0);
        $allowedCountries = collect(config('formats.countries', []))
            ->pluck('value')
            ->filter()
            ->values()
            ->all();

        $allowedTimezones = collect(config('formats.timezones', []))
            ->pluck('value')
            ->filter()
            ->values()
            ->all();

        $rules = [
            'organization_name' => 'required',
            'address' => 'required',
            'country' => ['required', Rule::in($allowedCountries)],
            'state' => 'required',
            'zip' => 'required',
            'city' => 'required',
            'timezone' => ['required', Rule::in($allowedTimezones)],
            'enable_campaign_resend' => 'boolean',
            'move_failed_contacts_to_group' => 'boolean',
            'resend_intervals' => 'nullable|array',
            'resend_intervals.*' => 'integer|min:1|max:24',
            'failed_campaign_group' => [
                'required_if:move_failed_contacts_to_group,true',
                'nullable',
                Rule::exists('contact_groups', 'uuid')->where(function ($query) use ($organizationId) {
                    $query->where('organization_id', $organizationId)
                        ->whereNull('deleted_at');
                }),
            ],
        ];

        return $rules;
    }
}
