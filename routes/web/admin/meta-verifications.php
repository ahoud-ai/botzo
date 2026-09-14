<?php

use Illuminate\Support\Facades\Route;

Route::get('/meta-verifications/{id?}', [App\Http\Controllers\Admin\MetaVerificationController::class, 'index'])
    ->middleware('admin.permission:meta_verifications,view')
    ->name('meta-verifications');

Route::post('/meta-verifications/{id}/advance', [App\Http\Controllers\Admin\MetaVerificationController::class, 'advance'])
    ->middleware('admin.permission:meta_verifications,advance');

Route::post('/meta-verifications/{id}/reject', [App\Http\Controllers\Admin\MetaVerificationController::class, 'reject'])
    ->middleware('admin.permission:meta_verifications,reject');

Route::get('/meta-verifications/{id}/documents/{documentId}', [App\Http\Controllers\Admin\MetaVerificationController::class, 'downloadDocument'])
    ->middleware('admin.permission:meta_verifications,view');

Route::post('/meta-verifications/{id}/request-document', [App\Http\Controllers\Admin\MetaVerificationController::class, 'requestDocument'])
    ->middleware('admin.permission:meta_verifications,advance');
