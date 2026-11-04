<?php

use App\Http\Controllers\SallaWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/salla/webhook', SallaWebhookController::class)->name('salla.webhook');
