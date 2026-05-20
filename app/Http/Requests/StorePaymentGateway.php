<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentGateway extends FormRequest
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
        $type = strtolower($this->route('payment_gateway'));
        $statusRule = ['required', Rule::in(['0', '1', 0, 1])];

        if ($type !== 'moyasar') {
            return [];
        }

        $usesStructuredPayload = $this->has('active_mode') || $this->has('test') || $this->has('live');
        $isActive = in_array($this->input('status'), ['1', 1, true], true);

        if ($usesStructuredPayload) {
            $activeMode = strtolower((string) $this->input('active_mode', 'test'));

            return [
                'active_mode' => ['required', Rule::in(['test', 'live'])],
                'status' => $statusRule,
                'test.publishable_key' => [Rule::requiredIf($isActive && $activeMode === 'test'), 'nullable', 'string'],
                'test.secret_key' => [Rule::requiredIf($isActive && $activeMode === 'test'), 'nullable', 'string'],
                'test.webhook_secret' => [Rule::requiredIf($isActive && $activeMode === 'test'), 'nullable', 'string'],
                'live.publishable_key' => [Rule::requiredIf($isActive && $activeMode === 'live'), 'nullable', 'string'],
                'live.secret_key' => [Rule::requiredIf($isActive && $activeMode === 'live'), 'nullable', 'string'],
                'live.webhook_secret' => [Rule::requiredIf($isActive && $activeMode === 'live'), 'nullable', 'string'],
            ];
        }

        return [
            'publishable_key' => [Rule::requiredIf($isActive), 'nullable', 'string'],
            'secret_key' => [Rule::requiredIf($isActive), 'nullable', 'string'],
            'webhook_secret' => [Rule::requiredIf($isActive), 'nullable', 'string'],
            'mode' => ['required', Rule::in(['test', 'live'])],
            'status' => $statusRule,
        ];
    }
}
