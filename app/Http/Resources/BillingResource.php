<?php

namespace App\Http\Resources;

use App\Helpers\DateTimeHelper;
use App\Models\BillingPayment;
use App\Support\BillingPaymentMethodResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $data['amount'] = number_format($this->amount, 2);

        if ($this->entity_type === 'payment') {
            $payment = BillingPayment::query()->find($this->entity_id);
            $methodLabel = __(BillingPaymentMethodResolver::displayLabel($payment?->payment_method, $payment?->processor));

            $data['description'] = __('Payment via :method', [
                'method' => $methodLabel,
            ]);
        }

        return $data;
    }
}
