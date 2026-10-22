<?php

namespace App\Services\WebWidget;

use App\Models\WebWidget;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validates and saves a widget's settings; shared by the customer's widget page and the admin's
 * botzo.net widget page so both follow the same rules.
 */
class WebWidgetSettingsUpdater
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_enabled' => ['required', 'boolean'],
            'allowed_domains' => ['present', 'array', 'max:'.(int) config('web_widget.max_domains', 10)],
            'allowed_domains.*' => ['nullable', 'string', 'max:255'],
            'theme' => ['required', Rule::in((array) config('web_widget.themes', ['mochi', 'botzo', 'classic']))],
            'accent' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_secondary' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'position' => ['required', Rule::in(['right', 'left'])],
            'assistant_name' => ['nullable', 'string', 'max:60'],
            'business_label' => ['nullable', 'string', 'max:60'],
            'welcome_text' => ['nullable', 'string', 'max:'.(int) config('web_widget.max_welcome_length', 300)],
            'suggestions' => ['present', 'array', 'max:'.(int) config('web_widget.max_suggestions', 4)],
            'suggestions.*' => ['nullable', 'string', 'max:'.(int) config('web_widget.max_suggestion_length', 60)],
            'pages' => ['nullable', 'array', 'max:'.(int) config('web_widget.max_pages', 10)],
            'pages.*.label' => ['nullable', 'string', 'max:100'],
            'pages.*.url' => ['nullable', 'string', 'max:500'],
            'hide_branding' => ['nullable', 'boolean'],
            'voice_enabled' => ['nullable', 'boolean'],
            'voice' => ['nullable', Rule::in((array) config('web_widget.voices', ['alloy']))],
            'daily_reply_limit' => ['required', 'integer', 'min:0', 'max:5000'],
        ];
    }

    /**
     * Save validated $data on the organization's widget (created with a public key when missing).
     *
     * @throws ValidationException
     */
    public function save(int $organizationId, array $data, ?int $createdBy): WebWidget
    {
        $domains = $this->domains((array) $data['allowed_domains']);

        if ($data['is_enabled'] && $domains === []) {
            throw ValidationException::withMessages(['allowed_domains' => __('Add at least one website domain before turning the widget on.')]);
        }

        $widget = WebWidget::firstOrNew(['organization_id' => $organizationId]);
        if (! $widget->exists) {
            $widget->public_key = WebWidget::generatePublicKey();
            $widget->created_by = $createdBy;
        }

        // Pages are checked against the domains being saved now.
        $widget->allowed_domains = $domains;
        $pages = WebWidgetPages::normalize((array) ($data['pages'] ?? []), $widget);
        if ($pages['errors'] !== []) {
            throw ValidationException::withMessages($pages['errors']);
        }

        $defaults = app(WebWidgetDefaults::class)->settings();

        $widget->fill([
            'is_enabled' => (bool) $data['is_enabled'],
            'daily_reply_limit' => (int) $data['daily_reply_limit'],
            'settings' => [
                'theme' => $data['theme'],
                'accent' => strtoupper($data['accent']),
                'accent_secondary' => strtoupper((string) ($data['accent_secondary'] ?? $defaults['accent_secondary'])),
                'position' => $data['position'],
                'assistant_name' => trim((string) ($data['assistant_name'] ?? '')),
                'business_label' => trim((string) ($data['business_label'] ?? '')),
                'welcome_text' => trim((string) ($data['welcome_text'] ?? '')),
                'suggestions' => array_values(array_filter(array_map(fn ($item) => trim((string) $item), (array) $data['suggestions']), fn ($item) => $item !== '')),
                'pages' => $pages['pages'],
                'hide_branding' => (bool) ($data['hide_branding'] ?? false),
                'voice_enabled' => (bool) ($data['voice_enabled'] ?? false),
                'voice' => (string) ($data['voice'] ?? $defaults['voice']),
            ],
        ])->save();

        return $widget;
    }

    /**
     * @return list<string>
     *
     * @throws ValidationException
     */
    private function domains(array $values): array
    {
        $domains = [];

        foreach ($values as $index => $value) {
            if (trim((string) $value) === '') {
                continue;
            }

            $domain = WebWidget::normalizeDomain((string) $value);
            if ($domain === null) {
                throw ValidationException::withMessages(["allowed_domains.$index" => __('Enter a domain like example.com.')]);
            }
            $domains[] = $domain;
        }

        return array_values(array_unique($domains));
    }
}
