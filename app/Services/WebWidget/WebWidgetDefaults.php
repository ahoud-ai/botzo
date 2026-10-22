<?php

namespace App\Services\WebWidget;

use App\Models\Setting;

/**
 * The widget defaults every organization starts from: config('web_widget.defaults') with the
 * admin's overrides (setting "web_widget_defaults"), plus the platform-wide voice call limits.
 */
class WebWidgetDefaults
{
    public const SETTING_KEY = 'web_widget_defaults';

    private ?array $stored = null;

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $defaults = (array) config('web_widget.defaults', []);

        return array_replace($defaults, array_intersect_key((array) ($this->stored()['settings'] ?? []), $defaults));
    }

    /**
     * @return array{call_max_minutes:int, daily_minutes_cap:int}
     */
    public function voiceLimits(): array
    {
        $limits = array_replace((array) config('web_widget.voice_limits', []), (array) ($this->stored()['voice_limits'] ?? []));

        return [
            'call_max_minutes' => max(1, (int) ($limits['call_max_minutes'] ?? 5)),
            'daily_minutes_cap' => max(0, (int) ($limits['daily_minutes_cap'] ?? 0)),
        ];
    }

    public function save(array $settings, array $voiceLimits): void
    {
        Setting::updateOrCreate(['key' => self::SETTING_KEY], ['value' => json_encode([
            'settings' => array_intersect_key($settings, (array) config('web_widget.defaults', [])),
            'voice_limits' => array_intersect_key($voiceLimits, (array) config('web_widget.voice_limits', [])),
        ], JSON_UNESCAPED_UNICODE)]);

        $this->stored = null;
    }

    private function stored(): array
    {
        if ($this->stored === null) {
            $decoded = json_decode((string) Setting::where('key', self::SETTING_KEY)->value('value'), true);
            $this->stored = is_array($decoded) ? $decoded : [];
        }

        return $this->stored;
    }
}
