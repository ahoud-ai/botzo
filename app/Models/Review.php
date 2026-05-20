<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Review extends Model {
    use HasFactory;

    protected $guarded = [];
    public $timestamps = true;

    public function listAll($searchTerm)
    {
        $searchColumns = ['name', 'position', 'review'];
        $optionalColumns = ['name_ar', 'name_en', 'position_ar', 'position_en', 'review_ar', 'review_en'];
        $tableName = $this->getTable();

        foreach ($optionalColumns as $column) {
            if (Schema::hasColumn($tableName, $column)) {
                $searchColumns[] = $column;
            }
        }

        $query = $this->newQuery();

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

        $query = $query->latest()->paginate(10);

        return $query;
    }

    public function localizedName(?string $locale = null): ?string
    {
        return $this->resolveLocalizedValue($locale, $this->name_ar, $this->name_en, $this->name);
    }

    public function localizedPosition(?string $locale = null): ?string
    {
        return $this->resolveLocalizedValue($locale, $this->position_ar, $this->position_en, $this->position);
    }

    public function localizedReview(?string $locale = null): ?string
    {
        return $this->resolveLocalizedValue($locale, $this->review_ar, $this->review_en, $this->review);
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
