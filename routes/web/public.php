<?php

use App\Models\Language;
use App\Services\System\RuntimeReadinessService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Route::get('/current-locale', function () {
    return response()->json(['locale' => app()->getLocale()]);
});

Route::get('/health/live', function () {
    return response()->json([
        'status' => 'ok',
        'service' => config('app.name', 'app'),
        'timestamp' => now()->toISOString(),
    ]);
});

Route::get('/health/ready', function () {
    $report = app(RuntimeReadinessService::class)->evaluate();

    return response()->json(
        $report,
        $report['status'] === 'ready' ? 200 : 503
    );
});

Route::get('/locales', function () {
    $supported = collect(config('i18n.supported_locales', ['en', 'ar']))
        ->map(fn ($locale) => strtolower($locale))
        ->unique()
        ->values();

    try {
        $activeLocales = Language::whereNull('deleted_at')
            ->where('status', 'active')
            ->pluck('code')
            ->map(fn ($code) => strtolower($code))
            ->unique();
    } catch (\Throwable $exception) {
        $activeLocales = collect();
    }

    $locales = $supported
        ->filter(fn ($locale) => $activeLocales->contains($locale))
        ->values();

    if ($locales->isEmpty()) {
        $locales = $supported;
    }

    return response()->json($locales);
});

Route::get('/translations/{locale}', function ($locale) {
    $locale = strtolower($locale);
    $supported = collect(config('i18n.supported_locales', ['en', 'ar']))
        ->map(fn ($value) => strtolower($value))
        ->values();

    if (! $supported->contains($locale)) {
        return response()->json(['error' => __('Locale not supported')], 404);
    }

    $path = base_path("lang/{$locale}.json");
    if (File::exists($path)) {
        $translations = File::get($path);

        return response()->json(json_decode($translations, true));
    }

    return response()->json(['error' => __('Locale not found')], 404);
});

Route::match(['get', 'post'], '/', [App\Http\Controllers\FrontendController::class, 'index']);
Route::match(['get', 'post'], '/pricing', [App\Http\Controllers\FrontendController::class, 'pricing']);
Route::match(['get', 'post'], '/contact', [App\Http\Controllers\FrontendController::class, 'contact']);
Route::match(['get', 'post'], '/product', [App\Http\Controllers\FrontendController::class, 'product']);
Route::match(['get', 'post'], '/api-documentation', [App\Http\Controllers\FrontendController::class, 'apiDocumentation']);
Route::match(['get', 'post'], '/faqs', [App\Http\Controllers\FrontendController::class, 'faqs']);
Route::match(['get', 'post'], '/cookie-policy', [App\Http\Controllers\FrontendController::class, 'cookiePolicy']);
Route::match(['get', 'post'], '/pages/{slug}', [App\Http\Controllers\FrontendController::class, 'pages']);
Route::match(['get', 'post'], '/privacy', [App\Http\Controllers\FrontendController::class, 'privacy']);
Route::redirect('/tos', '/terms-of-service', 301);
Route::match(['get', 'post'], '/terms-of-service', [App\Http\Controllers\FrontendController::class, 'termsOfService']);
Route::get('/sitemap.xml', [App\Http\Controllers\FrontendSeoController::class, 'sitemap']);
Route::match(['get', 'post'], '/campaign-send', [App\Http\Controllers\FrontendController::class, 'campaignSend'])
    ->middleware('authorize.campaign.dispatch')
    ->name('campaign.send');
Route::get('/language/{locale}', [App\Http\Controllers\FrontendController::class, 'changeLanguage']);

Route::get('media/{filename}', [App\Http\Controllers\FileController::class, 'show'])->where('filename', '.*');
Route::get('/automation/flows/{uuid}/assets/{assetUuid}', [App\Http\Controllers\User\AutomationFlowController::class, 'showAsset'])
    ->whereUuid('uuid')
    ->whereUuid('assetUuid')
    ->middleware('signed')
    ->name('flowbuilder.assets.show');

Route::get('/invite/{identifier}', [App\Http\Controllers\AuthController::class, 'viewInvite']);
Route::post('/invite/{identifier}', [App\Http\Controllers\AuthController::class, 'invite']);
Route::post('/logout', [App\Http\Controllers\AuthController::class, 'logout'])->name('logout');

Route::match(['get', 'post'], '/webhook/whatsapp/{identifier?}', [App\Http\Controllers\WebhookController::class, 'handle']);
Route::match(['get', 'post'], '/webhook/waba', [App\Http\Controllers\WebhookController::class, 'whatsappWebhook']);
Route::match(['get', 'post'], '/payment/moyasar/webhook', [App\Http\Controllers\PaymentController::class, 'processWebhook']);
Route::match(['get', 'post'], '/payment/moyasar', [App\Http\Controllers\PaymentController::class, 'processPayment']);
