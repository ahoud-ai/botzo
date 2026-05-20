<?php

namespace App\Http\Requests;

use App\Models\Coupon;
use App\Models\SubscriptionPlan;
use App\Services\PaymentProcessorAvailabilityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan' => [
                'required',
                'integer',
                Rule::exists('subscription_plans', 'id')->where(function ($query) {
                    return $query
                        ->where('status', 'active')
                        ->whereNull('deleted_at');
                }),
            ],
            'method' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if ($value === null || trim((string) $value) === '') {
                        return;
                    }

                    try {
                        app(PaymentProcessorAvailabilityService::class)->assertAvailable((string) $value);
                    } catch (\InvalidArgumentException $exception) {
                        $fail($exception->getMessage());
                    }
                },
            ],
            'coupon' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    $code = trim((string) $value);
                    if ($code === '') {
                        return;
                    }

                    $coupon = Coupon::query()
                        ->where('code', $code)
                        ->where('status', 'active')
                        ->whereNull('deleted_at')
                        ->first();

                    if (!$coupon) {
                        $fail(__('The coupon code is invalid!'));

                        return;
                    }

                    if ($coupon->quantity !== null && (int) ($coupon->quantity_redeemed ?? 0) >= (int) $coupon->quantity) {
                        $fail(__('The coupon has expired!'));
                    }
                },
            ],
        ];
    }
}
