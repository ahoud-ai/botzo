<?php

namespace App\Models;
use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingTaxRate extends Model {
    use HasFactory;
    use HasUuid;

    protected $guarded = [];
    public $timestamps = false;

    public function invoice()
    {
        return $this->belongsTo(BillingInvoice::class, 'invoice_id', 'id');
    }
}
