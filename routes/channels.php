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
    file_put_contents(storage_path('logs/temp_channel_diag.log'), date('c') . ' ' . json_encode([
        'passed_user_id' => $user?->id,
        'passed_user_class' => $user ? get_class($user) : null,
        'organization_id' => $organizationId,
        'auth_default_user_id' => Auth::user()?->id,
        'guard_user_check' => Auth::guard('user')->check(),
        'guard_user_id' => Auth::guard('user')->id(),
        'guard_admin_check' => Auth::guard('admin')->check(),
        'guard_admin_id' => Auth::guard('admin')->id(),
    ]) . PHP_EOL, FILE_APPEND);

    return true; // TEMP_DIAG: force-allow to isolate whether the closure is even reached
});

Broadcast::channel('chats.user.{userId}', function ($user, $userId) {
    return Auth::guard('user')->check()
        && (int) Auth::guard('user')->id() === (int) $userId;
});
