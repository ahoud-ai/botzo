<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasFactory;

    protected $guarded = [];
    public $timestamps = false;

    private function resolveLocalizedValue(string $locale, ?string $arabic, ?string $english, ?string $fallback): ?string
    {
        $arabic = is_string($arabic) && trim($arabic) !== '' ? $arabic : null;
        $english = is_string($english) && trim($english) !== '' ? $english : null;
        $fallback = is_string($fallback) && trim($fallback) !== '' ? $fallback : null;

        if ($locale === 'ar') {
            return $arabic ?? $english ?? $fallback;
        }

        return $english ?? $arabic ?? $fallback;
    }

    public function localizedQuestion(?string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();

        return $this->resolveLocalizedValue($locale, $this->question_ar, $this->question_en, $this->question);
    }

    public function localizedAnswer(?string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();

        return $this->resolveLocalizedValue($locale, $this->answer_ar, $this->answer_en, $this->answer);
    }

    public function listAll($searchTerm)
    {
        $query = $this->where(function ($query) use ($searchTerm) {
                $query->where('question', 'like', '%' . $searchTerm . '%')
                    ->orWhere('question_ar', 'like', '%' . $searchTerm . '%')
                    ->orWhere('question_en', 'like', '%' . $searchTerm . '%')
                    ->orWhere('answer', 'like', '%' . $searchTerm . '%')
                    ->orWhere('answer_ar', 'like', '%' . $searchTerm . '%')
                    ->orWhere('answer_en', 'like', '%' . $searchTerm . '%');
            })
            ->latest()
            ->paginate(10);

        return $query;
    }
}
