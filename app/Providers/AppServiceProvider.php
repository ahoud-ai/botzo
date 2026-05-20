<?php

namespace App\Providers;

use App\Contracts\FeatureGateContract;
use App\Contracts\QueueProfileContract;
use App\Contracts\WebhookVerificationContract;
use App\Modules\Platform\Application\Context\OrganizationContextResolver;
use App\Modules\Platform\Application\Environment\DatabaseConfigMode;
use App\Modules\Platform\Application\Environment\DemoModeService;
use App\Modules\Platform\Http\Support\DomainExceptionResponder;
use App\Services\AddonStateService;
use App\Services\QueueProfileService;
use App\Services\Webhooks\WhatsappWebhookVerificationService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(QueueProfileContract::class, QueueProfileService::class);
        $this->app->singleton(FeatureGateContract::class, AddonStateService::class);
        $this->app->singleton(WebhookVerificationContract::class, WhatsappWebhookVerificationService::class);
        $this->app->singleton(OrganizationContextResolver::class);
        $this->app->singleton(DemoModeService::class);
        $this->app->singleton(DatabaseConfigMode::class);
        $this->app->singleton(DomainExceptionResponder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set default string length for migrations (MySQL compatibility)
        Schema::defaultStringLength(191);

        // Force HTTPS in non-local environments
        if (! app()->environment('local')) {
            URL::forceScheme('https');
        }
    }
}
