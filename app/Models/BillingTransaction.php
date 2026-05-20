<?php

namespace App\Models;

use App\Helpers\DateTimeHelper;
use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingTransaction extends Model {
    use HasFactory;
    use HasUuid;

    protected $guarded = [];
    public $timestamps = true;

    public function getCreatedAtAttribute($value)
    {
        return DateTimeHelper::convertToOrganizationTimezone($value)->toDateTimeString();
    }

    public function getUpdatedAtAttribute($value)
    {
        return DateTimeHelper::convertToOrganizationTimezone($value)->toDateTimeString();
    }

    public function listAll($searchTerm = null, $organizationId = null, $dateFrom = null, $dateTo = null)
    {
        return $this->whereHas('organization', function ($query) {
                        $query->whereNull('deleted_at');
                    })
                    ->with(['organization' => function ($query) {
                        $query->whereNull('deleted_at');
                    }])
                    ->when($organizationId !== null, function ($query) use ($organizationId) {
                        return $query->where('organization_id', $organizationId);
                    })
                    ->when($dateFrom, function ($query) use ($dateFrom) {
                        return $query->whereDate('created_at', '>=', $dateFrom);
                    })
                    ->when($dateTo, function ($query) use ($dateTo) {
                        return $query->whereDate('created_at', '<=', $dateTo);
                    })
                    ->when($searchTerm, function ($query) use ($searchTerm) {
                        $query->where(function ($searchQuery) use ($searchTerm) {
                            $searchQuery
                                ->where('description', 'like', '%' . $searchTerm . '%')
                                ->orWhereHas('organization', function ($organizationQuery) use ($searchTerm) {
                                    $organizationQuery->where('name', 'like', '%' . $searchTerm . '%');
                                });
                        });
                    })
                    ->latest()
                    ->paginate(10)
                    ->withQueryString();
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'id');
    }
}
