<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Exclude webhook routes from CSRF protection (required in Laravel 11)
        // These match the exclusions from VerifyCsrfToken middleware
        $middleware->validateCsrfTokens(except: [
            'webhook/whatsapp/*',
            '/webhook/whatsapp/*',
            'webhook/waba',
            '/webhook/waba',
            'payment/moyasar/webhook',
            '/payment/moyasar/webhook',
        ]);

        // Register global middleware
        $middleware->append([
            \App\Http\Middleware\TrustProxies::class,
            \App\Http\Middleware\CheckAppStatus::class,
        ]);

        // Register web middleware group
        $middleware->web(append: [
            \App\Http\Middleware\Localization::class,
            \App\Http\Middleware\SetOrganizationFromSession::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // Register middleware aliases
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
            'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
            'can' => \Illuminate\Auth\Middleware\Authorize::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
            'precognitive' => \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            'signed' => \App\Http\Middleware\ValidateSignature::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'check.subscription' => \App\Http\Middleware\CheckSubscriptionStatus::class,
            'check.organization' => \App\Http\Middleware\CheckOrganizationId::class,
            'check.email.verification' => \App\Http\Middleware\CheckEmailVerification::class,
            'check.client.role' => \App\Http\Middleware\CheckClientRole::class,
            'auth.bearer' => \App\Http\Middleware\AuthenticateBearerToken::class,
            'setOrganization' => \App\Http\Middleware\SetOrganizationFromSession::class,
            'redirectIfAuthenticated' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'authorize.campaign.dispatch' => \App\Http\Middleware\AuthorizeCampaignDispatch::class,
            'admin.permission' => \App\Http\Middleware\CheckAdminPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Custom exception rendering
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                $statusCode = $e->getStatusCode();

                // Keep debug visibility in local/testing for real server errors.
                if ($statusCode === 500 && config('app.debug')) {
                    return null;
                }

                if (in_array($statusCode, [403, 404, 500], true) && $request->header('X-Inertia')) {
                    return Inertia::render('Error', [
                        'status' => $statusCode,
                        'message' => $e->getMessage(),
                    ])->toResponse($request)->setStatusCode($statusCode);
                }

                if ($statusCode === 403) {
                    return response()->view('errors.403', [], 403);
                }

                if ($statusCode === 404) {
                    return response()->view('errors.404', [], 404);
                }

                if ($statusCode === 500) {
                    return response()->view('errors.500', [], 500);
                }
            }

            return null;
        });
    })
    ->withSchedule(function ($schedule) {
        // ProcessCampaignMessagesJob - runs every minute to activate scheduled campaigns and mark completions
        $schedule->job(new \App\Jobs\ProcessCampaignMessagesJob(), 'campaign-messages')
            ->everyMinute()
            ->withoutOverlapping();
        
        // Monitor queue health
        $schedule->command('queue:restart')
            ->hourly()
            ->evenInMaintenanceMode();
        
        // Clean failed jobs table
        $schedule->command('queue:prune-failed --hours=24')
            ->daily()
            ->evenInMaintenanceMode();

        $schedule->command('queue:prune-batches --hours=48 --unfinished=72')
            ->daily();

        // Refresh due WhatsApp tokens proactively to avoid manual re-entry.
        $schedule->command('whatsapp:refresh-tokens')
            ->hourly()
            ->withoutOverlapping();
        
        $schedule->command('model:prune', [
            '--model' => \App\Models\CampaignLog::class,
            '--hours' => 72,
        ])->daily();
    })
    ->create();
