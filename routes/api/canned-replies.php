<?php

use Illuminate\Support\Facades\Route;

Route::get('/canned-replies', [App\Http\Controllers\ApiController::class, 'listCannedReplies']);
Route::post('/canned-replies', [App\Http\Controllers\ApiController::class, 'storeCannedReply']);
Route::put('/canned-replies/{uuid}', [App\Http\Controllers\ApiController::class, 'storeCannedReply']);
Route::delete('/canned-replies/{uuid}', [App\Http\Controllers\ApiController::class, 'destroyCannedReply']);
