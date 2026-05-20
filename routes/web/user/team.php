<?php

use Illuminate\Support\Facades\Route;

Route::get('/settings/company-team', [App\Http\Controllers\User\CompanyTeamController::class, 'index'])->name('company.team');
Route::post('/settings/company-team/invite', [App\Http\Controllers\User\CompanyTeamController::class, 'invite'])->name('company.team.invite');
Route::put('/settings/company-team/{uuid}', [App\Http\Controllers\User\CompanyTeamController::class, 'update'])->name('company.team.update');
Route::post('/settings/company-team/{uuid}/resend-invite', [App\Http\Controllers\User\CompanyTeamController::class, 'resendInvite'])->name('company.team.resend-invite');
Route::post('/settings/company-team/{uuid}/suspend', [App\Http\Controllers\User\CompanyTeamController::class, 'suspend'])->name('company.team.suspend');
Route::post('/settings/company-team/{uuid}/restore', [App\Http\Controllers\User\CompanyTeamController::class, 'restore'])->name('company.team.restore');
Route::delete('/settings/company-team/{uuid}', [App\Http\Controllers\User\CompanyTeamController::class, 'destroy'])->name('company.team.destroy');
Route::get('/settings/team', [App\Http\Controllers\User\TeamController::class, 'index'])->name('team');
Route::get('/settings/team/roles/select', [App\Http\Controllers\User\RoleController::class, 'getAllForSelect'])->name('user.team.roles.select');
Route::resource('settings/team/roles', App\Http\Controllers\User\RoleController::class)->names([
    'index' => 'user.team.roles.index',
    'create' => 'user.team.roles.create',
    'store' => 'user.team.roles.store',
    'show' => 'user.team.roles.show',
    'edit' => 'user.team.roles.edit',
    'update' => 'user.team.roles.update',
    'destroy' => 'user.team.roles.destroy',
]);
Route::post('/settings/team/invite', [App\Http\Controllers\User\TeamController::class, 'invite'])->name('team.store');
Route::put('/settings/team/{uuid}', [App\Http\Controllers\User\TeamController::class, 'update'])->name('team.update');
Route::delete('/settings/team/{uuid}', [App\Http\Controllers\User\TeamController::class, 'delete'])->name('team.destroy');
