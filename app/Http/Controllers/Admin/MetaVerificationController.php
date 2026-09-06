<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreMetaVerificationReject;
use App\Http\Resources\MetaVerificationRequestResource;
use App\Models\MetaVerificationRequest;
use App\Services\MetaVerificationRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MetaVerificationController extends BaseController
{
    private $service;

    public function __construct(MetaVerificationRequestService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, $id = null)
    {
        if ($id === null) {
            return Inertia::render('Admin/MetaVerification/Index', [
                'title' => __('Meta verifications'),
                'rows' => MetaVerificationRequestResource::collection(
                    MetaVerificationRequest::with('organization')
                        ->withCount('documents')
                        ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                        ->orderBy('created_at', 'desc')
                        ->paginate(15)
                        ->withQueryString()
                ),
                'filters' => [
                    'status' => $request->query('status'),
                ],
                'summary' => [
                    'total' => MetaVerificationRequest::count(),
                    'approved' => MetaVerificationRequest::where('status', 'approved')->count(),
                    'rejected' => MetaVerificationRequest::where('status', 'rejected')->count(),
                    'in_progress' => MetaVerificationRequest::whereNotIn('status', ['approved', 'rejected'])->count(),
                ],
            ]);
        }

        $record = MetaVerificationRequest::with(['organization', 'documents', 'documentRequests.requestedByAdmin'])->findOrFail($id);

        return Inertia::render('Admin/MetaVerification/Show', [
            'title' => __('Meta verifications'),
            'record' => $record,
        ]);
    }

    public function advance(Request $request, $id)
    {
        $this->service->advance(
            MetaVerificationRequest::findOrFail($id),
            $request->input('note') ?: null
        );

        return Redirect::back()->with('status', [
            'type' => 'success',
            'message' => __('Request advanced successfully'),
        ]);
    }

    public function reject(StoreMetaVerificationReject $request, $id)
    {
        $this->service->reject(MetaVerificationRequest::findOrFail($id), $request->validated()['reason']);

        return Redirect::back()->with('status', [
            'type' => 'success',
            'message' => __('Request rejected'),
        ]);
    }

    public function downloadDocument($id, $documentId)
    {
        $document = \App\Models\MetaVerificationDocument::where('meta_verification_request_id', $id)->findOrFail($documentId);

        if (! Storage::disk('local')->exists($document->path)) {
            abort(404);
        }

        return Storage::disk('local')->download($document->path, $document->original_name ?: 'document');
    }

    public function requestDocument(\App\Http\Requests\StoreMetaVerificationDocumentRequest $request, $id)
    {
        $data = $request->validated();

        $this->service->requestAdditionalDocument(
            MetaVerificationRequest::findOrFail($id),
            $data['label'],
            $data['note'] ?? null,
            auth('admin')->id()
        );

        return Redirect::back()->with('status', [
            'type' => 'success',
            'message' => __('Document request sent to the customer'),
        ]);
    }
}
