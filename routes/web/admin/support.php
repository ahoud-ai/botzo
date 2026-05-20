<?php

use Illuminate\Support\Facades\Route;

Route::get('/support/{uuid?}', [App\Http\Controllers\Admin\TicketController::class, 'index'])
    ->middleware('admin.permission:support,view')
    ->name('tickets');

Route::post('/support', [App\Http\Controllers\Admin\TicketController::class, 'store'])
    ->middleware('admin.permission:support,create');

Route::post('/support/{uuid}/comment', [App\Http\Controllers\Admin\TicketController::class, 'comment'])
    ->middleware('admin.permission:support,create');

Route::post('/support/{uuid}/status', [App\Http\Controllers\Admin\TicketController::class, 'changeStatus'])
    ->middleware('admin.permission:support,assign');

Route::post('/support/{uuid}/priority', [App\Http\Controllers\Admin\TicketController::class, 'changePriority'])
    ->middleware('admin.permission:support,assign');

Route::post('/support/{uuid}/assign', [App\Http\Controllers\Admin\TicketController::class, 'assign'])
    ->middleware('admin.permission:support,assign');
