<?php

namespace Modules\IntelliReply\Controllers;

use App\Helpers\CustomHelper;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Organization;
use App\Models\WebWidget;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Settings of the organization's website chat widget and its embed code.
 */
class WidgetController extends BaseController
{
    public function index()
    {
        $organizationId = (int) session()->get('current_organization');
        $this->checkPermission('automations.view_all', $organizationId);
        $this->ensureAiAssistantEnabled($organizationId);

        $organization = Organization::findOrFail($organizationId);
        $metadata = json_decode((string) $organization->metadata, true) ?: [];
        $widget = WebWidget::where('organization_id', $organizationId)->first();

        return Inertia::render('IntelliReply::User/Widget', [
            'title' => __('Website widget'),
            'aimodule' => true,
            'organizationName' => (string) $organization->name,
            'aiActive' => (bool) data_get($metadata, 'ai.active', false),
            'widget' => [
                'exists' => $widget !== null,
                'is_enabled' => (bool) ($widget?->is_enabled ?? false),
                'allowed_domains' => array_values((array) ($widget?->allowed_domains ?? [])),
                'settings' => ($widget ?? new WebWidget())->settingsWithDefaults(),
                'daily_reply_limit' => (int) ($widget?->daily_reply_limit ?? 300),
            ],
            'embedCode' => $widget ? $this->embedCode($widget) : null,
            'limits' => [
                'domains' => (int) config('web_widget.max_domains', 10),
                'suggestions' => (int) config('web_widget.max_suggestions', 4),
                'suggestion_length' => (int) config('web_widget.max_suggestion_length', 60),
                'welcome_length' => (int) config('web_widget.max_welcome_length', 300),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $organizationId = (int) session()->get('current_organization');
        $this->checkPermission('automations.edit', $organizationId);
        $this->ensureAiAssistantEnabled($organizationId);

        $data = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'allowed_domains' => ['present', 'array', 'max:'.(int) config('web_widget.max_domains', 10)],
            'allowed_domains.*' => ['nullable', 'string', 'max:255'],
            'accent' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'position' => ['required', Rule::in(['right', 'left'])],
            'assistant_name' => ['nullable', 'string', 'max:60'],
            'welcome_text' => ['nullable', 'string', 'max:'.(int) config('web_widget.max_welcome_length', 300)],
            'suggestions' => ['present', 'array', 'max:'.(int) config('web_widget.max_suggestions', 4)],
            'suggestions.*' => ['nullable', 'string', 'max:'.(int) config('web_widget.max_suggestion_length', 60)],
            'daily_reply_limit' => ['required', 'integer', 'min:0', 'max:5000'],
        ]);

        $domains = [];
        foreach ($data['allowed_domains'] as $index => $value) {
            if (trim((string) $value) === '') {
                continue;
            }

            $domain = WebWidget::normalizeDomain((string) $value);
            if ($domain === null) {
                throw ValidationException::withMessages(["allowed_domains.$index" => __('Enter a domain like example.com.')]);
            }
            $domains[] = $domain;
        }
        $domains = array_values(array_unique($domains));

        if ($data['is_enabled'] && $domains === []) {
            throw ValidationException::withMessages(['allowed_domains' => __('Add at least one website domain before turning the widget on.')]);
        }

        $widget = WebWidget::firstOrNew(['organization_id' => $organizationId]);
        if (! $widget->exists) {
            $widget->public_key = WebWidget::generatePublicKey();
            $widget->created_by = auth()->id();
        }

        $widget->fill([
            'is_enabled' => (bool) $data['is_enabled'],
            'allowed_domains' => $domains,
            'daily_reply_limit' => (int) $data['daily_reply_limit'],
            'settings' => [
                'accent' => strtoupper($data['accent']),
                'position' => $data['position'],
                'assistant_name' => trim((string) ($data['assistant_name'] ?? '')),
                'welcome_text' => trim((string) ($data['welcome_text'] ?? '')),
                'suggestions' => array_values(array_filter(array_map(fn ($item) => trim((string) $item), $data['suggestions']), fn ($item) => $item !== '')),
            ],
        ])->save();

        return back()->with('status', ['type' => 'success', 'message' => __('Website widget saved')]);
    }

    public function regenerateKey()
    {
        $organizationId = (int) session()->get('current_organization');
        $this->checkPermission('automations.edit', $organizationId);
        $this->ensureAiAssistantEnabled($organizationId);

        $widget = WebWidget::where('organization_id', $organizationId)->firstOrFail();
        $widget->forceFill(['public_key' => WebWidget::generatePublicKey()])->save();

        return back()->with('status', ['type' => 'success', 'message' => __('New embed code created. Replace the old code on your website.')]);
    }

    private function embedCode(WebWidget $widget): string
    {
        return sprintf('<script src="%s" data-key="%s" async></script>', e(url('/widget.js')), e($widget->public_key));
    }

    private function ensureAiAssistantEnabled(int $organizationId): void
    {
        if (! CustomHelper::isModuleEnabled('AI Assistant', $organizationId)) {
            abort(404);
        }
    }
}
