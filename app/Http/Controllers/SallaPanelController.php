<?php

namespace App\Http\Controllers;

use App\Models\SallaProduct;
use App\Models\SallaStore;
use App\Models\WebWidget;
use App\Models\WebWidgetSession;
use App\Services\IntelliReply\AiUsageLimiterService;
use App\Services\Salla\SallaPanelAuth;
use App\Services\Salla\SallaPanelSettings;
use App\Services\Salla\SallaStoreAccess;
use App\Services\SubscriptionPlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The merchant's panel, opened inside Salla's dashboard. Every request names its store only
 * through the panel token, so a merchant can reach only their own store.
 */
class SallaPanelController extends Controller
{
    public function __construct(private readonly SallaPanelAuth $auth)
    {
    }

    public function page(Request $request): Response
    {
        $lang = $request->query('lang') === 'en' ? 'en' : 'ar';
        $theme = $request->query('theme') === 'dark' ? 'dark' : 'light';
        $script = asset('salla-panel.js').'?v='.@filemtime(public_path('salla-panel.js'));

        return response()->view('salla.panel', compact('lang', 'theme', 'script'))->withHeaders([
            'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' https: data:; connect-src 'self' https://*.salla.dev https://*.salla.sa; frame-ancestors https://s.salla.sa",
            'Cache-Control' => 'no-store',
        ]);
    }

    public function session(Request $request): JsonResponse
    {
        $store = $this->auth->storeFromSallaToken((string) $request->input('token', ''));

        return $store
            ? response()->json(['token' => $this->auth->issue($store)])
            : $this->denied();
    }

    public function overview(
        Request $request,
        SallaStoreAccess $access,
        SubscriptionPlanLimitService $plans,
        AiUsageLimiterService $usage,
    ): JsonResponse {
        $store = $this->store($request);
        if (! $store) {
            return $this->denied();
        }

        $organizationId = (int) $store->organization_id;
        $subscription = $plans->activeSubscriptionForOrganization($organizationId);
        $snapshot = $usage->getSnapshot($organizationId);
        $widget = WebWidget::query()->where('organization_id', $organizationId)->first();

        return response()->json([
            'store_name' => (string) $store->store_name,
            'assistant' => [
                'running' => (bool) $widget?->is_enabled && $access->assistantAllowed($organizationId),
                'widget_enabled' => (bool) $widget?->is_enabled,
                'has_subscription' => $subscription !== null,
            ],
            'subscription' => $subscription ? [
                'plan' => (string) ($subscription->plan?->name_ar ?: $subscription->plan?->name),
                'plan_en' => (string) ($subscription->plan?->name_en ?: $subscription->plan?->name),
                'valid_until' => optional($subscription->valid_until ? now()->parse($subscription->valid_until) : null)->toIso8601String(),
            ] : null,
            'replies' => [
                'used' => (int) ($snapshot['system_key_count'] ?? 0),
                'limit' => (int) ($snapshot['system_key_monthly_quota'] ?? -1),
            ],
            'conversations_last_7_days' => $widget
                ? WebWidgetSession::query()->where('web_widget_id', $widget->id)->whereNotNull('contact_id')->where('created_at', '>=', now()->subDays(7))->count()
                : 0,
            'products' => [
                'count' => SallaProduct::query()->where('salla_store_id', $store->id)->count(),
                'synced_at' => $store->products_synced_at?->toIso8601String(),
            ],
        ]);
    }

    public function settings(Request $request, SallaPanelSettings $settings): JsonResponse
    {
        $store = $this->store($request);

        return $store ? response()->json($settings->read($store)) : $this->denied();
    }

    public function updateSettings(Request $request, SallaPanelSettings $settings): JsonResponse
    {
        $store = $this->store($request);
        if (! $store) {
            return $this->denied();
        }

        return response()->json($settings->save($store, $request->validate($settings->rules())));
    }

    private function store(Request $request): ?SallaStore
    {
        return $this->auth->storeFromPanelToken((string) $request->bearerToken());
    }

    private function denied(): JsonResponse
    {
        return response()->json(['error' => 'not_allowed'], 401);
    }
}
