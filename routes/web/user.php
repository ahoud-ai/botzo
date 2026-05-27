<?php

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:user'])->group(function () {
    Route::get('/email/verify', [App\Http\Controllers\AuthController::class, 'verifyEmail'])->middleware('auth')->name('verification.notice');
    Route::post('/email/verify-code', [App\Http\Controllers\AuthController::class, 'verifyEmailCode'])->middleware(['auth', 'throttle:6,1'])->name('verification.code.verify');

    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        return redirect('/dashboard');
    })->middleware(['auth', 'signed'])->name('verification.verify');

    Route::post('/email/verification-notification', [App\Http\Controllers\AuthController::class, 'sendEmailVerification'])->middleware(['auth', 'throttle:6,1'])->name('verification.send');

    Route::group(['middleware' => ['check.email.verification']], function () {
        Route::get('/select-organization', [App\Http\Controllers\User\OrganizationController::class, 'index'])->name('user.organization.index');
        Route::post('/select-organization', [App\Http\Controllers\User\OrganizationController::class, 'selectOrganization'])->name('user.organization.selectOrganization');
        Route::post('/organization', [App\Http\Controllers\User\OrganizationController::class, 'store'])->name('user.organization.store');

        Route::group(['middleware' => ['check.organization']], function () {
            require base_path('routes/web/user/dashboard-billing.php');

            Route::group(['middleware' => 'check.subscription'], function () {
                require base_path('routes/web/user/chats-contacts.php');
                require base_path('routes/web/user/campaigns-templates.php');
                require base_path('routes/web/automation.php');
                require base_path('routes/web/user/support-messages.php');

                Route::group(['middleware' => 'check.client.role'], function () {
                    require base_path('routes/web/user/settings-core.php');
                    require base_path('routes/web/user/team.php');
                    require base_path('routes/web/user/developer-tools.php');
                });

                Route::resource('notes', App\Http\Controllers\User\ChatNoteController::class);
            });
        });
    });
});
