<?php

namespace Modules\IntelliReply\Controllers;

use App\Helpers\CustomHelper;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Organization;
use App\Models\WebWidget;
use App\Services\WebWidget\WebWidgetEntitlements;
use App\Services\WebWidget\WebWidgetSettingsUpdater;
use Illuminate\Http\Request;
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
            'entitlements' => app(WebWidgetEntitlements::class)->for($organizationId),
            'voices' => array_values((array) config('web_widget.voices', ['alloy'])),
            'limits' => [
                'pages' => (int) config('web_widget.max_pages', 10),
                'page_label_length' => (int) config('web_widget.max_page_label_length', 40),
                'domains' => (int) config('web_widget.max_domains', 10),
                'suggestions' => (int) config('web_widget.max_suggestions', 4),
                'suggestion_length' => (int) config('web_widget.max_suggestion_length', 60),
                'welcome_length' => (int) config('web_widget.max_welcome_length', 300),
            ],
        ]);
    }

    public function update(Request $request, WebWidgetSettingsUpdater $updater)
    {
        $organizationId = (int) session()->get('current_organization');
        $this->checkPermission('automations.edit', $organizationId);
        $this->ensureAiAssistantEnabled($organizationId);

        $updater->save($organizationId, $request->validate($updater->rules()), auth()->id());

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
