<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use App\Models\Setting;

class BroadcastConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * Must run before BroadcastServiceProvider::boot() (see the provider
     * order in config/app.php), which requires routes/channels.php and calls
     * Broadcast::channel() there — that resolves and caches the default
     * broadcaster driver instance. If this provider set the real pusher
     * config afterward instead, channels.php would have already registered
     * its channels on a stale/default driver instance that never receives
     * them, and every broadcasting/auth request would 403 with no channel
     * match even though the user is authenticated correctly.
     */
    public function boot()
    {
        try {
            $broadcastSettings = $this->getPusherSettings();

            if (!empty($broadcastSettings) && isset($broadcastSettings['broadcast_driver'])) {
                $isPusher = $broadcastSettings['broadcast_driver'] === 'pusher';
                $hasPusherCredentials = !empty($broadcastSettings['pusher_app_key'])
                    && !empty($broadcastSettings['pusher_app_secret'])
                    && !empty($broadcastSettings['pusher_app_id']);

                // Only switch the default driver to pusher once real credentials exist —
                // otherwise the broadcaster resolves with a null key/secret the moment
                // anything (including just registering channels in routes/channels.php)
                // touches Broadcast::, and the Pusher SDK throws a TypeError.
                Config::set('broadcasting.default', $isPusher && !$hasPusherCredentials
                    ? 'null'
                    : $broadcastSettings['broadcast_driver']);

                if ($isPusher && $hasPusherCredentials) {
                    $cluster = $broadcastSettings['pusher_app_cluster'] ?? null;

                    Config::set('broadcasting.connections.pusher.key', $broadcastSettings['pusher_app_key']);
                    Config::set('broadcasting.connections.pusher.secret', $broadcastSettings['pusher_app_secret']);
                    Config::set('broadcasting.connections.pusher.app_id', $broadcastSettings['pusher_app_id']);
                    Config::set('broadcasting.connections.pusher.options.cluster', $cluster);

                    // config/broadcasting.php bakes a static 'host' fallback (api-mt1.pusher.com)
                    // from the PUSHER_APP_CLUSTER env var at file-load time. The Pusher SDK prefers
                    // 'host' over 'cluster' whenever 'host' is present, so overriding only 'cluster'
                    // here left every request hitting the wrong cluster's API and Pusher rejecting
                    // the app key with "not in this cluster". Override 'host' too so it matches.
                    if ($cluster) {
                        Config::set('broadcasting.connections.pusher.options.host', 'api-' . $cluster . '.pusher.com');
                    }
                }
            } else {
                \Illuminate\Support\Facades\Log::warning('BroadcastConfigServiceProvider: No broadcast settings found or broadcast_driver not set');
            }
        } catch (\Exception $e) {
            // Log the error instead of silently failing
            \Illuminate\Support\Facades\Log::error('BroadcastConfigServiceProvider: Error loading settings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Fetch Pusher settings from the database.
     *
     * @return array
     */
    private function getPusherSettings()
    {
        try {
            // Fetch Pusher settings from the database
            $broadcastSettings = Setting::whereIn('key', [
                'broadcast_driver',
                'pusher_app_key',
                'pusher_app_secret',
                'pusher_app_id',
                'pusher_app_cluster',
            ])->pluck('value', 'key')->toArray();

            return $broadcastSettings;
        } catch (\Exception $e) {
            // Return empty array if settings table doesn't exist or database is not ready
            return [];
        }
    }
}
