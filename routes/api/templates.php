<?php

use Illuminate\Support\Facades\Route;

Route::get('/templates', [App\Http\Controllers\ApiController::class, 'listTemplates']);
