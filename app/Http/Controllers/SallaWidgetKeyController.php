<?php

namespace App\Http\Controllers;

use App\Models\SallaStore;
use App\Models\WebWidget;
use App\Services\Salla\SallaStoreAccess;
use App\Services\Salla\SallaStoreInstaller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Tells the script in a Salla store which widget to open. Every snippet is the same, so the store
 * is named by its Salla id. The key is public anyway; it is only given to the store's own domain.
 */
class SallaWidgetKeyController extends Controller
{
    public function __construct(
        private readonly SallaStoreAccess $access,
        private readonly SallaStoreInstaller $installer,
    ) {
    }

    public function __invoke(Request $request, string $merchant): JsonResponse
    {
        $store = SallaStore::query()->where('merchant_id', $merchant)->first();
        $widget = $store
            ? WebWidget::query()->where('organization_id', $store->organization_id)->where('is_enabled', true)->first()
            : null;

        if (! $widget || ! $this->access->assistantAllowed((int) $store->organization_id)) {
            return $this->unavailable();
        }

        $origin = $request->headers->get('Origin');

        if (! $widget->allowsOrigin($origin) && ! $this->originIsNewStoreDomain($store, $widget, $origin)) {
            return $this->unavailable();
        }

        return response()->json(['key' => $widget->public_key])
            ->header('Cache-Control', 'private, max-age=300')
            ->header('Vary', 'Origin');
    }

    /**
     * A merchant can move the store to a new domain and Salla sends Botzo no event for it, so an
     * unknown domain makes Botzo ask Salla again, at most once an hour per store.
     */
    private function originIsNewStoreDomain(SallaStore $store, WebWidget $widget, ?string $origin): bool
    {
        if (! $origin || ! $store->access_token || ! Cache::add('salla:domain-check:'.$store->id, 1, now()->addHour())) {
            return false;
        }

        $this->installer->backfillDetails($store);

        return $widget->refresh()->allowsOrigin($origin);
    }

    private function unavailable(): JsonResponse
    {
        return response()->json(['error' => 'widget_unavailable'], 403);
    }
}
