<?php

use Illuminate\Support\Facades\Route;

Route::get('/meta-verifications/{id?}', [App\Http\Controllers\Admin\MetaVerificationController::class, 'index'])
    ->middleware('admin.permission:meta_verifications,view')
    ->name('meta-verifications');

Route::post('/meta-verifications/{id}/advance', [App\Http\Controllers\Admin\MetaVerificationController::class, 'advance'])
    ->middleware('admin.permission:meta_verifications,advance');

Route::post('/meta-verifications/{id}/reject', [App\Http\Controllers\Admin\MetaVerificationController::class, 'reject'])
    ->middleware('admin.permission:meta_verifications,reject');
