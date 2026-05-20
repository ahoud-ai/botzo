<?php

namespace App\Models;
use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class BillingInvoice extends Model {
    use HasFactory;
    use HasUuid;

    protected $guarded = [];
    public $timestamps = true;

    protected static array $schemaCache = [];

    public function listAll($searchTerm, $organizationId = null)
    {
        $query = $this->with(['plan', 'organization'])
            ->when($organizationId !== null, function ($query) use ($organizationId) {
                return $query->where('organization_id', $organizationId);
            });

        if (self::hasColumn('created_at')) {
            $query->orderByDesc('created_at');
        }

        return $query
            ->orderByDesc('id')
            ->paginate(10);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id', 'id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'id');
    }

    public function taxRates()
    {
        return $this->hasMany(BillingTaxRate::class, 'invoice_id', 'id');
    }

    public function payments()
    {
        if (!BillingPayment::hasColumn('invoice_id')) {
            return $this->hasMany(BillingPayment::class, 'organization_id', 'organization_id')
                ->whereRaw('1 = 0');
        }

        return $this->hasMany(BillingPayment::class, 'invoice_id', 'id');
    }

    public static function hasColumn(string $column): bool
    {
        return self::$schemaCache[$column] ??= Schema::hasColumn((new static())->getTable(), $column);
    }
}
