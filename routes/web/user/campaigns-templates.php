<?php

use Illuminate\Support\Facades\Route;

Route::get('/campaigns/{uuid?}', [App\Http\Controllers\User\CampaignController::class, 'index'])->name('campaigns');
Route::post('/campaigns', [App\Http\Controllers\User\CampaignController::class, 'store']);
Route::get('/campaigns/export/{uuid?}', [App\Http\Controllers\User\CampaignController::class, 'export']);
Route::delete('/campaigns/{uuid?}', [App\Http\Controllers\User\CampaignController::class, 'delete']);

Route::match(['get', 'post'], '/templates/create', [App\Http\Controllers\User\TemplateController::class, 'create']);
Route::get('/templates/{uuid?}', [App\Http\Controllers\User\TemplateController::class, 'index']);
Route::post('/templates', [App\Http\Controllers\User\TemplateController::class, 'store']);
Route::post('/templates/{uuid}', [App\Http\Controllers\User\TemplateController::class, 'update']);
Route::delete('/templates/{uuid}', [App\Http\Controllers\User\TemplateController::class, 'delete']);
