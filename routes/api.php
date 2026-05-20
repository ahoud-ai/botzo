<?php

use App\Http\Middleware\AuthenticateBearerToken;
use Illuminate\Support\Facades\Route;

require base_path('routes/api/public.php');
require base_path('routes/api/utilities.php');

Route::middleware([AuthenticateBearerToken::class, 'throttle:developer-api'])->group(function () {
    require base_path('routes/api/messages.php');
    require base_path('routes/api/campaigns.php');
    require base_path('routes/api/contacts.php');
    require base_path('routes/api/contact-groups.php');
    require base_path('routes/api/canned-replies.php');
    require base_path('routes/api/templates.php');
    require base_path('routes/api/verification.php');
});
