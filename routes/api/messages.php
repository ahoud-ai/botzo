<?php

use Illuminate\Support\Facades\Route;

Route::middleware(App\Http\Middleware\DeveloperApiIdempotency::class)->group(function () {
    Route::post('/send', [App\Http\Controllers\ApiController::class, 'sendMessage']);
    Route::post('/send/template', [App\Http\Controllers\ApiController::class, 'sendTemplateMessage']);
    Route::post('/send/media', [App\Http\Controllers\ApiController::class, 'sendMediaMessage']);
});
Route::get('/messages/{messageId}', [App\Http\Controllers\DeveloperMessageController::class, 'show']);
