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

    private const ORGANIZATION_CACHE_KEY = 'web-widget:site-organization-id';

    public function organization(): ?Organization
    {
        $id = (int) Setting::getValueByKey(self::SETTING_KEY);

        return $id > 0 ? Organization::find($id) : null;
    }

    /**
     * The id of the site organization, or 0 when none is set. Cached: it is asked on every reply.
     */
    public function organizationId(): int
    {
        return (int) Cache::remember(self::ORGANIZATION_CACHE_KEY, now()->addMinutes(10), function () {
            return (int) Setting::getValueByKey(self::SETTING_KEY);
        });
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
        Cache::forget(self::ORGANIZATION_CACHE_KEY);
    }
}
