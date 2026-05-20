<?php

namespace App\Providers;

use App\Models\Setting;
use App\Modules\Platform\Application\Environment\DatabaseConfigMode;
use Illuminate\Support\ServiceProvider;

class MailConfigServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        if (! app(DatabaseConfigMode::class)->enabled()) {
            return;
        }

        $mailConfig = Setting::where('key', 'mail_config')->value('value');
        $mailConfig = json_decode($mailConfig, true);

        if (! is_array($mailConfig)) {
            return;
        }

        config([
            'mail.driver' => $mailConfig['driver'] ?? null,
            'mail.host' => $mailConfig['host'] ?? null,
            'mail.port' => $mailConfig['port'] ?? null,
            'mail.username' => $mailConfig['username'] ?? null,
            'mail.password' => $mailConfig['password'] ?? null,
            'mail.from.address' => $mailConfig['from_address'] ?? null,
            'mail.from.name' => $mailConfig['from_name'] ?? null,
            'mail.reply_to.address' => $mailConfig['reply_to_address'] ?? null,
            'mail.reply_to.name' => $mailConfig['reply_to_name'] ?? null,
        ]);

        if (($mailConfig['driver'] ?? null) === 'resend') {
            config([
                'mail.mailers.resend.key' => $mailConfig['resend_api_key'] ?? config('mail.mailers.resend.key'),
            ]);
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
