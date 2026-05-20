<?php

use Illuminate\Support\Facades\Route;

Route::post('/campaigns', [App\Http\Controllers\ApiController::class, 'storeCampaign']);
