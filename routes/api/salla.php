<?php

use App\Http\Controllers\SallaPanelController;
use App\Http\Controllers\SallaWebhookController;
use App\Http\Controllers\SallaWidgetKeyController;
use Illuminate\Support\Facades\Route;

Route::post('/salla/webhook', SallaWebhookController::class)->name('salla.webhook');
Route::middleware('throttle:120,1')->get('/salla/widget/{merchant}', SallaWidgetKeyController::class)
    ->where('merchant', '[0-9]{1,20}');

// The merchant's panel inside Salla's dashboard: its token replaces the session cookie.
Route::middleware('throttle:120,1')->prefix('/salla/panel')->group(function () {
    Route::get('/', [SallaPanelController::class, 'page']);
    Route::post('/session', [SallaPanelController::class, 'session'])->middleware('throttle:20,1');
    Route::get('/overview', [SallaPanelController::class, 'overview']);
});
