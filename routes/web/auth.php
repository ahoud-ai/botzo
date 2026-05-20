<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['guest', 'redirectIfAuthenticated:user,admin'])->group(function () {
    Route::get('/login', [App\Http\Controllers\AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [App\Http\Controllers\AuthController::class, 'login']);
    Route::get('/social-login/{type?}', [App\Http\Controllers\AuthController::class, 'socialLogin']);
    Route::get('/google/callback', [App\Http\Controllers\AuthController::class, 'googleCallback'])->name('google.callback');
    Route::get('/facebook/callback', [App\Http\Controllers\AuthController::class, 'handleFacebookCallback']);
    Route::get('/signup', [App\Http\Controllers\AuthController::class, 'showRegistrationForm']);
    Route::post('/signup', [App\Http\Controllers\AuthController::class, 'handleRegistration']);
    Route::get('/forgot-password', [App\Http\Controllers\AuthController::class, 'showForgotForm']);
    Route::post('/forgot-password', [App\Http\Controllers\AuthController::class, 'createPasswordResetToken']);
    Route::get('/reset-password', [App\Http\Controllers\AuthController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [App\Http\Controllers\AuthController::class, 'resetPassword']);
});

Route::middleware(['auth:user,admin'])->group(function () {
    Route::put('/profile', [App\Http\Controllers\ProfileController::class, 'update']);
    Route::put('/profile/password', [App\Http\Controllers\ProfileController::class, 'updatePassword']);
    Route::put('/profile/organization', [App\Http\Controllers\ProfileController::class, 'updateOrganization']);
});
