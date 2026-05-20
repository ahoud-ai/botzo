<?php

use Illuminate\Support\Facades\Route;

Route::get('/settings', [App\Http\Controllers\Admin\SettingController::class, 'index'])
    ->middleware('admin.permission:settings,general');

Route::match(['get', 'post'], '/settings/general', [App\Http\Controllers\Admin\SettingController::class, 'general'])
    ->middleware('admin.permission:settings,general');

Route::get('/settings/frontend', [App\Http\Controllers\Admin\SettingController::class, 'frontend'])
    ->middleware('admin.permission:settings,frontend');

Route::get('/settings/frontend/contact-details', [App\Http\Controllers\Admin\SettingController::class, 'frontendContact'])
    ->middleware('admin.permission:settings,frontend');

Route::get('/settings/frontend/premium-home', [App\Http\Controllers\Admin\SettingController::class, 'premiumHomeMedia'])
    ->middleware('admin.permission:settings,frontend');

Route::get('/settings/frontend/seo', [App\Http\Controllers\Admin\SettingController::class, 'frontendSeo'])
    ->middleware('admin.permission:settings,frontend');

Route::get('/settings/features', [App\Http\Controllers\Admin\SettingController::class, 'features'])
    ->middleware('admin.permission:settings,general');

Route::get('/settings/features/embedded-signup', [App\Http\Controllers\Admin\SettingController::class, 'embeddedSignup'])
    ->middleware('admin.permission:settings,general');

Route::post('/settings/features/embedded-signup', [App\Http\Controllers\Admin\AddonController::class, 'store'])
    ->defaults('feature', 'Embedded Signup')
    ->middleware('admin.permission:settings,general');

Route::get('/settings/features/embedded-signup/health', [App\Http\Controllers\Admin\AddonController::class, 'embeddedSignupHealth'])
    ->middleware('admin.permission:settings,general');

Route::post('/settings/features/embedded-signup/meta-review-tests', [App\Http\Controllers\Admin\SettingController::class, 'embeddedSignupMetaReviewTests'])
    ->middleware('admin.permission:settings,general');

Route::get('/settings/features/ai-assistant', [App\Http\Controllers\Admin\SettingController::class, 'aiAssistant'])
    ->middleware('admin.permission:settings,general');

Route::post('/settings/features/ai-assistant', [App\Http\Controllers\Admin\AddonController::class, 'store'])
    ->defaults('feature', 'AI Assistant')
    ->middleware('admin.permission:settings,general');

Route::get('/settings/features/flow-builder', [App\Http\Controllers\Admin\SettingController::class, 'flowBuilder'])
    ->middleware('admin.permission:settings,general');

Route::post('/settings/features/flow-builder', [App\Http\Controllers\Admin\AddonController::class, 'store'])
    ->defaults('feature', 'Flow builder')
    ->middleware('admin.permission:settings,general');

Route::put('/settings', [App\Http\Controllers\Admin\SettingController::class, 'update'])
    ->middleware('admin.permission:settings,auto');

Route::get('/settings/smtp', [App\Http\Controllers\Admin\SettingController::class, 'email'])
    ->middleware('admin.permission:settings,smtp');

Route::get('/settings/broadcast-drivers', [App\Http\Controllers\Admin\SettingController::class, 'broadcast_driver'])
    ->middleware('admin.permission:settings,broadcast_driver');

Route::match(['get', 'post'], '/settings/timezone', [App\Http\Controllers\Admin\SettingController::class, 'timezone'])
    ->middleware('admin.permission:settings,timezone');

Route::get('/settings/email-templates', [App\Http\Controllers\Admin\EmailTemplateController::class, 'index'])
    ->middleware('admin.permission:settings,email_templates');

Route::get('/settings/email-template/{id}', [App\Http\Controllers\Admin\EmailTemplateController::class, 'show'])
    ->middleware('admin.permission:settings,email_templates');

Route::put('/settings/email-template/{id}', [App\Http\Controllers\Admin\EmailTemplateController::class, 'update'])
    ->middleware('admin.permission:settings,email_templates');

Route::get('/settings/pages', [App\Http\Controllers\Admin\PagesController::class, 'index'])
    ->middleware('admin.permission:settings,frontend');

Route::post('/settings/pages', [App\Http\Controllers\Admin\PagesController::class, 'store'])
    ->middleware('admin.permission:settings,frontend');

Route::get('/settings/page/{id}', [App\Http\Controllers\Admin\PagesController::class, 'show'])
    ->middleware('admin.permission:settings,frontend');

Route::put('/settings/page/{id}', [App\Http\Controllers\Admin\PagesController::class, 'update'])
    ->middleware('admin.permission:settings,frontend');

Route::delete('/settings/page/{id}', [App\Http\Controllers\Admin\PagesController::class, 'delete'])
    ->middleware('admin.permission:settings,frontend');

Route::match(['get', 'post'], '/settings/billing', [App\Http\Controllers\Admin\SettingController::class, 'billing'])
    ->middleware('admin.permission:settings,billing');

Route::get('/settings/storage', [App\Http\Controllers\Admin\SettingController::class, 'storage'])
    ->middleware('admin.permission:settings,general');

Route::get('/settings/socials', [App\Http\Controllers\Admin\SettingController::class, 'socials'])
    ->middleware('admin.permission:settings,general');

Route::get('/settings/subscription', [App\Http\Controllers\Admin\SettingController::class, 'subscription'])
    ->middleware('admin.permission:settings,general');
