<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\AutomationFlows\PreviewAutomationFlowRequest;
use App\Http\Requests\AutomationFlows\SaveAutomationFlowRequest;
use App\Http\Requests\AutomationFlows\StoreAutomationFlowRequest;
use App\Http\Requests\AutomationFlows\UploadAutomationFlowAssetRequest;
use App\Http\Requests\AutomationFlows\ValidateAutomationFlowRequest;
use App\Models\AutomationFlow;
use App\Models\AutomationFlowAsset;
use App\Services\AutomationFlows\AutomationFlowAccessService;
use App\Services\AutomationFlows\AutomationFlowAssetService;
use App\Services\AutomationFlows\AutomationFlowBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class AutomationFlowController extends Controller
{
    public function __construct(
        private readonly AutomationFlowBuilderService $builder,
        private readonly AutomationFlowAccessService $access,
        private readonly AutomationFlowAssetService $assets,
    ) {}

    public function index(Request $request)
    {
        if ($redirect = $this->featureGuard(true)) {
            return $redirect;
        }

        $organizationId = (int) session('current_organization');
        $this->checkPermission('automations.flows.view', $organizationId);
        $readiness = $this->access->readinessReport($organizationId);

        return Inertia::render('User/Automation/Flows/Index', [
            'title' => __('Automations'),
            'rows' => $this->builder->list($organizationId, $request->string('search')->toString(), $request->string('status')->toString()),
            'filters' => $request->only(['search', 'status']),
            'flowBuilderEnabled' => true,
            'flowBuilderReadiness' => $readiness,
        ]);
    }

    public function store(StoreAutomationFlowRequest $request)
    {
        if ($response = $this->featureGuard(false, true)) {
            return $response;
        }

        $organizationId = (int) session('current_organization');
        $this->checkPermission('automations.flows.add', $organizationId);

        $flow = $this->builder->create($organizationId, (int) auth()->id(), $request->validated());

        return response()->json([
            'status' => 'ok',
            'redirect_to' => '/automation/flows/'.$flow->uuid,
            'flow_uuid' => $flow->uuid,
            'id' => $flow->id,
            'message' => __('Automation created successfully.'),
        ]);
    }

    public function show(string $uuid)
    {
        if ($response = $this->featureGuard(false, true)) {
            return $response;
        }

        $organizationId = (int) session('current_organization');
        $this->checkPermission('automations.flows.view', $organizationId);

        $flow = $this->resolveFlow($uuid);

        return Inertia::render('User/Automation/Flows/Builder', array_merge([
            'title' => __('Automations'),
            'flowBuilderEnabled' => true,
        ], $this->builder->builderPayload($flow, $organizationId)));
    }

    public function update(SaveAutomationFlowRequest $request, string $uuid)
    {
        if ($response = $this->featureGuard(false, true)) {
            return $response;
        }

        $organizationId = (int) session('current_organization');
        $this->checkPermission('automations.flows.edit', $organizationId);

        $flow = $this->resolveFlow($uuid);
        $this->builder->update($flow, $organizationId, (int) auth()->id(), $request->validated());

        return response()->json([
            'status' => 'ok',
            'message' => __('Draft saved successfully.'),
        ]);
    }

    public function autosave(SaveAutomationFlowRequest $request, string $uuid)
    {
        return $this->update($request, $uuid);
    }

    public function validateDraft(ValidateAutomationFlowRequest $request, string $uuid)
    {
        if ($response = $this->featureGuard(false, true)) {
            return $response;
        }

        $organizationId = (int) session('current_organization');
        $this->checkPermission('automations.flows.edit', $organizationId);

        $flow = $this->resolveFlow($uuid);

        return response()->json($this->builder->validateDraft(
            $flow,
            $organizationId,
            $request->validated('graph_json'),
            $request->validated('node_secrets', [])
        ));
    }

    public function preview(PreviewAutomationFlowRequest $request, string $uuid)
    {
        if ($response = $this->featureGuard(false, true)) {
            return $response;
        }

        $organizationId = (int) session('current_organization');
        $this->checkPermission('automations.flows.view', $organizationId);

        $flow = $this->resolveFlow($uuid);

        return response()->json($this->builder->preview(
            $flow,
            $organizationId,
            $request->validated('graph_json'),
            $request->validated('focus_node_id')
        ));
    }

    public function uploadAsset(UploadAutomationFlowAssetRequest $request, string $uuid)
    {
        if ($response = $this->featureGuard(false, true)) {
            return $response;
        }

        $organizationId = (int) session('current_organization');
        $this->checkPermission('automations.flows.edit', $organizationId);

        $flow = $this->resolveFlow($uuid);
        abort_if((int) $flow->organization_id !== $organizationId, 404);

        $asset = $this->assets->store(
            $flow,
            $request->file('file'),
            (int) auth()->id(),
            $request->validated('media_kind')
        );

        return response()->json([
            'status' => 'ok',
            'asset' => $this->assets->toBuilderArray($flow, $asset),
        ]);
    }

    public function deleteAsset(string $uuid, string $assetUuid)
    {
        if ($response = $this->featureGuard(false, true)) {
            return $response;
        }

        $organizationId = (int) session('current_organization');
        $this->checkPermission('automations.flows.edit', $organizationId);

        $flow = $this->resolveFlow($uuid);
        abort_if((int) $flow->organization_id !== $organizationId, 404);

        $asset = AutomationFlowAsset::where('uuid', $assetUuid)->firstOrFail();
        $this->assets->delete($flow, $asset);

        return response()->json([
            'status' => 'ok',
        ]);
    }

    public function showAsset(string $uuid, string $assetUuid)
    {
        if (! $this->access->builderSchemaReady() || ! $this->access->baseSchemaReady()) {
            abort(404);
        }

        $flow = $this->resolveFlow($uuid);
        $asset = AutomationFlowAsset::where('uuid', $assetUuid)->firstOrFail();
        $payload = $this->assets->downloadPayload($flow, $asset);

        return response($payload['content'], 200, [
            'Content-Type' => $payload['mime_type'],
            'Content-Disposition' => 'inline; filename="'.addslashes($payload['filename']).'"',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function publish(Request $request, string $uuid)
    {
        if ($response = $this->featureGuard(false, true)) {
            return $response;
        }

        $organizationId = (int) session('current_organization');
        $this->checkPermission('automations.flows.publish', $organizationId);

        $flow = $this->resolveFlow($uuid);
        $this->builder->publish($flow, $organizationId, (int) auth()->id());

        return response()->json([
            'status' => 'ok',
            'message' => __('Automation published successfully.'),
        ]);
    }

    public function pause(Request $request, string $uuid)
    {
        if ($response = $this->featureGuard(false, true)) {
            return $response;
        }

        $organizationId = (int) session('current_organization');
        $this->checkPermission('automations.flows.publish', $organizationId);

        $flow = $this->resolveFlow($uuid);
        $pause = (bool) $request->boolean('pause', true);
        $flow = $this->builder->pause($flow, $organizationId, $pause);

        return response()->json([
            'status' => 'ok',
            'flow_status' => $flow->status,
        ]);
    }

    public function destroy(string $uuid)
    {
        if ($response = $this->featureGuard(false, true)) {
            return $response;
        }

        $organizationId = (int) session('current_organization');
        $this->checkPermission('automations.flows.delete', $organizationId);

        $flow = $this->resolveFlow($uuid);
        abort_if((int) $flow->organization_id !== $organizationId, 404);
        $flow->delete();

        return response()->json([
            'status' => 'ok',
            'redirect_to' => '/automation/flows',
            'message' => __('Automation deleted successfully.'),
        ]);
    }

    public function duplicate(string $uuid)
    {
        if ($response = $this->featureGuard(false, true)) {
            return $response;
        }

        $organizationId = (int) session('current_organization');
        $this->checkPermission('automations.flows.add', $organizationId);

        $flow = $this->resolveFlow($uuid);
        $duplicate = $this->builder->duplicate($flow, $organizationId, (int) auth()->id());

        return response()->json([
            'status' => 'ok',
            'redirect_to' => '/automation/flows/'.$duplicate->uuid,
            'flow_uuid' => $duplicate->uuid,
            'id' => $duplicate->id,
            'message' => __('Automation duplicated successfully.'),
        ]);
    }

    private function resolveFlow(string $identifier): AutomationFlow
    {
        $identifier = trim($identifier);

        if (ctype_digit($identifier)) {
            return AutomationFlow::query()
                ->where('id', (int) $identifier)
                ->orWhere('uuid', $identifier)
                ->firstOrFail();
        }

        return AutomationFlow::where('uuid', $identifier)->firstOrFail();
    }

    private function featureGuard(bool $allowIndexRedirect = false, bool $requiresBuilderSchema = false)
    {
        if (! $this->access->runtimeEnabled()) {
            if ($allowIndexRedirect) {
                return Redirect::to('/automation/basic');
            }

            abort(404);
        }

        $organizationId = (int) session('current_organization');
        if (! $this->access->addonEnabledForOrganization($organizationId)) {
            if ($allowIndexRedirect) {
                return Redirect::to('/automation/basic');
            }

            abort(403, __('Flow builder feature is not enabled for your organization.'));
        }

        $readiness = $this->access->readinessReport($organizationId);
        if (! $readiness['base_schema_ready']) {
            if ($allowIndexRedirect) {
                return Redirect::to('/automation/basic');
            }

            return $this->schemaIncompleteResponse($readiness, true);
        }

        if (! $requiresBuilderSchema || $readiness['builder_schema_ready']) {
            return null;
        }

        return $this->schemaIncompleteResponse($readiness, false);
    }

    private function schemaIncompleteResponse(array $readiness, bool $baseSetupIncomplete)
    {
        $message = $readiness['message'] ?: __(
            'Flow Builder setup is incomplete. Run the latest migrations and try again.'
        );

        if (request()->expectsJson()) {
            return new JsonResponse([
                'status' => 'error',
                'code' => $baseSetupIncomplete ? 'flow_builder_base_schema_incomplete' : 'flow_builder_schema_incomplete',
                'message' => $message,
                'missing_tables' => $baseSetupIncomplete
                    ? ($readiness['missing_base_tables'] ?? [])
                    : ($readiness['missing_builder_tables'] ?? []),
            ], 409);
        }

        if ($baseSetupIncomplete) {
            abort(503, $message);
        }

        if (request()->isMethod('get')) {
            return Redirect::to('/automation/flows');
        }

        return new JsonResponse([
            'status' => 'error',
            'code' => 'flow_builder_schema_incomplete',
            'message' => $message,
            'missing_tables' => $readiness['missing_builder_tables'] ?? [],
        ], 409);
    }
}
