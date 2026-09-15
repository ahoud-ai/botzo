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
                // Set default broadcaster
                Config::set('broadcasting.default', $broadcastSettings['broadcast_driver']);

                // Only set Pusher config if driver is pusher
                if ($broadcastSettings['broadcast_driver'] === 'pusher' && !empty($broadcastSettings['pusher_app_key'])) {
                    Config::set('broadcasting.connections.pusher.key', $broadcastSettings['pusher_app_key'] ?? null);
                    Config::set('broadcasting.connections.pusher.secret', $broadcastSettings['pusher_app_secret'] ?? null);
                    Config::set('broadcasting.connections.pusher.app_id', $broadcastSettings['pusher_app_id'] ?? null);
                    Config::set('broadcasting.connections.pusher.options.cluster', $broadcastSettings['pusher_app_cluster'] ?? null);
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
