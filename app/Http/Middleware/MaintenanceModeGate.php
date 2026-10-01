<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceModeGate
{
    private const EXEMPT_PATH_PREFIXES = [
        'admin',
        'login',
        'forgot-password',
        'reset-password',
        'webhook',
        'payment/moyasar/webhook',
        'build',
        'storage',
        'media',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isExempt($request) || ! $this->isMaintenanceModeEnabled()) {
            return $next($request);
        }

        $settings = Setting::whereIn('key', ['favicon', 'email', 'socials', 'maintenance_eta'])
            ->pluck('value', 'key');

        $faviconPath = trim((string) ($settings['favicon'] ?? ''));
        $socials = json_decode((string) ($settings['socials'] ?? ''), true);
        $statusLink = is_array($socials) ? trim((string) ($socials['twitter'] ?? '')) : '';

        return response()->view('maintenance', [
            'faviconUrl' => $faviconPath !== '' ? url('/media/'.$faviconPath) : url('/images/favicon.png'),
            'maintenanceEta' => trim((string) ($settings['maintenance_eta'] ?? '')),
            'supportEmail' => trim((string) ($settings['email'] ?? '')) ?: 'support@botzo.net',
            'statusLink' => $statusLink !== '' ? $statusLink : '#',
        ], 503)->header('Retry-After', 3600);
    }

    private function isExempt(Request $request): bool
    {
        if (Auth::guard('admin')->check()) {
            return true;
        }

        $currentPath = $request->path();

        return Str::startsWith($currentPath, self::EXEMPT_PATH_PREFIXES);
    }

    private function isMaintenanceModeEnabled(): bool
    {
        return (string) Setting::where('key', 'maintenance_mode')->value('value') === '1';
    }
}
