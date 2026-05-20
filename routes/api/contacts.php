<?php

use Illuminate\Support\Facades\Route;

Route::get('/contacts', [App\Http\Controllers\ApiController::class, 'listContacts']);
Route::post('/contacts', [App\Http\Controllers\ApiController::class, 'storeContact']);
Route::put('/contacts/{uuid}', [App\Http\Controllers\ApiController::class, 'storeContact']);
Route::delete('/contacts/{uuid}', [App\Http\Controllers\ApiController::class, 'destroyContact']);
