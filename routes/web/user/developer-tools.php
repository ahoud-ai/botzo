<?php

use Illuminate\Support\Facades\Route;

Route::get('/developer-tools/access-tokens', [App\Http\Controllers\User\DeveloperController::class, 'index']);
Route::post('/developer-tools/access-tokens', [App\Http\Controllers\User\DeveloperController::class, 'store']);
Route::post('/developer-tools/access-tokens/{uuid}/rotate', [App\Http\Controllers\User\DeveloperController::class, 'rotate']);
Route::delete('/developer-tools/access-tokens/{uuid}', [App\Http\Controllers\User\DeveloperController::class, 'delete']);
