<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingCheckoutIntent extends Model
{
    use HasFactory;
    use HasUuid;

    protected $guarded = [];

    protected $casts = [
        'snapshot_json' => 'array',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'base_price' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'net_total' => 'decimal:2',
        'balance_applied' => 'decimal:2',
        'amount_due' => 'decimal:2',
        'coupon_amount' => 'decimal:2',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function billingOrganization()
    {
        return $this->belongsTo(Organization::class, 'billing_organization_id');
    }

    public function targetPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'target_plan_id');
    }

    public function payment()
    {
        return $this->belongsTo(BillingPayment::class, 'completed_payment_id');
    }

    public function invoice()
    {
        return $this->belongsTo(BillingInvoice::class, 'completed_invoice_id');
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }
}
