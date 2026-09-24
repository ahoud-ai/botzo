<?php

namespace App\Services\EmbeddedSignup;

use App\Models\Organization;
use App\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Fills the gap left by the Embedded Signup popup: when it's opened via
 * window.open() to Meta's hosted onboarding URL (instead of FB.login()), the
 * popup never posts a WA_EMBEDDED_SIGNUP message back to our opener window and
 * never redirects back to our redirect_uri either — confirmed by live testing.
 * So the frontend has no code to exchange and /whatsapp/exchange-code never
 * fires, even though the client's WABA really was shared with our Business
 * Manager on Meta's side.
 *
 * This service asks Meta directly instead of waiting for the browser: our
 * System User access token (Setting `whatsapp_access_token`) already has
 * standing access to any WABA a client shares with our business through this
 * Embedded Signup config, so we can list them via the Tech Provider endpoint
 * (client_whatsapp_business_accounts) and diff against a "before" snapshot to
 * find whichever one just got shared — no user-level OAuth code needed at all.
 */
class EmbeddedSignupReconciliationService
{
    private string $apiVersion;

    public function __construct()
    {
        $this->apiVersion = config('graph.api_version');
    }

    public function systemUserToken(): ?string
    {
        $token = Setting::where('key', 'whatsapp_access_token')->value('value');

        return $token !== null && $token !== '' ? $token : null;
    }

    /**
     * Our own Business Manager ID, discovered dynamically (not stored anywhere)
     * because the System User token is scoped to it.
     *
     * me/businesses returns an empty list for this System User token — confirmed
     * live on production (200 OK, {"data":[]}) — a known limitation already
     * worked around once before in EmbeddedSignupReviewTestService, which is
     * exactly where this fallback comes from: reverse-derive the business id via
     * owner_business_info on a WABA we already know about (any already-connected
     * organization's waba_id), rather than relying on me/businesses at all.
     */
    public function resolveOwnBusinessId(): ?string
    {
        $response = $this->graphGet('me/businesses', ['fields' => 'id,name']);

        if ($response->successful()) {
            $businessId = data_get($response->json(), 'data.0.id');
            if ($businessId) {
                return $businessId;
            }
        }

        foreach ($this->knownWabaIds() as $wabaId) {
            $response = $this->graphGet($wabaId, ['fields' => 'owner_business_info']);

            if (!$response->successful()) {
                continue;
            }

            $businessId = data_get($response->json(), 'owner_business_info.id');
            if ($businessId) {
                return $businessId;
            }
        }

        return null;
    }

    /**
     * waba_id values already stored on any organization — used only to bootstrap
     * resolveOwnBusinessId() above via reverse lookup, not as the reconciliation
     * baseline itself (that's the point of the snapshot/reconcile diff).
     */
    private function knownWabaIds(): array
    {
        $ids = [];

        Organization::whereNotNull('metadata')->chunkById(100, function ($organizations) use (&$ids) {
            foreach ($organizations as $organization) {
                $metadata = json_decode((string) $organization->metadata, true);
                $wabaId = $metadata['whatsapp']['waba_id'] ?? null;

                if ($wabaId) {
                    $ids[] = (string) $wabaId;
                }
            }
        });

        return array_values(array_unique($ids));
    }

    /**
     * WABAs a client has shared with our business as a Tech Provider/Solution
     * Partner — this is the correct endpoint for Embedded Signup connections,
     * distinct from owned_whatsapp_business_accounts (WABAs we own directly).
     */
    public function listClientWabaIds(string $businessId): array
    {
        $ids = [];
        $path = "{$businessId}/client_whatsapp_business_accounts";
        $query = ['fields' => 'id', 'limit' => 200];

        do {
            $response = $this->graphGet($path, $query);

            if (!$response->successful()) {
                break;
            }

            foreach (data_get($response->json(), 'data', []) as $waba) {
                if (!empty($waba['id'])) {
                    $ids[] = (string) $waba['id'];
                }
            }

            $nextUrl = data_get($response->json(), 'paging.next');
            if (!$nextUrl) {
                break;
            }

            // paging.next is a full URL already carrying the access token/query —
            // call it directly instead of re-building path/query ourselves.
            $response = Http::acceptJson()->timeout(20)->get($nextUrl);
            if (!$response->successful()) {
                break;
            }

            foreach (data_get($response->json(), 'data', []) as $waba) {
                if (!empty($waba['id'])) {
                    $ids[] = (string) $waba['id'];
                }
            }

            $path = null;
        } while ($path);

        return array_values(array_unique($ids));
    }

    /**
     * Current full snapshot of client WABA ids visible to our business, or null
     * if the System User token / business lookup isn't usable right now.
     */
    public function currentClientWabaIds(): ?array
    {
        $businessId = $this->resolveOwnBusinessId();

        if (!$businessId) {
            return null;
        }

        return $this->listClientWabaIds($businessId);
    }

    /**
     * Display name for one candidate WABA, used only when reconcile() can't
     * tell which of several unclaimed WABAs the user means and needs to show
     * them a picker (see EmbeddedSignupReconciliationController::reconcile()).
     */
    public function fetchWabaName(string $wabaId): ?string
    {
        $response = $this->graphGet($wabaId, ['fields' => 'name']);

        return $response->successful() ? data_get($response->json(), 'name') : null;
    }

    private function graphGet(string $path, array $query = []): Response
    {
        return Http::acceptJson()
            ->timeout(20)
            ->withToken((string) $this->systemUserToken())
            ->get("https://graph.facebook.com/{$this->apiVersion}/{$path}", $query);
    }
}
