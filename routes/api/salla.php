<?php

use App\Http\Controllers\SallaWebhookController;
use App\Http\Controllers\SallaWidgetKeyController;
use Illuminate\Support\Facades\Route;

Route::post('/salla/webhook', SallaWebhookController::class)->name('salla.webhook');
Route::middleware('throttle:120,1')->get('/salla/widget/{merchant}', SallaWidgetKeyController::class)
    ->where('merchant', '[0-9]{1,20}');
