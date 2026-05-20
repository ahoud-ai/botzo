<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/translations/{locale}', function ($locale) {
    $locale = strtolower($locale);
    $supported = collect(config('i18n.supported_locales', ['en', 'ar']))
        ->map(fn ($value) => strtolower($value))
        ->values();

    if (Str::startsWith($locale, 'php_')) {
        return response()->json(['error' => __('Invalid locale')], 400);
    }

    if (! $supported->contains($locale)) {
        return response()->json(['error' => __('Locale not supported')], 404);
    }

    $path = base_path("lang/{$locale}.json");

    if (! File::exists($path)) {
        return response()->json(['error' => __('Translation file not found')], 404);
    }

    return response()->json(json_decode(File::get($path), true));
});
