<?php

use Illuminate\Support\Facades\Route;

if (file_exists(base_path('modules/IntelliReply/routes.php'))) {
    require base_path('modules/IntelliReply/routes.php');
}

Route::post('/whatsapp/exchange-code', [App\Http\Controllers\User\SettingController::class, 'exchangeEmbeddedSignupCode']);
Route::post('/settings/features/embedded-signup/toggle', [App\Http\Controllers\User\SettingController::class, 'toggleEmbeddedSignup']);
Route::get('/settings', [App\Http\Controllers\User\SettingController::class, 'index']);
Route::get('/settings/whatsapp', [App\Http\Controllers\User\SettingController::class, 'viewWhatsappSettings']);
Route::post('/settings/whatsapp/refresh', [App\Http\Controllers\User\SettingController::class, 'refreshWhatsappData']);
Route::post('/settings/whatsapp/token', [App\Http\Controllers\User\SettingController::class, 'updateToken']);
Route::post('/settings/whatsapp', [App\Http\Controllers\User\SettingController::class, 'storeWhatsappSettings']);
Route::post('/settings/whatsapp/business-profile', [App\Http\Controllers\User\SettingController::class, 'whatsappBusinessProfileUpdate']);
Route::delete('/settings/whatsapp/business-profile', [App\Http\Controllers\User\SettingController::class, 'deleteWhatsappIntegration']);
Route::match(['get', 'post'], '/settings/contacts', [App\Http\Controllers\User\SettingController::class, 'contacts']);
Route::match(['get', 'post'], '/settings/tickets', [App\Http\Controllers\User\SettingController::class, 'tickets']);
Route::match(['get', 'post'], '/settings/automation', [App\Http\Controllers\User\SettingController::class, 'automation']);
Route::resource('contact-fields', App\Http\Controllers\User\ContactFieldController::class);
Route::post('/contact-fields/update-positions', [App\Http\Controllers\User\ContactFieldController::class, 'updatePositions']);
