<?php

use Illuminate\Support\Facades\Route;

Route::get('/support/{uuid?}', [App\Http\Controllers\User\TicketController::class, 'index'])->name('support');
Route::post('/support', [App\Http\Controllers\User\TicketController::class, 'store']);
Route::post('/support/{uuid}/comment', [App\Http\Controllers\User\TicketController::class, 'comment']);
Route::post('/support/{uuid}/status', [App\Http\Controllers\User\TicketController::class, 'changeStatus']);
Route::post('/support/{uuid}/priority', [App\Http\Controllers\User\TicketController::class, 'changePriority']);

Route::match(['get', 'post'], '/messages', [App\Http\Controllers\User\MessageController::class, 'index']);
Route::match(['get', 'post'], '/message-templates', [App\Http\Controllers\User\TemplateController::class, 'index']);
Route::match(['get', 'post'], '/instances', [App\Http\Controllers\User\InstanceController::class, 'index']);
