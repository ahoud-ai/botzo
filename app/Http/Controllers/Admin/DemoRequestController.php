<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller as BaseController;
use App\Models\DemoRequest;
use App\Services\DemoRequests\DemoRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

/**
 * Demo requests from the website form and from Mochi, with a WhatsApp link to reach each person
 * and a simple status the team moves along (new, contacted, done).
 */
class DemoRequestController extends BaseController
{
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), DemoRequest::STATUSES, true) ? $request->query('status') : null;
        $search = trim((string) $request->query('search', ''));

        $rows = DemoRequest::query()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($inner) => $inner->where('full_name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('business_activity', 'like', $like));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DemoRequest $item) => [
                'id' => $item->id,
                'full_name' => $item->full_name,
                'phone' => $item->phone,
                'email' => $item->email,
                'business_activity' => $item->business_activity,
                'notes' => $item->notes,
                'preferred_time' => $item->preferred_time,
                'source' => $item->source ?: DemoRequest::SOURCE_WEBSITE,
                'status' => $item->status,
                'created_at' => $item->created_at?->toIso8601String(),
                'whatsapp_url' => DemoRequestService::whatsappUrl($item->phone, 'السلام عليكم '.$item->full_name.'، معك فريق بوتزو بخصوص طلب العرض التوضيحي'),
            ]);

        $counts = DemoRequest::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('Admin/DemoRequest/Index', [
            'title' => __('Demo requests'),
            'rows' => $rows,
            'filters' => ['status' => $status, 'search' => $search !== '' ? $search : null],
            'summary' => [
                'total' => (int) $counts->sum(),
                'new' => (int) ($counts['new'] ?? 0),
                'contacted' => (int) ($counts['contacted'] ?? 0),
                'done' => (int) ($counts['done'] ?? 0),
            ],
        ]);
    }

    public function updateStatus(Request $request, int $id)
    {
        $data = $request->validate(['status' => ['required', 'in:'.implode(',', DemoRequest::STATUSES)]]);

        DemoRequest::findOrFail($id)->update(['status' => $data['status']]);

        return Redirect::back()->with('status', [
            'type' => 'success',
            'message' => __('Status updated'),
        ]);
    }
}
