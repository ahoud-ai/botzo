<?php

namespace App\Services;

use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Support\SaClientPlanProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use dacoto\EnvSet\Facades\EnvSet;
use DB;

class SettingService
{
    /**
     * Update the settings based on the request data.
     *
     * @param array $request The data from the request.
     * @return bool Indicates whether the operation was successful.
     */
    public function updateSettings(Request $request)
    {
        $this->updateSettingEntries($request);
        $this->updateSocials($request);
        $this->syncFrontendSeoArtifacts($request);

        return true;
    }

    /**
     * Update individual setting entries based on the request data.
     *
     * @param array $request The data from the request.
     * @return void
     */
    private function updateSettingEntries(Request $request)
    {
        $this->updatePremiumHomeMediaEntries($request);
        $this->updateFrontendSeoMediaEntries($request);

        $premiumHomeMediaKeys = config('frontend.premium_home_media_keys', []);
        $seoMediaKeys = config('frontend.seo_media_keys', []);
        $managedImageKeys = array_values(array_unique(array_merge($premiumHomeMediaKeys, $seoMediaKeys)));

        foreach ($request->all() as $key => $value) {
            if ($key !== 'socials') {
                if ($this->isRetiredSettingKey((string) $key) || $this->isAppearanceColorKey((string) $key)) {
                    continue;
                }

                if (in_array($key, $managedImageKeys, true) || $this->isManagedImageResetKey($key, $managedImageKeys)) {
                    continue;
                }

                if($key == 'logo' || $key == 'favicon'){
                    if ($value != null) {
                        if($request->hasFile($key)){
                            $filePath = $request->file($key)->store('public');
                        } else {
                            $filePath = $value;
                        }

                        try {
                            DB::table('settings')
                                ->updateOrInsert([
                                    'key' => $key
                                ], [
                                    'value' =>$filePath,
                                ]);
                        } catch (\Exception $e) {
                            Log::error($e->getMessage());
                        }
                    }
                } else if($key == 'app_environment') {
                    /*Artisan::call('config:clear');
                    Artisan::call('cache:clear');
                    Cache::flush();

                    EnvSet::setKey('APP_ENV', $value);
                    EnvSet::save();

                    try {
                        DB::table('settings')
                            ->updateOrInsert([
                                'key' => $key
                            ],[
                                'value' => $value,
                            ]);
                    } catch (\Exception $e) {
                        Log::error($e->getMessage());
                    }*/
                } else if($key == 'trial_limits') { 
                    $trial_limits = $request->all()['trial_limits'];

                    try {
                        DB::table('settings')
                            ->updateOrInsert([
                                'key' => 'trial_limits'
                            ],[
                                'value' => json_encode($trial_limits),
                            ]);
                    } catch (\Exception $e) {
                        Log::error($e->getMessage());
                    }
                } else if($key == 'trial_addons') {
                    $trialAddons = SaClientPlanProfile::sanitizeAddonMap($request->all()['trial_addons'] ?? []);

                    try {
                        DB::table('settings')
                            ->updateOrInsert([
                                'key' => 'trial_addons'
                            ],[
                                'value' => json_encode($trialAddons),
                            ]);
                    } catch (\Exception $e) {
                        Log::error($e->getMessage());
                    }
                } else if($key == 'aws'){
                    Artisan::call('config:clear');
                    Artisan::call('cache:clear');
                    Cache::flush();

                    if (isset($value['access_key'])) {
                        EnvSet::setKey('AWS_ACCESS_KEY_ID', $value['access_key']);
                    }
                    if (isset($value['secret_key'])) {
                        EnvSet::setKey('AWS_SECRET_ACCESS_KEY', $value['secret_key']);
                    }
                    if (isset($value['default_region'])) {
                        EnvSet::setKey('AWS_DEFAULT_REGION', $value['default_region']);
                    }
                    if (isset($value['bucket'])) {
                        EnvSet::setKey('AWS_BUCKET', $value['bucket']);
                    }
                    EnvSet::save();

                    $value = json_encode($value);

                    DB::table('settings')
                        ->updateOrInsert([
                            'key' => $key
                        ],[
                            'value' => $value,
                        ]);
                } else {
                    if($key == 'mail_config'){
                        if($value['driver'] == 'smtp'){
                            Artisan::call('config:clear');
                            Artisan::call('cache:clear');
                            Cache::flush();

                            $port = (int) ($value['port'] ?? 587);
                            // Port 465 uses implicit SSL; 587, 25, 2525 use STARTTLS
                            $encryption = ($port === 465) ? 'ssl' : 'tls';

                            EnvSet::setKey('MAIL_MAILER', $value['driver']);
                            EnvSet::setKey('MAIL_HOST', $value['host']);
                            EnvSet::setKey('MAIL_PORT', $value['port']);
                            EnvSet::setKey('MAIL_ENCRYPTION', $encryption);
                            EnvSet::setKey('MAIL_USERNAME', $value['username']);
                            EnvSet::setKey('MAIL_PASSWORD', $value['password']);
                            EnvSet::setKey('MAIL_FROM_ADDRESS', $value['from_address']);
                            EnvSet::setKey('MAIL_FROM_NAME', $value['from_name']);
                            // When using SMTP, comment out / ignore Resend API key
                            EnvSet::setKey('RESEND_API_KEY', null);
                            EnvSet::save();
                        } elseif ($value['driver'] == 'resend') {
                            Artisan::call('config:clear');
                            Artisan::call('cache:clear');
                            Cache::flush();

                            EnvSet::setKey('MAIL_MAILER', $value['driver']);
                            EnvSet::setKey('RESEND_API_KEY', $value['resend_api_key'] ?? '');
                            EnvSet::setKey('MAIL_FROM_ADDRESS', $value['from_address']);
                            EnvSet::setKey('MAIL_FROM_NAME', $value['from_name']);
                            // When using Resend, ignore SMTP settings (do not use MAIL_HOST etc.)
                            EnvSet::setKey('MAIL_HOST', null);
                            EnvSet::setKey('MAIL_PORT', null);
                            EnvSet::setKey('MAIL_USERNAME', null);
                            EnvSet::setKey('MAIL_PASSWORD', null);
                            EnvSet::save();
                        } else if($value['driver'] == 'ses'){
                            Artisan::call('config:clear');
                            Artisan::call('cache:clear');
                            Cache::flush();

                            EnvSet::setKey('MAIL_MAILER', $value['driver']);
                            EnvSet::setKey('MAIL_HOST', null);
                            EnvSet::setKey('MAIL_PORT', null);
                            EnvSet::setKey('MAIL_USERNAME', null);
                            EnvSet::setKey('MAIL_PASSWORD', null);
                            EnvSet::setKey('SES_KEY', $value['ses_key']);
                            EnvSet::setKey('SES_KEY_SECRET', $value['ses_secret']);
                            EnvSet::setKey('SES_REGION', $value['ses_region']);
                            EnvSet::setKey('MAIL_FROM_ADDRESS', $value['from_address']);
                            EnvSet::setKey('MAIL_FROM_NAME', $value['from_name']);
                            EnvSet::save();
                        } else if($value['driver'] == 'mailgun'){
                            Artisan::call('config:clear');
                            Artisan::call('cache:clear');
                            Cache::flush();

                            EnvSet::setKey('MAIL_MAILER', $value['driver']);
                            EnvSet::setKey('MAIL_HOST', null);
                            EnvSet::setKey('MAIL_PORT', null);
                            EnvSet::setKey('MAIL_USERNAME', null);
                            EnvSet::setKey('MAIL_PASSWORD', null);
                            EnvSet::setKey('MAILGUN_DOMAIN', $value['mg_domain']);
                            EnvSet::setKey('MAILGUN_SECRET', $value['mg_secret']);
                            EnvSet::setKey('MAIL_FROM_ADDRESS', $value['from_address']);
                            EnvSet::setKey('MAIL_FROM_NAME', $value['from_name']);
                            EnvSet::save();
                        }

                        $value = json_encode($value);

                        DB::table('settings')
                            ->updateOrInsert([
                                'key' => $key
                            ],[
                                'value' => $value,
                            ]);
                    } else if($key == 'is_tax_inclusive'){
                        try {
                            DB::table('settings')->updateOrInsert(['key' => $key],['value' => $value,]);
                        } catch (\Exception $e) {
                            Log::error($e->getMessage());
                        }

                    } else {
                        if($key != 'logo' && $key != 'favicon'){
                            try {
                                DB::table('settings')
                                    ->updateOrInsert([
                                        'key' => $key
                                    ],[
                                        'value' => $value,
                                    ]);
                            } catch (\Exception $e) {
                                Log::error($e->getMessage());
                            }
                        }
                    }
                }
            }
        }
    }

    private function updatePremiumHomeMediaEntries(Request $request): void
    {
        foreach (config('frontend.premium_home_media_keys', []) as $key) {
            $shouldRemove = $request->boolean('remove_'.$key);

            if (! $shouldRemove && ! $request->hasFile($key)) {
                continue;
            }

            $this->persistManagedImageSetting($request, $key, $shouldRemove);
        }
    }

    private function updateFrontendSeoMediaEntries(Request $request): void
    {
        foreach (config('frontend.seo_media_keys', []) as $key) {
            $shouldRemove = $request->boolean('remove_'.$key);

            if (! $shouldRemove && ! $request->hasFile($key)) {
                continue;
            }

            $this->persistManagedImageSetting($request, $key, $shouldRemove);
        }
    }

    private function persistManagedImageSetting(Request $request, string $key, bool $shouldRemove = false): void
    {
        $existingPath = Setting::where('key', $key)->value('value');

        if ($shouldRemove) {
            $this->deleteManagedSettingFile($existingPath);
            DB::table('settings')->where('key', $key)->delete();

            return;
        }

        if (! $request->hasFile($key)) {
            return;
        }

        $filePath = $request->file($key)->store('public');

        $this->deleteManagedSettingFile($existingPath);

        try {
            DB::table('settings')
                ->updateOrInsert([
                    'key' => $key,
                ], [
                    'value' => $filePath,
                ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }

    private function deleteManagedSettingFile(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        $normalizedPath = trim($path, " \t\n\r\0\x0B\"'");

        if ($normalizedPath === '' || ! str_starts_with($normalizedPath, 'public/')) {
            return;
        }

        $publicRelativePath = ltrim(substr($normalizedPath, strlen('public/')), '/');

        try {
            Storage::delete($normalizedPath);

            if ($publicRelativePath !== '') {
                Storage::disk('public')->delete($publicRelativePath);
            }
        } catch (\Throwable $e) {
            Log::warning($e->getMessage());
        }
    }

    private function isManagedImageResetKey(string $key, array $managedKeys): bool
    {
        if (! str_starts_with($key, 'remove_')) {
            return false;
        }

        return in_array(substr($key, 7), $managedKeys, true);
    }

    private function syncFrontendSeoArtifacts(Request $request): void
    {
        if ((string) $request->input('type') !== 'frontend-seo') {
            return;
        }

        $this->syncRobotsTxtFile();
    }

    private function syncRobotsTxtFile(): void
    {
        $settings = Setting::whereIn('key', [
            'seo_robots_custom',
            'seo_robots_index',
            'seo_canonical_base_url',
        ])->pluck('value', 'key');

        $customRobots = trim((string) ($settings['seo_robots_custom'] ?? ''));

        if ($customRobots !== '') {
            $content = $customRobots;
        } else {
            $indexingAllowed = $this->isTruthySetting($settings['seo_robots_index'] ?? '1');
            $canonicalBaseUrl = trim((string) ($settings['seo_canonical_base_url'] ?? config('app.url', '')));
            $canonicalBaseUrl = $canonicalBaseUrl !== '' ? rtrim($canonicalBaseUrl, '/') : rtrim((string) config('app.url', ''), '/');

            if (! $indexingAllowed) {
                $content = implode(PHP_EOL, [
                    'User-agent: *',
                    'Disallow: /',
                ]);
            } else {
                $lines = [
                    'User-agent: *',
                    'Allow: /',
                    'Disallow: /admin',
                    'Disallow: /dashboard',
                    'Disallow: /automation',
                    'Disallow: /settings',
                ];

                if ($canonicalBaseUrl !== '') {
                    $lines[] = '';
                    $lines[] = 'Sitemap: '.$canonicalBaseUrl.'/sitemap.xml';
                }

                $content = implode(PHP_EOL, $lines);
            }
        }

        try {
            file_put_contents(public_path('robots.txt'), rtrim($content).PHP_EOL);
        } catch (\Throwable $e) {
            Log::warning($e->getMessage());
        }
    }

    private function isTruthySetting($value): bool
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Update the 'socials' setting based on the request data.
     *
     * @param array $request The data from the request.
     * @return void
     */
    private function updateSocials(Request $request)
    {
        if (isset($request->all()['socials'])) {
            $socials = $request->all()['socials'];
            unset($socials['instagram'], $socials['messenger']);

            try {
                DB::table('settings')
                    ->updateOrInsert([
                        'key' => 'socials'
                    ],[
                        'value' => json_encode($socials),
                    ]);
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        }
    }

    private function isRetiredSettingKey(string $key): bool
    {
        if (in_array($key, [
            'google_analytics_status',
            'google_analytics_tracking_id',
            'tracking_google_tag_manager_id',
            'google_maps_api_key',
            'google_auth_active',
            'recaptcha_active',
            'recaptcha_secret_key',
            'recaptcha_site_key',
            'woo'.'commerce_plugin_link',
            'woo'.'commerce_plugin_name',
            'is_meta_messaging_active',
            'meta_messaging_app_id',
            'meta_messaging_app_secret',
            'meta_messaging_verify_token',
            'meta_messaging_manual_connect_enabled',
            'mobile'.'_app_enabled',
            'mobile'.'_push_enabled',
            'fire'.'base_credentials',
            'fire'.'base_project_id',
            'f'.'cm_server_key',
            'f'.'cm_sender_id',
        ], true)) {
            return true;
        }

        foreach ([
            'google_analytics_',
            'tracking_google_',
            'google_maps_',
            'google_auth_',
            'recaptcha_',
            'woo'.'commerce_',
            'meta_messaging_',
            'messaging_channel_',
            'messenger_',
            'instagram_',
            'mobile'.'_',
            'fire'.'base_',
            'f'.'cm_',
        ] as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function isAppearanceColorKey(string $key): bool
    {
        return in_array($key, ['primary_color', 'secondary_color'], true);
    }

    /**
     * Retrieve all settings from the database.
     *
     * @return \Illuminate\Database\Eloquent\Collection The collection of settings.
     */
    public function getSettings()
    {
        return Setting::get();
    }
}
