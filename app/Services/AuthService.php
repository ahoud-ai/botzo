<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Models\Team;
use App\Models\User;
use Auth;
use DB;
use Str;

class AuthService
{
    private $user;
    private $organizationSessionService;

    public function __construct($user)
    {
        $this->user = $user;
        $this->organizationSessionService = app(OrganizationSessionService::class);
    }

    public function authenticateSession($request)
    {
        if($this->user->role != 'user'){
            Auth::guard('admin')->attempt(['email' => $request->email, 'password' => $request->password]);
        } else {
            Auth::guard('user')->attempt(['email' => $request->email, 'password' => $request->password]);
            $organizationId = $this->organizationSessionService->firstOrganizationIdForUser($this->user->id);

            if ($organizationId) {
                session()->put('current_organization', $organizationId);
            } else {
                session()->forget('current_organization');
            }
        }
    }
}
