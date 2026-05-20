<?php

use Illuminate\Support\Facades\Route;

if (app()->environment(['local', 'testing'])) {
    Route::post('/generate-dummy-data', [App\Http\Controllers\Admin\UtilityController::class, 'generateDummyData']);
}
