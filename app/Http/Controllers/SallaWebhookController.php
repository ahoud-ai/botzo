<?php

namespace App\Http\Controllers;

use App\Services\Salla\SallaWebhookProcessor;
use App\Services\Salla\SallaWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class SallaWebhookController extends Controller
{
    public function __invoke(Request $request, SallaWebhookVerifier $verifier, SallaWebhookProcessor $processor): JsonResponse
    {
        if (! $verifier->isValid($request)) {
            Log::warning('salla.webhook.rejected', ['strategy' => (string) $request->header('X-Salla-Security-Strategy')]);

            return response()->json(['ok' => false], 401);
        }

        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return response()->json(['ok' => false], 400);
        }

        try {
            $processor->handle($request->getContent(), $payload);
        } catch (Throwable $exception) {
            Log::error('salla.webhook.failed', ['event' => (string) ($payload['event'] ?? ''), 'merchant_id' => $payload['merchant'] ?? null, 'error' => $exception::class]);

            return response()->json(['ok' => false], 500);
        }

        return response()->json(['ok' => true]);
    }
}
