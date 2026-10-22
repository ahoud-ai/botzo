<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\WebWidget\SiteWidget;
use App\Services\WebWidget\WebWidgetDefaults;
use App\Services\WebWidget\WebWidgetSettingsUpdater;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Admin control of the website widget: the defaults every organization starts from, the shared
 * voice call limits, and the widget on Botzo's own site.
 */
class WebWidgetSettingsController extends Controller
{
    public function index(WebWidgetDefaults $defaults, SiteWidget $site)
    {
        $organization = $site->organization();
        $widget = $site->widget();

        return Inertia::render('Admin/Setting/Features/WebWidget', [
            'title' => __('Website widget'),
            'defaults' => $defaults->settings(),
            'voiceLimits' => $defaults->voiceLimits(),
            'themes' => array_values((array) config('web_widget.themes', [])),
            'voices' => array_values((array) config('web_widget.voices', [])),
            'site' => $organization ? [
                'organization' => ['name' => $organization->name, 'uuid' => $organization->uuid],
                'exists' => $widget !== null,
                'is_enabled' => (bool) ($widget?->is_enabled ?? false),
                'allowed_domains' => array_values((array) ($widget?->allowed_domains ?? [])),
                'daily_reply_limit' => (int) ($widget?->daily_reply_limit ?? 300),
                'settings' => $widget?->settingsWithDefaults() ?? $defaults->settings(),
            ] : null,
            'limits' => [
                'domains' => (int) config('web_widget.max_domains', 10),
                'suggestions' => (int) config('web_widget.max_suggestions', 4),
                'suggestion_length' => (int) config('web_widget.max_suggestion_length', 60),
                'welcome_length' => (int) config('web_widget.max_welcome_length', 300),
                'pages' => (int) config('web_widget.max_pages', 10),
                'page_label_length' => (int) config('web_widget.max_page_label_length', 40),
            ],
        ]);
    }

    public function updateDefaults(Request $request, WebWidgetDefaults $defaults)
    {
        $data = $request->validate([
            'theme' => ['required', Rule::in((array) config('web_widget.themes', []))],
            'accent' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_secondary' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'position' => ['required', Rule::in(['right', 'left'])],
            'assistant_name' => ['nullable', 'string', 'max:60'],
            'welcome_text' => ['nullable', 'string', 'max:'.(int) config('web_widget.max_welcome_length', 300)],
            'suggestions' => ['present', 'array', 'max:'.(int) config('web_widget.max_suggestions', 4)],
            'suggestions.*' => ['nullable', 'string', 'max:'.(int) config('web_widget.max_suggestion_length', 60)],
            'voice' => ['required', Rule::in((array) config('web_widget.voices', []))],
            'call_max_minutes' => ['required', 'integer', 'min:1', 'max:30'],
            'daily_minutes_cap' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        $defaults->save([
            'theme' => $data['theme'],
            'accent' => strtoupper($data['accent']),
            'accent_secondary' => strtoupper($data['accent_secondary']),
            'position' => $data['position'],
            'assistant_name' => trim((string) ($data['assistant_name'] ?? '')),
            'welcome_text' => trim((string) ($data['welcome_text'] ?? '')),
            'suggestions' => array_values(array_filter(array_map(fn ($item) => trim((string) $item), $data['suggestions']), fn ($item) => $item !== '')),
            'voice' => $data['voice'],
        ], [
            'call_max_minutes' => (int) $data['call_max_minutes'],
            'daily_minutes_cap' => (int) $data['daily_minutes_cap'],
        ]);

        return back()->with('status', ['type' => 'success', 'message' => __('Widget defaults saved')]);
    }

    public function updateSite(Request $request, SiteWidget $site, WebWidgetSettingsUpdater $updater)
    {
        $organization = $site->organization();
        abort_unless($organization, 404);

        $updater->save((int) $organization->id, $request->validate($updater->rules()), null);
        $site->forget();

        return back()->with('status', ['type' => 'success', 'message' => __('Botzo website widget saved')]);
    }
}
