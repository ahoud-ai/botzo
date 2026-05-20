<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class LoginRequest extends FormRequest
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
            'email' => [
                'required',
                'email',
            ],
            'password' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (blank($this->email) || blank($value)) {
                        return;
                    }

                    $user = User::where('email', $this->email)
                        ->where('status', '1')
                        ->whereNull('deleted_at')
                        ->first();

                    if (!$user || !Hash::check($value, $user->getAuthPassword())) {
                        return $fail(__('The provided credentials are incorrect.'));
                    }
                },
            ],
        ];

        return $rules;
    }
}
