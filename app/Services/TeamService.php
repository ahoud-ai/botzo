<?php

namespace App\Services;

use App\Helpers\Email;
use App\Models\OrganizationRole;
use App\Models\Team;
use App\Models\TeamInvite;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Auth;
use Str;

class TeamService
{
    public function __construct(
        private readonly SubscriptionFeatureUsageService $subscriptionFeatureUsageService,
        private readonly OrganizationUserSeatUsageService $organizationUserSeatUsageService,
    ) {
    }

    public function invite(object $request)
    {
        $organizationId = (int) session()->get('current_organization');
        $organizationRoleId = $this->resolveAssignableRoleId($organizationId, (int) $request->organization_role_id);
        $email = strtolower(trim((string) $request->email));

        $invitee = User::query()
            ->where('email', $email)
            ->where('role', 'user')
            ->first();

        if ($invitee) {
            $existingTeamMembership = Team::query()
                ->where('organization_id', $organizationId)
                ->where('user_id', $invitee->id)
                ->whereNull('deleted_at')
                ->exists();

            if ($existingTeamMembership) {
                throw ValidationException::withMessages([
                    'email' => __('This user is already a member of this workspace. Edit their role instead.'),
                ]);
            }
        }

        $this->assertTeamCapacity($organizationId, $invitee?->id, $email);

        $invite = TeamInvite::where('organization_id', $organizationId)
            ->where('email', $email)
            ->first();

        if(!$invite){
            $inviteCode = md5(Carbon::now()->timestamp . $organizationId . Str::random(32));
            $invite = TeamInvite::create([
                'organization_id' => $organizationId,
                'email' => $email,
                'code' => $inviteCode,
                'organization_role_id' => $organizationRoleId,
                'invited_by' => auth()->user()->id,
                'expire_at' => now()->addDay(),
            ]);
        } else  {
            $inviteCode = md5(Carbon::now()->timestamp . $organizationId . Str::random(32));
            $invite->code = $inviteCode;
            $invite->email = $email;
            $invite->organization_role_id = $organizationRoleId;
            $invite->expire_at = now()->addDay();
            $invite->invited_by = auth()->user()->id;
            $invite->save();
        }

        //Send invite email
        $inviter = User::where('id', auth()->user()->id)->first();
        Email::sendInvite('Invite', $email, $inviter, url('/invite/' . $inviteCode), [
            'first_name' => $invitee?->first_name,
            'last_name' => $invitee?->last_name,
            'email' => $email,
            'full_name' => $invitee?->full_name,
        ]);

        return $invite;
    }

    public function store(object $request, $inviteCode)
    {
        $invite = TeamInvite::where('code', $inviteCode)
            ->where('expire_at', '>=', now())
            ->first();

        if(!$invite){
            return Redirect::back()->with(
                'status', [
                    'type' => 'error', 
                    'message' => __('This invite code has expired!')
                ]
            );
        }

        try{
            $user = User::query()
                ->where('email', $invite->email)
                ->where('role', 'user')
                ->first();

            $this->assertTeamCapacity(
                (int) $invite->organization_id,
                $user?->id,
                $invite->email,
                [(int) $invite->id]
            );

            if(!$user){
                $user = User::create([
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'email' => $request->email,
                    'password' => bcrypt($request->password),
                    'language' => app()->getLocale(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                //Send new user registration email
                Email::send('Registration', $user, [
                    'link' => Email::defaultAppLink(),
                ]);
            }

            $organizationRoleId = $this->resolveAssignableRoleId((int) $invite->organization_id, (int) $invite->organization_role_id);

            $team = Team::updateOrCreate(
                [
                    'organization_id' => $invite->organization_id,
                    'user_id' => $user->id,
                ],
                [
                    'organization_role_id' => $organizationRoleId,
                    'status' => 'active',
                    'created_by' => $invite->invited_by,
                    'updated_at' => now()
                ]
            );

            $invite->delete();

            Auth::guard('user')->loginUsingId($user->id);

            session()->put('current_organization', $invite->organization_id);
        } catch (\Exception $e) {
            Log::error('Exception: ' . $e->getMessage());
            //return response()->view('errors.custom', [], 500); // Customize the error response as needed
        }
    }

    public function update(object $request, $uuid)
    {
        $organizationId = (int) session()->get('current_organization');
        $organizationRoleId = $this->resolveAssignableRoleId($organizationId, (int) $request->organization_role_id);

        $team = Team::where('uuid', $uuid)
            ->where('organization_id', $organizationId)
            ->with('organizationRole')
            ->first();

        if (! $team) {
            return Redirect::back()->with(
                'status', [
                    'type' => 'error',
                    'message' => __('Team member not found!')
                ]
            );
        }
        
        // Prevent updating owner role
        if ($team && $team->organizationRole && $team->organizationRole->isOwnerRole()) {
            return Redirect::back()->with(
                'status', [
                    'type' => 'error', 
                    'message' => __('You cannot change the owner role!')
                ]
            );
        }
        
        $team->update([
            'organization_role_id' => $organizationRoleId,
            'updated_at' => now()
        ]);
    }

    public function destroy($uuid)
    {
        $organizationId = (int) session()->get('current_organization');
        $team = Team::where('uuid', $uuid)
            ->where('organization_id', $organizationId)
            ->with('organizationRole')
            ->first();

        if(!$team) {
            return Redirect::back()->with(
                'status', [
                    'type' => 'error', 
                    'message' => __('Team member not found!')
                ]
            );
        }

        // Check if user is owner (universal owner role)
        if($team->organizationRole && $team->organizationRole->isOwnerRole()){
            return Redirect::back()->with(
                'status', [
                    'type' => 'error', 
                    'message' => __('You can\'t delete the main admin account!')
                ]
            );
        } else {
            Team::where('uuid', $uuid)
                ->where('organization_id', $organizationId)
                ->delete();

            return Redirect::back()->with(
                'status', [
                    'type' => 'success', 
                    'message' => __('Row deleted successfully!')
                ]
            );
        }
    }

    private function resolveAssignableRoleId(int $organizationId, int $organizationRoleId): int
    {
        if ($organizationId <= 0 || $organizationRoleId <= 0) {
            throw ValidationException::withMessages([
                'organization_role_id' => __('A valid role is required.'),
            ]);
        }

        $role = OrganizationRole::query()
            ->where('id', $organizationRoleId)
            ->where('organization_id', $organizationId)
            ->first();

        if (! $role) {
            $selectedRole = OrganizationRole::query()->find($organizationRoleId);

            throw ValidationException::withMessages([
                'organization_role_id' => $selectedRole?->isOwnerRole()
                    ? __('The Owner role can only be assigned automatically when the workspace is created.')
                    : __('The selected role is not available for this organization.'),
            ]);
        }

        return (int) $role->id;
    }

    /**
     * @param  array<int>  $excludeTeamInviteIds
     */
    private function assertTeamCapacity(
        int $organizationId,
        ?int $userId,
        ?string $email,
        array $excludeTeamInviteIds = []
    ): void {
        $snapshot = $this->subscriptionFeatureUsageService->snapshot($organizationId, 'team_limit');
        $limit = (int) ($snapshot['limit'] ?? 0);

        if ($limit < 0) {
            return;
        }

        $usageSnapshot = $this->organizationUserSeatUsageService->snapshot($organizationId, [
            'team_invite_ids' => $excludeTeamInviteIds,
        ]);
        $targetIdentityKey = $this->organizationUserSeatUsageService->identityKey($userId, $email);

        if ($targetIdentityKey !== null && in_array($targetIdentityKey, $usageSnapshot['identity_keys'] ?? [], true)) {
            return;
        }

        if ((int) ($usageSnapshot['used'] ?? 0) >= $limit) {
            throw ValidationException::withMessages([
                'email' => __('You have reached your limit of team members. Please upgrade your account to add more!'),
            ]);
        }
    }
};
