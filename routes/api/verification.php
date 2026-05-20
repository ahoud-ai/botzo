<?php

use Illuminate\Support\Facades\Route;

Route::get('/verify', [App\Http\Controllers\ApiController::class, 'verifyApiKey']);
