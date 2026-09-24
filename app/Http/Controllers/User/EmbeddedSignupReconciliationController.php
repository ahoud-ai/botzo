<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Organization;
use App\Services\EmbeddedSignup\EmbeddedSignupAuditService;
use App\Services\EmbeddedSignup\EmbeddedSignupReconciliationService;
use App\Services\EmbeddedSignup\EmbeddedSignupService;
use Illuminate\Http\Request;
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
 * reconcile() asks Meta directly instead of waiting for the browser (see
 * EmbeddedSignupReconciliationService for why the System User token makes
 * that possible without a per-user OAuth code): any WABA visible to our
 * business but not yet linked to an organization is a candidate. Exactly one
 * candidate links automatically; more than one (two orgs mid-connecting, or
 * — as hit repeatedly testing this with Meta's reusable sandbox numbers,
 * which stay shared even after we disconnect them locally — leftover
 * previously-unclaimed WABAs) is shown to the user to pick from via
 * select(). Both reuse the same persistence path
 * (SettingController::persistWhatsappSettings()) that the code-exchange flow
 * already uses, so every path writes the connection identically.
 *
 * An earlier version narrowed candidates to ones "new since a pre-popup
 * snapshot" to auto-guess among several — dropped because it actively hid
 * the ambiguous case (silently returned "pending" forever whenever the extra
 * WABAs were already unclaimed before the snapshot was taken, confirmed live)
 * instead of surfacing it to the picker below.
 *
 * Split out from SettingController (rather than added there) to keep it under
 * its ratcheted file-size budget — see ArchitectureBudgetGuardTest.
 */
class EmbeddedSignupReconciliationController extends BaseController
{
    private EmbeddedSignupAuditService $embeddedSignupAuditService;

    public function __construct()
    {
        $this->embeddedSignupAuditService = new EmbeddedSignupAuditService();
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
            $this->embeddedSignupAuditService->record(
                'reconcile.lookup_failed',
                'failed',
                [],
                'META_LOOKUP_FAILED',
                $organizationId,
                $userId,
                __('Unable to reach Meta right now. Please try again in a moment.')
            );

            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => __('Unable to reach Meta right now. Please try again in a moment.'),
            ], 422);
        }

        // "Unclaimed" (visible to our business, but not yet linked to any
        // organization) is the whole signal — never re-link a WABA another
        // organization already owns.
        $unclaimedIds = array_values(array_filter($currentIds, function (string $wabaId) {
            return !Organization::where('metadata->whatsapp->waba_id', $wabaId)->exists();
        }));

        if (count($unclaimedIds) === 0) {
            return response()->json(['success' => true, 'status' => 'pending']);
        }

        // More than one WABA is unclaimed at once — genuinely ambiguous for a
        // first-time real client (two orgs mid-connecting simultaneously), but
        // this is also what happens whenever we've been testing repeatedly with
        // Meta's reusable sandbox numbers, since Meta never "unshares" a WABA
        // just because we disconnect it locally. Rather than fail with "contact
        // support", let the user pick — see select() below.
        if (count($unclaimedIds) > 1) {
            $candidates = array_map(function (string $wabaId) use ($reconciliationService) {
                $details = $reconciliationService->fetchWabaDetails($wabaId);

                return [
                    'waba_id' => $wabaId,
                    'name' => $details['name'] ?? $wabaId,
                    'phone' => $details['phone'],
                ];
            }, $unclaimedIds);

            $this->embeddedSignupAuditService->record(
                'reconcile.ambiguous',
                'pending',
                ['candidate_waba_ids' => $unclaimedIds],
                null,
                $organizationId,
                $userId,
                __('Multiple new WhatsApp accounts were detected at once; user is choosing.')
            );

            return response()->json(['success' => true, 'status' => 'ambiguous', 'candidates' => $candidates]);
        }

        return $this->linkWaba($unclaimedIds[0], $reconciliationService, $organizationId, $userId);
    }

    /**
     * Completes the connection for one specific WABA the user picked from the
     * candidate list reconcile() returned when it couldn't disambiguate on its
     * own. Re-checks the id is still genuinely unclaimed rather than trusting
     * the client, since the candidate list is a few seconds stale by the time
     * the user clicks.
     */
    public function select(Request $request)
    {
        if ($response = $this->abortIfDemo()) {
            return $response;
        }

        $organizationId = session()->get('current_organization');
        $this->checkPermission('settings.manage', $organizationId);
        $userId = auth()->id();

        $validated = $request->validate(['waba_id' => ['required', 'string']]);
        $wabaId = $validated['waba_id'];

        $reconciliationService = app(EmbeddedSignupReconciliationService::class);
        $currentIds = $reconciliationService->currentClientWabaIds();

        if ($currentIds === null || !in_array($wabaId, $currentIds, true)) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => __('Unable to reach Meta right now. Please try again in a moment.'),
            ], 422);
        }

        if (Organization::where('metadata->whatsapp->waba_id', $wabaId)->exists()) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => __('This WhatsApp account is already connected to another organization.'),
            ], 422);
        }

        return $this->linkWaba($wabaId, $reconciliationService, $organizationId, $userId);
    }

    private function linkWaba(string $wabaId, EmbeddedSignupReconciliationService $reconciliationService, ?int $organizationId, ?int $userId)
    {
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
