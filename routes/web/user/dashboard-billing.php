<?php

use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], '/dashboard', [App\Http\Controllers\User\DashboardController::class, 'index'])->name('dashboard');

Route::group(['middleware' => 'check.client.role'], function () {
    Route::delete('dismiss-team-prompt/{type}', [App\Http\Controllers\User\DashboardController::class, 'dismissTeamPrompt'])->name('dashboard.team.prompt.dismiss');
    Route::match(['get', 'post'], '/billing', [App\Http\Controllers\User\BillingController::class, 'index'])->name('user.billing.index');
    Route::get('/billing/usage', [App\Http\Controllers\User\BillingController::class, 'usage'])->name('user.billing.usage');
    Route::get('/billing/invoices/{invoice}', [App\Http\Controllers\User\BillingController::class, 'showInvoice'])->name('user.billing.invoices.show');
    Route::get('/billing/invoices/{invoice}/preview', [App\Http\Controllers\User\BillingController::class, 'previewInvoice'])->name('user.billing.invoices.preview');
    Route::get('/billing/invoices/{invoice}/print', [App\Http\Controllers\User\BillingController::class, 'printInvoice'])->name('user.billing.invoices.print');
    Route::get('/billing/invoices/{invoice}/download', [App\Http\Controllers\User\BillingController::class, 'downloadInvoice'])->name('user.billing.invoices.download');
    Route::post('/pay', [App\Http\Controllers\User\BillingController::class, 'pay'])->name('user.billing.pay');
    Route::resource('subscription', App\Http\Controllers\User\SubscriptionController::class);
    Route::post('/subscription/scheduled-change/cancel', [App\Http\Controllers\User\SubscriptionController::class, 'cancelScheduledChange'])
        ->name('subscription.scheduled-change.cancel');
    Route::post('/subscription/coupon/apply/{id}', [App\Http\Controllers\User\SubscriptionController::class, 'applyCoupon']);
    Route::delete('/subscription/coupon/remove/{id}', [App\Http\Controllers\User\SubscriptionController::class, 'removeCoupon']);
});
