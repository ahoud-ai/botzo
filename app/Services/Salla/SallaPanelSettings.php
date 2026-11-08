<?php

namespace App\Services\Salla;

use App\Models\SallaStore;
use App\Models\WebWidget;
use Illuminate\Support\Arr;

/**
 * What a merchant can change from the panel: Mochi's look and on/off switch, and what Mochi may tell
 * shoppers about the store. Their text stays apart from Botzo's instructions to the assistant.
 */
class SallaPanelSettings
{
    public const MAX_NAME = 40;

    public const MAX_WELCOME = 300;

    public const MAX_INFO = 2000;

    public function rules(): array
    {
        return [
            'is_enabled' => ['required', 'boolean'],
            'use_store_color' => ['required', 'boolean'],
            'accent' => ['nullable', 'required_if:use_store_color,false', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'position' => ['required', 'in:left,right'],
            'assistant_name' => ['nullable', 'string', 'max:'.self::MAX_NAME],
            'welcome_text' => ['nullable', 'string', 'max:'.self::MAX_WELCOME],
            'return_policy' => ['nullable', 'string', 'max:'.self::MAX_INFO],
            'store_notes' => ['nullable', 'string', 'max:'.self::MAX_INFO],
        ];
    }

    public function read(SallaStore $store): array
    {
        $widget = $this->widget($store);
        $saved = (array) $widget->settings;
        $info = (array) data_get($store->store_facts, 'merchant', []);

        return [
            'is_enabled' => (bool) $widget->is_enabled,
            'use_store_color' => empty($saved['accent']),
            'accent' => $saved['accent'] ?? null,
            'position' => $saved['position'] ?? 'left',
            'assistant_name' => (string) ($saved['assistant_name'] ?? ''),
            'welcome_text' => (string) ($saved['welcome_text'] ?? ''),
            'return_policy' => (string) ($info['return_policy'] ?? ''),
            'store_notes' => (string) ($info['notes'] ?? ''),
        ];
    }

    public function save(SallaStore $store, array $data): array
    {
        $widget = $this->widget($store);
        $settings = (array) $widget->settings;

        $settings['position'] = $data['position'];
        $settings['assistant_name'] = $this->clean($data['assistant_name'] ?? '');
        $settings['welcome_text'] = $this->clean($data['welcome_text'] ?? '');

        if ($data['use_store_color']) {
            $settings = Arr::except($settings, ['accent', 'accent_secondary']);
        } else {
            $settings['accent'] = strtoupper((string) $data['accent']);
        }

        $widget->forceFill(['is_enabled' => (bool) $data['is_enabled'], 'settings' => $settings])->save();

        $facts = (array) $store->store_facts;
        $facts['merchant'] = [
            'return_policy' => $this->clean($data['return_policy'] ?? ''),
            'notes' => $this->clean($data['store_notes'] ?? ''),
        ];
        $store->forceFill(['store_facts' => $facts])->save();

        return $this->read($store);
    }

    private function widget(SallaStore $store): WebWidget
    {
        return WebWidget::query()->where('organization_id', $store->organization_id)->first()
            ?? app(SallaWidgetProvisioner::class)->ensure($store);
    }

    /** Keeps line breaks, drops other control characters. */
    private function clean(mixed $text): string
    {
        return trim((string) preg_replace('/[\x{0000}-\x{0009}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}]/u', '', (string) $text));
    }
}
