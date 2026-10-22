<?php

namespace App\Services\WebWidget;

use App\Models\Organization;
use App\Models\Setting;
use App\Models\WebWidget;
use Illuminate\Support\Facades\Cache;

/**
 * The widget shown on Botzo's own public site: the widget of the organization recorded in the
 * setting "web_widget_site_organization_id" (set by botzo:setup-site-widget).
 */
class SiteWidget
{
    public const SETTING_KEY = 'web_widget_site_organization_id';

    private const CACHE_KEY = 'web-widget:site-key';

    public function organization(): ?Organization
    {
        $id = (int) Setting::getValueByKey(self::SETTING_KEY);

        return $id > 0 ? Organization::find($id) : null;
    }

    public function widget(): ?WebWidget
    {
        $organization = $this->organization();

        return $organization ? WebWidget::where('organization_id', $organization->id)->first() : null;
    }

    public function remember(Organization $organization): void
    {
        Setting::updateOrCreate(['key' => self::SETTING_KEY], ['value' => (string) $organization->id]);
        $this->forget();
    }

    /**
     * The public key to embed on the public site, or null when the site widget is off.
     */
    public function embedKey(): ?string
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(10), function () {
            $widget = $this->widget();

            return $widget && $widget->is_enabled ? $widget->public_key : '';
        }) ?: null;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
