<?php

use Illuminate\Support\Facades\Route;

Route::get('/developer-tools/access-tokens', [App\Http\Controllers\User\DeveloperController::class, 'index']);
Route::post('/developer-tools/access-tokens', [App\Http\Controllers\User\DeveloperController::class, 'store']);
Route::post('/developer-tools/access-tokens/{uuid}/rotate', [App\Http\Controllers\User\DeveloperController::class, 'rotate']);
Route::delete('/developer-tools/access-tokens/{uuid}', [App\Http\Controllers\User\DeveloperController::class, 'delete']);

Route::post('/developer-tools/event-hooks', [App\Http\Controllers\User\DeveloperWebhookController::class, 'store']);
Route::put('/developer-tools/event-hooks/{uuid}', [App\Http\Controllers\User\DeveloperWebhookController::class, 'update']);
Route::post('/developer-tools/event-hooks/{uuid}/rotate', [App\Http\Controllers\User\DeveloperWebhookController::class, 'rotate']);
Route::post('/developer-tools/event-hooks/{uuid}/test', [App\Http\Controllers\User\DeveloperWebhookController::class, 'test']);
Route::delete('/developer-tools/event-hooks/{uuid}', [App\Http\Controllers\User\DeveloperWebhookController::class, 'destroy']);
Route::get('/developer-tools/webhook-inspector', [App\Http\Controllers\User\DeveloperWebhookController::class, 'inspector']);
