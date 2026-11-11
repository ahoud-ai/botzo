<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\Chat\ChatAccessService;
use App\Services\IntelliReply\HumanRequestService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Notifications: conversations waiting for the team that this user is allowed to see. Anyone who
 * can be notified sees them, including members limited to their assigned chats.
 */
class HumanRequestController extends Controller
{
    public function __construct(private readonly HumanRequestService $requests)
    {
    }

    public function page(): Response
    {
        $organizationId = $this->organizationId();
        abort_unless($this->canBeNotified($organizationId), 404);

        return Inertia::render('User/Notifications/Index', [
            'title' => __('Notifications'),
            'open' => $this->requests->visibleTo((int) auth()->id(), $organizationId)['items'],
            'closed' => $this->requests->closedVisibleTo((int) auth()->id(), $organizationId),
        ]);
    }

    /** The count beside the menu item and the mobile bell. */
    public function index(): JsonResponse
    {
        $organizationId = $this->organizationId();

        if (! $this->canBeNotified($organizationId)) {
            return response()->json(['available' => false, 'count' => 0]);
        }

        return response()->json(['available' => true, 'count' => $this->requests->visibleTo((int) auth()->id(), $organizationId)['count']]);
    }

    public function close(string $uuid): JsonResponse
    {
        $organizationId = $this->organizationId();

        abort_unless($this->canBeNotified($organizationId), 404);
        abort_unless($this->requests->closeVisible($uuid, (int) auth()->id(), $organizationId), 404);

        return response()->json(['available' => true, 'count' => $this->requests->visibleTo((int) auth()->id(), $organizationId)['count']]);
    }

    private function organizationId(): int
    {
        return (int) session()->get('current_organization');
    }

    private function canBeNotified(int $organizationId): bool
    {
        return app(ChatAccessService::class)->canOpenChatArea($organizationId);
    }
}
