<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class SubscriptionPlan extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $guarded = [];
    protected $appends = ['display_name'];
    public $timestamps = true;

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'plan_id', 'id');
    }

    public function listAll($searchTerm)
    {
        $searchColumns = ['name'];
        $tableName = $this->getTable();

        foreach (['name_ar', 'name_en'] as $column) {
            if (Schema::hasColumn($tableName, $column)) {
                $searchColumns[] = $column;
            }
        }

        $query = $this->whereNull('deleted_at');

        if ($searchTerm !== null && trim((string) $searchTerm) !== '') {
            $query->where(function ($query) use ($searchTerm, $searchColumns) {
                foreach ($searchColumns as $index => $column) {
                    if ($index === 0) {
                        $query->where($column, 'like', '%' . $searchTerm . '%');
                    } else {
                        $query->orWhere($column, 'like', '%' . $searchTerm . '%');
                    }
                }
            });
        }

        return $query->latest()->paginate(10);
    }

    public function localizedName(?string $locale = null): ?string
    {
        return $this->resolveLocalizedValue($locale, $this->name_ar, $this->name_en, $this->name);
    }

    public function getDisplayNameAttribute(): ?string
    {
        return $this->localizedName();
    }

    public function metadataArray(): array
    {
        $metadata = json_decode((string) $this->metadata, true);

        return is_array($metadata) ? $metadata : [];
    }

    public function metadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadataArray()[$key] ?? $default;
    }

    public function tierRank(): int
    {
        $rank = (int) $this->metadataValue('tier_rank', 0);

        return $rank > 0 ? $rank : 0;
    }

    private function resolveLocalizedValue(?string $locale, ?string $arabicValue, ?string $englishValue, ?string $previousValue): ?string
    {
        $normalizedLocale = strtolower((string) ($locale ?? app()->getLocale() ?? 'en'));

        if (str_starts_with($normalizedLocale, 'ar')) {
            return $this->firstFilled($arabicValue, $previousValue, $englishValue);
        }

        return $this->firstFilled($englishValue, $previousValue, $arabicValue);
    }

    private function firstFilled(...$values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return $value;
            }
        }

        return null;
    }
}
