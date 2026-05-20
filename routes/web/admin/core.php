<?php

use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])
    ->middleware('admin.permission:customers,view');

Route::middleware('admin.permission:customers')->group(function () {
    Route::patch('users/{user}/suspend', [App\Http\Controllers\Admin\UserController::class, 'suspend'])->name('users.suspend');
    Route::patch('users/{user}/restore', [App\Http\Controllers\Admin\UserController::class, 'restore'])->name('users.restore');
    Route::resource('users', App\Http\Controllers\Admin\UserController::class);
});

Route::middleware('admin.permission:organizations')->group(function () {
    Route::resource('organizations', App\Http\Controllers\Admin\OrganizationController::class);
    Route::get('organizations/{organization}/invoices/{invoice}', [App\Http\Controllers\Admin\OrganizationController::class, 'showInvoice'])->name('organizations.invoices.show');
    Route::get('organizations/{organization}/invoices/{invoice}/preview', [App\Http\Controllers\Admin\OrganizationController::class, 'previewInvoice'])->name('organizations.invoices.preview');
    Route::get('organizations/{organization}/invoices/{invoice}/print', [App\Http\Controllers\Admin\OrganizationController::class, 'printInvoice'])->name('organizations.invoices.print');
    Route::get('organizations/{organization}/invoices/{invoice}/download', [App\Http\Controllers\Admin\OrganizationController::class, 'downloadInvoice'])->name('organizations.invoices.download');
});

Route::middleware('admin.permission:settings,tax_rates')->group(function () {
    Route::resource('tax-rates', App\Http\Controllers\Admin\TaxController::class);
});

Route::middleware('admin.permission:settings,coupons')->group(function () {
    Route::resource('coupons', App\Http\Controllers\Admin\CouponController::class);
});

Route::middleware('admin.permission:settings,frontend')->group(function () {
    Route::resource('faqs', App\Http\Controllers\Admin\FaqController::class);
    Route::resource('testimonials', App\Http\Controllers\Admin\TestimonialController::class);
});

Route::middleware('admin.permission:subscription_plans')->group(function () {
    Route::resource('plans', App\Http\Controllers\Admin\SubscriptionPlanController::class);
    Route::get('plans/{uuid}/check-subscribers', [App\Http\Controllers\Admin\SubscriptionPlanController::class, 'checkSubscribers']);
    Route::post('plans/{uuid}/destroy-with-transfer', [App\Http\Controllers\Admin\SubscriptionPlanController::class, 'destroyWithTransfer']);
});

Route::middleware('admin.permission:team')->group(function () {
    Route::resource('team/users', App\Http\Controllers\Admin\TeamController::class)->names('team.users');
});

Route::middleware('admin.permission:roles')->group(function () {
    Route::resource('team/roles', App\Http\Controllers\Admin\RoleController::class);
    Route::get('team/roles/{uuid}/check-users', [App\Http\Controllers\Admin\RoleController::class, 'checkUsers']);
    Route::post('team/roles/{uuid}/destroy-with-transfer', [App\Http\Controllers\Admin\RoleController::class, 'destroyWithTransfer']);
});

Route::middleware('admin.permission:billing')->group(function () {
    Route::resource('billing', App\Http\Controllers\Admin\BillingController::class);
    Route::get('/payment-logs', [App\Http\Controllers\Admin\PaymentController::class, 'index'])->name('payment-logs.index');
    Route::get('/payment-logs/invoices/{invoice}', [App\Http\Controllers\Admin\PaymentController::class, 'showInvoice'])->name('payment-logs.invoices.show');
    Route::get('/payment-logs/invoices/{invoice}/preview', [App\Http\Controllers\Admin\PaymentController::class, 'previewInvoice'])->name('payment-logs.invoices.preview');
    Route::get('/payment-logs/invoices/{invoice}/print', [App\Http\Controllers\Admin\PaymentController::class, 'printInvoice'])->name('payment-logs.invoices.print');
    Route::get('/payment-logs/invoices/{invoice}/download', [App\Http\Controllers\Admin\PaymentController::class, 'downloadInvoice'])->name('payment-logs.invoices.download');
    Route::get('/payment_logs', [App\Http\Controllers\Admin\PaymentController::class, 'index']);
    Route::get('/payment_logs/invoices/{invoice}', [App\Http\Controllers\Admin\PaymentController::class, 'showInvoice']);
    Route::get('/payment_logs/invoices/{invoice}/preview', [App\Http\Controllers\Admin\PaymentController::class, 'previewInvoice']);
    Route::get('/payment_logs/invoices/{invoice}/print', [App\Http\Controllers\Admin\PaymentController::class, 'printInvoice']);
    Route::get('/payment_logs/invoices/{invoice}/download', [App\Http\Controllers\Admin\PaymentController::class, 'downloadInvoice']);
});

Route::middleware('admin.permission:settings,payment_gateways')->group(function () {
    Route::resource('payment-gateways', App\Http\Controllers\Admin\PaymentGatewayController::class)->only(['index', 'show', 'update']);
});
