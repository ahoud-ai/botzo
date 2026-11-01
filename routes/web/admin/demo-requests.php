<?php

use Illuminate\Support\Facades\Route;

Route::get('/demo-requests', [App\Http\Controllers\Admin\DemoRequestController::class, 'index'])
    ->middleware('admin.permission:demo_requests,view')
    ->name('demo-requests');

Route::post('/demo-requests/{id}/status', [App\Http\Controllers\Admin\DemoRequestController::class, 'updateStatus'])
    ->whereNumber('id')
    ->middleware('admin.permission:demo_requests,edit');
