<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class Localization
{
    /**
    * Handle an incoming request.
    *
    * @param  \Illuminate\Http\Request  $request
    * @param  \Closure  $next
    * @return mixed
    */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Priority: User language > Session locale > Default language
        if ($user && $user->language) {
            $locale = $this->normalizeLocale($user->language);
            App::setLocale($locale);
            // Set session locale to persist across requests
            Session::put('locale', $locale);
        } elseif (Session::has('locale')) {
            $locale = $this->normalizeLocale(Session::get('locale'));
            App::setLocale($locale);
            Session::put('locale', $locale);
        } else {
            if ($this->isBootstrapped()) {
                $locale = Setting::where('key', 'default_language')->value('value') ?? 'en';
            } else {
                $locale = 'en';
            }

            $locale = $this->normalizeLocale($locale);
            App::setLocale($locale);
            Session::put('locale', $locale);
        }

        return $next($request);
    }

    public function isBootstrapped(): bool
    {
        return file_exists(storage_path('in'.'stalled'));
    }

    private function normalizeLocale(?string $locale): string
    {
        $normalized = strtolower((string) $locale);

        if (!in_array($normalized, $this->supportedLocales(), true)) {
            return 'en';
        }

        return $normalized;
    }

    private function supportedLocales(): array
    {
        $supported = config('i18n.supported_locales', ['en', 'ar']);

        return array_values(array_unique(array_map(static fn ($locale) => strtolower((string) $locale), $supported)));
    }
}
