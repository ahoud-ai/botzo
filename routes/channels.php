<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use App\Services\Chat\ChatAccessService;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chats', function ($user) {
    return true; // Adjust authentication logic if needed
});

// Secure channel for organization-specific chats
Broadcast::channel('chats.ch{organizationId}', function ($user, $organizationId) {
    return app(ChatAccessService::class)
        ->canSubscribeToOrganizationStream($user, (int) $organizationId);
});

Broadcast::channel('chats.user.{userId}', function ($user, $userId) {
    return Auth::guard('user')->check()
        && (int) Auth::guard('user')->id() === (int) $userId;
});
