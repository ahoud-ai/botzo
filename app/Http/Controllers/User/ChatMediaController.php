<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Chat;
use App\Models\ChatMedia;
use App\Services\Chat\ChatAccessService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves media received from customers. The files are stored outside the public folder, so
 * only a signed-in team member of the organization who may see that conversation gets them.
 */
class ChatMediaController extends BaseController
{
    public function show(int $id): Response
    {
        $organizationId = (int) session()->get('current_organization');
        $access = app(ChatAccessService::class);

        if (! $access->canOpenChatArea($organizationId)) {
            abort(403, __('You do not have permission to perform this action.'));
        }

        $media = ChatMedia::find($id);
        if (! $media || ! $media->isPrivate() || ! str_starts_with((string) $media->storage_path, ChatMedia::PRIVATE_FOLDER.'/')) {
            abort(404);
        }

        $chat = Chat::with('contact')
            ->where('organization_id', $organizationId)
            ->where('media_id', $media->id)
            ->whereNull('deleted_at')
            ->first();

        if (! $chat || ! $chat->contact || ! $access->canViewContact($chat->contact, $organizationId)) {
            abort(404);
        }

        if ($media->location === 'amazon') {
            return redirect()->away(Storage::disk('s3')->temporaryUrl($media->storage_path, now()->addMinutes(5)));
        }

        if (! Storage::disk('local')->exists($media->storage_path)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($media->storage_path), [
            'Content-Type' => $media->type ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
