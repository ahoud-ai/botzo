<?php

namespace App\Http\Controllers;

use App\Services\Webhooks\WebhookInspector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookInspectorController extends Controller
{
    public function store(Request $request, string $token, WebhookInspector $inspector): JsonResponse
    {
        $organizationId = $inspector->organizationFor($token);
        if ($organizationId === null) {
            return response()->json(['received' => false], 404);
        }

        $stored = $inspector->store($organizationId, $request);

        return response()->json(['received' => true, 'signature_valid' => $stored->signature_valid]);
    }
}
