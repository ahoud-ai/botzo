<?php

namespace App\Services\Salla;

use App\Models\Organization;
use App\Models\SallaStore;
use App\Models\WebWidget;

/**
 * Gives a store its assistant and website widget. Safe to run again: the widget's key and whatever
 * the merchant edited are kept, and the allowed domain follows the store's domain in Salla.
 */
class SallaWidgetProvisioner
{
    public function ensure(SallaStore $store): WebWidget
    {
        $organization = Organization::query()->findOrFail($store->organization_id);

        $this->ensureAssistant($organization, (string) $store->store_name);

        $domain = WebWidget::normalizeDomain((string) $store->store_domain);
        $widget = WebWidget::query()->where('organization_id', $organization->id)->first();

        if (! $widget) {
            return WebWidget::create([
                'organization_id' => $organization->id,
                'public_key' => WebWidget::generatePublicKey(),
                'is_enabled' => true,
                'allowed_domains' => $domain ? [$domain] : [],
                'created_by' => $organization->created_by,
                'settings' => ['theme' => 'store'],
            ]);
        }

        if ($domain && $widget->allowed_domains !== [$domain]) {
            $widget->update(['allowed_domains' => [$domain]]);
        }

        return $widget;
    }

    /** Replies always use Botzo's key: Salla plans do not allow a store's own. */
    private function ensureAssistant(Organization $organization, string $storeName): void
    {
        $metadata = json_decode((string) $organization->metadata, true) ?: [];
        $ai = (array) ($metadata['ai'] ?? []);
        $firstRun = ! isset($ai['persona']);

        $ai['persona'] = array_merge(
            ['business_name' => $storeName] + (array) require database_path('data/salla_store_assistant.php'),
            array_filter((array) ($ai['persona'] ?? []), fn ($value) => $value !== '' && $value !== null)
        );

        if ($firstRun) {
            $ai += [
                'platform' => 'OpenAI',
                'model' => 'gpt-4o-mini',
                'max_tokens' => 512,
                'temperature' => 0.5,
            ];
            $ai['active'] = true;
            $ai['enable_automatic_responses'] = true;
            $ai['key_source'] = 'global';
        }

        $metadata['ai'] = $ai;
        $organization->forceFill(['metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE)])->save();
    }
}
