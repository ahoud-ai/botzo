<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreMetaVerificationReject;
use App\Http\Resources\MetaVerificationRequestResource;
use App\Models\MetaVerificationRequest;
use App\Services\MetaVerificationRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
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

        $record = MetaVerificationRequest::with('organization')->findOrFail($id);

        return Inertia::render('Admin/MetaVerification/Show', [
            'title' => __('Meta verifications'),
            'record' => $record,
        ]);
    }

    public function advance($id)
    {
        $this->service->advance(MetaVerificationRequest::findOrFail($id));

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
}
