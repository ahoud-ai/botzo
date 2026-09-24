<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Organization;
use App\Services\EmbeddedSignup\EmbeddedSignupAuditService;
use App\Services\EmbeddedSignup\EmbeddedSignupGate;
use App\Services\EmbeddedSignup\EmbeddedSignupReconciliationService;
use App\Services\EmbeddedSignup\EmbeddedSignupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fallback path for Embedded Signup persistence: the popup (opened via
 * window.open() to Meta's hosted onboarding URL rather than FB.login()) never
 * posts a WA_EMBEDDED_SIGNUP message back to our opener window and never
 * redirects back to our redirect_uri either — confirmed by live testing — so
 * SettingController::exchangeEmbeddedSignupCode() never fires even after a
 * client successfully shares their WABA with us on Meta's side.
 *
 * These two endpoints ask Meta directly instead of waiting for the browser
 * (see EmbeddedSignupReconciliationService for why the System User token makes
 * that possible without a per-user OAuth code): snapshot() records which WABAs
 * are shared with us right before the popup opens, reconcile() re-checks after
 * it closes and persists whichever one is new — reusing the same persistence
 * path (SettingController::persistWhatsappSettings()) that the code-exchange
 * flow already uses, so both paths write the connection identically.
 *
 * Split out from SettingController (rather than added there) to keep it under
 * its ratcheted file-size budget — see ArchitectureBudgetGuardTest.
 */
class EmbeddedSignupReconciliationController extends BaseController
{
    private EmbeddedSignupGate $embeddedSignupGate;
    private EmbeddedSignupAuditService $embeddedSignupAuditService;

    public function __construct()
    {
        $this->embeddedSignupGate = new EmbeddedSignupGate();
        $this->embeddedSignupAuditService = new EmbeddedSignupAuditService();
    }

    public function snapshot(Request $request)
    {
        if ($response = $this->abortIfDemo()) {
            return $response;
        }

        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.manage', $organizationId);

        if (!$this->embeddedSignupGate->isGloballyEnabled()
            || !$this->embeddedSignupGate->isPlanEnabled($organizationId)
            || !$this->embeddedSignupGate->isOrganizationEnabled($organizationId)) {
            return response()->json(['success' => false, 'message' => __('Embedded signup is not available.')], 422);
        }

        $ids = app(EmbeddedSignupReconciliationService::class)->currentClientWabaIds();

        if ($ids === null) {
            return response()->json([
                'success' => false,
                'message' => __('Unable to reach Meta right now. Please try again in a moment.'),
            ], 422);
        }

        Cache::put($this->snapshotCacheKey($organizationId), $ids, now()->addMinutes(20));

        return response()->json(['success' => true]);
    }

    public function reconcile(Request $request)
    {
        if ($response = $this->abortIfDemo()) {
            return $response;
        }

        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.manage', $organizationId);
        $userId = auth()->id();

        $reconciliationService = app(EmbeddedSignupReconciliationService::class);
        $currentIds = $reconciliationService->currentClientWabaIds();

        if ($currentIds === null) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => __('Unable to reach Meta right now. Please try again in a moment.'),
            ], 422);
        }

        $baselineIds = Cache::get($this->snapshotCacheKey($organizationId), []);
        $newIds = array_values(array_diff($currentIds, $baselineIds));

        // Never re-link a WABA another organization already owns, even if it
        // looks "new" against a stale/expired baseline.
        $newIds = array_values(array_filter($newIds, function (string $wabaId) {
            return !Organization::where('metadata->whatsapp->waba_id', $wabaId)->exists();
        }));

        if (count($newIds) === 0) {
            return response()->json(['success' => true, 'status' => 'pending']);
        }

        if (count($newIds) > 1) {
            $this->embeddedSignupAuditService->record(
                'reconcile.ambiguous',
                'failed',
                ['candidate_waba_ids' => $newIds],
                'RECONCILE_AMBIGUOUS',
                $organizationId,
                $userId,
                __('Multiple new WhatsApp accounts were detected at once. Please contact support to complete the connection.')
            );

            return response()->json([
                'success' => false,
                'status' => 'ambiguous',
                'message' => __('Multiple new WhatsApp accounts were detected at once. Please contact support to complete the connection.'),
            ], 422);
        }

        $wabaId = $newIds[0];
        $embeddedSignupService = new EmbeddedSignupService();
        $accessToken = $reconciliationService->systemUserToken();

        $overrideResponse = $embeddedSignupService->overrideWabaCallback($accessToken, $wabaId);
        if (!$overrideResponse->success) {
            $this->embeddedSignupAuditService->record(
                'reconcile.override_webhook',
                'failed',
                ['waba_id' => $wabaId],
                $overrideResponse->code ?? 'WEBHOOK_OVERRIDE_FAILED',
                $organizationId,
                $userId,
                $overrideResponse->message
            );

            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => $this->embeddedSignupMessage($overrideResponse->code ?? 'WEBHOOK_OVERRIDE_FAILED', $overrideResponse->message),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $persistResponse = app(SettingController::class)->persistWhatsappSettings(
                $accessToken,
                null,
                $embeddedSignupService->getAppId(),
                null,
                $wabaId,
                false,
                true,
                'embedded_signup',
                null
            );

            if (!$persistResponse->success) {
                DB::rollBack();

                $this->embeddedSignupAuditService->record(
                    'reconcile.persist',
                    'failed',
                    ['waba_id' => $wabaId],
                    $persistResponse->code ?? 'WABA_RESOLUTION_FAILED',
                    $organizationId,
                    $userId,
                    $persistResponse->message
                );

                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => $this->embeddedSignupMessage($persistResponse->code ?? 'WABA_RESOLUTION_FAILED', $persistResponse->message),
                ], 422);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Embedded signup reconciliation failed during persistence', [
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'waba_id' => $wabaId,
                'error' => $e->getMessage(),
            ]);

            $this->embeddedSignupAuditService->record(
                'reconcile.persist',
                'failed',
                ['waba_id' => $wabaId],
                'WABA_RESOLUTION_FAILED',
                $organizationId,
                $userId,
                __('Unable to finalize embedded signup connection.')
            );

            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => __('Unable to finalize embedded signup connection.'),
            ], 422);
        }

        Cache::forget($this->snapshotCacheKey($organizationId));

        $this->embeddedSignupAuditService->record(
            'reconcile.completed',
            'success',
            ['waba_id' => $wabaId],
            null,
            $organizationId,
            $userId,
            __('Embedded signup connected successfully.')
        );

        return response()->json(['success' => true, 'status' => 'connected']);
    }

    private function snapshotCacheKey(?int $organizationId): string
    {
        return "embedded_signup_snapshot:{$organizationId}";
    }

    /**
     * Mirrors SettingController::embeddedSignupMessage() — small enough that
     * duplicating it here is simpler and safer than exposing it across controllers.
     */
    private function embeddedSignupMessage(?string $code, ?string $defaultMessage = null): string
    {
        $messageMap = [
            'EMBEDDED_DISABLED' => __('Embedded signup is currently disabled.'),
            'META_CONFIG_MISSING' => __('Embedded signup is not configured correctly. Contact the administrator.'),
            'CODE_EXCHANGE_FAILED' => __('Unable to exchange embedded signup code.'),
            'LONG_TOKEN_EXCHANGE_FAILED' => __('Unable to generate a long-lived access token.'),
            'WABA_RESOLUTION_FAILED' => __('Unable to resolve WhatsApp business account from embedded signup.'),
            'WEBHOOK_OVERRIDE_FAILED' => __('Unable to override webhook callback URL.'),
        ];

        if ($code && isset($messageMap[$code])) {
            return $defaultMessage ?: $messageMap[$code];
        }

        return $defaultMessage ?: __('Something went wrong. Refresh the page and try again');
    }

    /**
     * Mirrors SettingController::abortIfDemo() — kept local rather than shared
     * so this controller has no dependency on SettingController beyond the one
     * explicit persistWhatsappSettings() call above.
     */
    private function abortIfDemo()
    {
        $organizationId = session()->get('current_organization');

        if (app()->environment('demo') && $organizationId == 1) {
            return back()->with(
                'status', [
                    'type' => 'error',
                    'message' => __('You cannot perform this action using the demo account. To test this feature, please create your own account.')
                ]
            );
        }

        return null;
    }
}
