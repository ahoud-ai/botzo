<?php

use Illuminate\Support\Facades\Route;

Route::get('/contact-groups', [App\Http\Controllers\ApiController::class, 'listContactGroups']);
Route::post('/contact-groups', [App\Http\Controllers\ApiController::class, 'storeContactGroup']);
Route::put('/contact-groups/{uuid}', [App\Http\Controllers\ApiController::class, 'storeContactGroup']);
Route::delete('/contact-groups/{uuid}', [App\Http\Controllers\ApiController::class, 'destroyContactGroup']);
