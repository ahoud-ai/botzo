<?php

namespace App\Models;

use App\Helpers\DateTimeHelper;
use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class BillingPayment extends Model {
    use HasFactory;
    use HasUuid;

    protected $guarded = [];
    public $timestamps = true;

    protected static array $schemaCache = [];

    public function listAll($searchTerm, $organizationId = null)
    {
        $query = $this->with(['organization'])
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

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'id');
    }

    public function invoice()
    {
        if (!self::hasColumn('invoice_id')) {
            return $this->belongsTo(BillingInvoice::class, 'organization_id', 'organization_id')
                ->whereRaw('1 = 0');
        }

        return $this->belongsTo(BillingInvoice::class, 'invoice_id', 'id');
    }

    public static function hasColumn(string $column): bool
    {
        return self::$schemaCache[$column] ??= Schema::hasColumn((new static())->getTable(), $column);
    }
}
