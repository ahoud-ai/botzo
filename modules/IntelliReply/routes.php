<?php

use Illuminate\Support\Facades\Route;
use Modules\IntelliReply\Controllers\ChatController as AiChatController;
use Modules\IntelliReply\Controllers\DocumentController;
use Modules\IntelliReply\Controllers\MainController;

Route::get('/automation/ai', [MainController::class, 'index']);
Route::post('/automation/ai/activate', [MainController::class, 'activate']);
Route::post('/automation/ai/setup', [MainController::class, 'setup']);
Route::post('/automation/ai/assistant-setup', [MainController::class, 'assistant_setup']);
Route::get('/automation/chat/suggestion', [AiChatController::class, 'suggestion']);
Route::post('/automation/contact/{uuid}', [MainController::class, 'enable_ai_assistant']);

Route::post('/automation/upload/document', [DocumentController::class, 'store']);
Route::delete('/automation/upload/document/{uuid}', [DocumentController::class, 'delete']);
