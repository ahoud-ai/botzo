<?php

use Illuminate\Support\Facades\Route;

Route::get('/languages/{language}/export', [App\Http\Controllers\Admin\LanguageController::class, 'export'])
    ->middleware('admin.permission:languages,view');

Route::post('/languages/{language}/import', [App\Http\Controllers\Admin\LanguageController::class, 'import'])
    ->middleware('admin.permission:languages,edit');

Route::get('/languages/{language}/translations', [App\Http\Controllers\Admin\LanguageController::class, 'translations'])
    ->middleware('admin.permission:languages,view');

Route::post('/languages/{language}/default', [App\Http\Controllers\Admin\LanguageController::class, 'setDefault'])
    ->middleware('admin.permission:languages,edit');

Route::resource('languages', App\Http\Controllers\Admin\LanguageController::class)
    ->middleware('admin.permission:languages');

Route::post('/translations/{languageCode}/{key}', [App\Http\Controllers\Admin\LanguageController::class, 'updateTranslation'])
    ->middleware('admin.permission:languages,edit');
