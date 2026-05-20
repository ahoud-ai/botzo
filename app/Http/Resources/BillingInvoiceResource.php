<?php

namespace App\Http\Resources;

use App\Helpers\DateTimeHelper;
use App\Services\BillingInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $invoiceService = app(BillingInvoiceService::class);
        $payment = $invoiceService->resolvePaymentRecord($this->resource);

        return [
            'uuid' => $this->uuid,
            'invoice_number' => $invoiceService->invoiceNumber($this->resource),
            'organization_name' => $this->organization?->name,
            'plan_name' => $this->plan?->localizedName(app()->getLocale())
                ?? $this->plan?->name
                ?? __('Not set'),
            'issued_at' => $this->getRawOriginal('created_at')
                ? DateTimeHelper::formatDate($this->getRawOriginal('created_at'))
                : __('Not set'),
            'subtotal' => $invoiceService->formatAmount($this->subtotal),
            'tax' => $invoiceService->formatAmount($this->tax),
            'total' => $invoiceService->formatAmount($this->total),
            'status_label' => $invoiceService->invoiceStatusLabel($this->resource, $payment),
            'payment_method_label' => $invoiceService->paymentMethodLabel($this->resource, $payment),
            'payment_reference' => $payment?->details,
        ];
    }
}
