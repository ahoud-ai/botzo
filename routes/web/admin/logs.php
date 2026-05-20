<?php

use Illuminate\Support\Facades\Route;

Route::get('/user-logs/emails', [App\Http\Controllers\Admin\EmailLogController::class, 'index'])
    ->middleware('admin.permission:logs,view');
