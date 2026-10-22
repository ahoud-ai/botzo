<?php

use App\Http\Controllers\AssistantApiController;
use Illuminate\Support\Facades\Route;

// Mochi API: conversations with the organization's AI assistant from apps and websites.
Route::prefix('v1/assistant')->group(function () {
    Route::post('/conversations', [AssistantApiController::class, 'open']);
    Route::post('/conversations/{conversation}/messages', [AssistantApiController::class, 'send']);
    Route::get('/conversations/{conversation}/messages', [AssistantApiController::class, 'messages']);
});
