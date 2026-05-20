<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Page extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $appends = ['display_name', 'slug'];

    public $timestamps = true;

    public function listAll($searchTerm)
    {
        $searchColumns = ['name'];
        $optionalColumns = ['name_ar', 'name_en', 'content', 'content_ar', 'content_en'];
        $tableName = $this->getTable();

        foreach ($optionalColumns as $column) {
            if (Schema::hasColumn($tableName, $column)) {
                $searchColumns[] = $column;
            }
        }

        $query = $this->newQuery();
        $searchTerm = trim((string) $searchTerm);

        if ($searchTerm !== '') {
            $query->where(function ($builder) use ($searchColumns, $searchTerm) {
                foreach ($searchColumns as $index => $column) {
                    if ($index === 0) {
                        $builder->where($column, 'like', '%' . $searchTerm . '%');
                    } else {
                        $builder->orWhere($column, 'like', '%' . $searchTerm . '%');
                    }
                }
            });
        }

        return $query
            ->orderBy('updated_at', 'asc')
            ->paginate(10);
    }

    public function localizedName(?string $locale = null): ?string
    {
        return $this->resolveLocalizedValue($locale, $this->name_ar, $this->name_en, $this->name);
    }

    public function localizedContent(?string $locale = null): ?string
    {
        return $this->resolveLocalizedValue($locale, $this->content_ar, $this->content_en, $this->content);
    }

    public function localizedSlug(?string $locale = null): string
    {
        return $this->normalizeSlug($this->localizedName($locale));
    }

    public function matchesSlug(string $slug): bool
    {
        $normalizedSlug = $this->normalizeSlug(urldecode($slug));

        if ($normalizedSlug === '') {
            return false;
        }

        $candidates = [
            $this->name,
            $this->name_ar,
            $this->name_en,
        ];

        foreach ($candidates as $candidate) {
            if ($this->normalizeSlug($candidate) === $normalizedSlug) {
                return true;
            }
        }

        return false;
    }

    public function getDisplayNameAttribute(): ?string
    {
        return $this->localizedName();
    }

    public function getSlugAttribute(): string
    {
        return $this->localizedSlug();
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
                return (string) $value;
            }
        }

        return null;
    }

    private function normalizeSlug(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $lower = function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);

        $slug = preg_replace('/\s+/u', '-', $lower);

        return trim((string) $slug, '-');
    }
}
