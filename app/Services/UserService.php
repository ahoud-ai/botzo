<?php

namespace App\Services;

use App\Helpers\Email;
use App\Http\Resources\UserResource;
use App\Models\Organization;
use App\Models\OrganizationEmployee;
use App\Models\OrganizationEmployeeAssignment;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\TeamInvite;
use App\Models\User;
use App\Services\OrganizationService;
use DB;
use Str;
use Illuminate\Validation\ValidationException;

class UserService
{
    private const SYSTEM_ADMIN_ROLES = ['admin', 'owner'];

    private $organization;
    private $role;

    public function __construct($role)
    {
        $this->organizationService = new OrganizationService();
        $this->role = $role;
    }

    /**
     * Get all roles based on the provided request filters.
     *
     * @param Request $request
     * @return mixed
     */
    public function get(object $request)
    {
        $users = (new User)->listAll($this->role, $request->query('search'));

        return UserResource::collection($users);
    }

    /**
     * Retrieve a user by its UUID.
     *
     * @param string $uuid
     * @return \App\Models\Role
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getByUuid($request, $id = NULL)
    {
        $roles = Role::all();
    
        if ($id === null) {
            $result = ['user' => null];
    
            if ($this->role === 'user') {
                $result['organizations'] = null;
            } else {
                $result['roles'] = $roles;
            }
    
            return $result;
        }

        $query = User::with('role')->where('id', $id);

        if ($this->role === 'user') {
            $query->where('role', '=', 'user');
            $organizations = $this->organizationService->get($request, $query->first()->id);
            $result = ['user' => $query->first(), 'organizations' => $organizations];
        } else {
            $query->where('role', '!=', 'user');
            $result = ['user' => $query->first(), 'roles' => $roles];
        }

        return $result;
    }

    /**
     * Store a new user based on the provided request data.
     *
     * @param Request $request
     */
    public function store(Object $request, array $options = [])
    {
        $role = $this->role === 'user' ? 'user' : $this->getRoleNameFromUuid($request->input('role'));
        $sendRegistrationEmail = (bool) ($options['send_registration_email'] ?? $request->boolean('send_registration_email', true));
        $registrationLink = $options['registration_link'] ?? Email::defaultAppLink();
        $creationMode = $this->resolveCreationMode($request);

        return DB::transaction(function () use ($request, $role, $sendRegistrationEmail, $registrationLink, $creationMode) {
            $avatarPath = null;

            if ($request->hasFile('avatar')) {
                $avatarPath = $request->file('avatar')->store('public');
            }

            $newUser = User::create([
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'email' => $request->input('email'),
                'avatar' => $avatarPath,
                'role' => $role,
                'phone' => $request->input('phone') ? phone($request->input('phone'))->formatE164() : null,
                'address' => json_encode([
                    'street' => $request->input('street'),
                    'city' => $request->input('city'),
                    'state' => $request->input('state'),
                    'zip' => $request->input('zip'),
                    'country' => $request->input('country'),
                ]),
                'password' => $request->input('password'),
                'language' => app()->getLocale(),
            ]);

            if ($this->role === 'user') {
                $creatorId = auth()->check() ? auth()->id() : (auth('admin')->check() ? auth('admin')->id() : $newUser->id);

                if ($creationMode === 'owner_new_org') {
                    $plan = null;

                    if ($request->input('organization_billing_mode') === 'now' && $request->filled('organization_plan_uuid')) {
                        $plan = SubscriptionPlan::query()
                            ->where('uuid', (string) $request->input('organization_plan_uuid'))
                            ->where('status', 'active')
                            ->whereNull('deleted_at')
                            ->first();
                    }

                    $this->organizationService->createOwnedMainOrganization(
                        $newUser,
                        (string) $request->input('organization_name'),
                        $creatorId,
                        $plan
                    );
                }

                if ($creationMode === 'employee_existing_org') {
                    $company = Organization::query()
                        ->where('uuid', (string) $request->input('company_uuid'))
                        ->where('organization_type', 'main')
                        ->whereNull('deleted_at')
                        ->first();

                    if (!$company) {
                        throw ValidationException::withMessages([
                            'company_uuid' => __('The selected company is invalid.'),
                        ]);
                    }

                    app(CompanyWorkforceService::class)->provisionEmployeeAccessForCompany(
                        $company,
                        $newUser,
                        (array) $request->input('assignments', []),
                        $creatorId
                    );
                }
            }

            if ($sendRegistrationEmail) {
                Email::send('Registration', $newUser, [
                    'link' => $registrationLink,
                ]);
            }

            return $newUser;
        });
    }

    /**
     * Update an existing user.
     *
     * @param Request $request
     * @param string $uuid
     * @return \App\Models\User
     */
    public function update($request, $id)
    {
        $user = $this->managedUserQuery()
            ->where('id', $id)
            ->firstOrFail();
        $role = $this->resolveRoleForUpdate($user, $request);
        $avatarPath = $user->avatar;

        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        $payload = [
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'email' => $request->input('email'),
            'avatar' => $avatarPath,
            'role' => $role,
            'phone' => $request->input('phone') ? phone($request->input('phone'))->formatE164() : null,
            'address' => json_encode([
                'street' => $request->street,
                'city' => $request->city,
                'state' => $request->state,
                'zip' => $request->zip,
                'country' => $request->country,
            ]),
        ];

        if ($request->filled('password')) {
            $payload['password'] = (string) $request->input('password');
        }

        $user->update($payload);

        return $user;
    }

    /**
     * Remove the specified user.
     *
     * @param string $uuid
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function destroy($id)
    {
        $user = $this->managedUserQuery()
            ->with([
                'teams' => fn ($query) => $query->with(['organization', 'organizationRole']),
            ])
            ->where('id', $id)
            ->firstOrFail();

        $ownsMainOrganization = $user->teams->contains(function (Team $team) {
            return $team->organizationRole?->isOwnerRole()
                && $team->organization?->organization_type === 'main';
        });

        if ($user->isSystemOwner()) {
            throw ValidationException::withMessages([
                'user' => __('The primary system owner cannot be deleted.'),
            ]);
        }

        if ($this->isSystemAdminRole($user->role)) {
            $this->ensureAnotherActiveSystemAdminExists($user);
        }

        if ($ownsMainOrganization) {
            throw ValidationException::withMessages([
                'user' => __('This account owns one or more main organizations. Transfer ownership before deleting the platform account.'),
            ]);
        }

        DB::transaction(function () use ($user) {
            $user->delete();

            Team::query()
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->delete();

            TeamInvite::query()
                ->where('email', strtolower((string) $user->email))
                ->delete();

            $employeeIds = OrganizationEmployee::query()
                ->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->orWhere('email', strtolower((string) $user->email));
                })
                ->whereNull('deleted_at')
                ->pluck('id')
                ->map(fn ($employeeId) => (int) $employeeId)
                ->all();

            if ($employeeIds !== []) {
                OrganizationEmployeeAssignment::query()
                    ->whereIn('organization_employee_id', $employeeIds)
                    ->whereNull('deleted_at')
                    ->delete();

                OrganizationEmployee::query()
                    ->whereIn('id', $employeeIds)
                    ->whereNull('deleted_at')
                    ->update([
                        'invite_code' => null,
                        'invite_expires_at' => null,
                        'suspended_at' => now(),
                        'updated_at' => now(),
                    ]);

                OrganizationEmployee::query()
                    ->whereIn('id', $employeeIds)
                    ->whereNull('deleted_at')
                    ->delete();
            }
        });
    }

    /**
     * Suspend a platform account without changing linked company memberships.
     *
     * @param int|string $id
     */
    public function suspend($id): User
    {
        $user = $this->managedUserQuery()
            ->where('id', $id)
            ->firstOrFail();

        if ($user->isSystemOwner()) {
            throw ValidationException::withMessages([
                'user' => __('The primary system owner cannot be suspended.'),
            ]);
        }

        if ((int) ($user->status ?? 1) !== 1) {
            return $user;
        }

        if ($this->isSystemAdminRole($user->role)) {
            $this->ensureAnotherActiveSystemAdminExists($user);
        }

        $user->status = 0;
        $user->remember_token = Str::random(60);
        $user->save();

        return $user->refresh();
    }

    /**
     * Restore a suspended platform account without recreating memberships.
     *
     * @param int|string $id
     */
    public function restore($id): User
    {
        $user = $this->managedUserQuery()
            ->where('id', $id)
            ->firstOrFail();

        if ((int) ($user->status ?? 1) === 1) {
            return $user;
        }

        $user->status = 1;
        $user->save();

        return $user->refresh();
    }

    /**
     * Get the role name from the roles table based on the provided UUID.
     *
     * @param string $uuid
     * @return string
     */
    private function resolveRoleForUpdate(User $user, object $request): string
    {
        if (strtolower((string) $user->role) === 'user') {
            return (string) $user->role;
        }

        if ($user->isSystemOwner()) {
            if ($request->filled('role')) {
                $requestedRole = $this->getRoleNameFromUuid($request->input('role'), (string) $user->role);

                if (strtolower($requestedRole) !== strtolower((string) $user->role)) {
                    throw ValidationException::withMessages([
                        'role' => __('The primary system owner role cannot be changed.'),
                    ]);
                }
            }

            return (string) $user->role;
        }

        $requestedRole = $this->getRoleNameFromUuid($request->input('role'), (string) $user->role);

        if ($this->isSystemAdminRole($user->role) && ! $this->isSystemAdminRole($requestedRole)) {
            $this->ensureAnotherActiveSystemAdminExists($user);
        }

        return $requestedRole;
    }

    private function getRoleNameFromUuid($uuid, ?string $fallback = 'user')
    {
        if (! $uuid) {
            return $fallback ?? 'user';
        }

        $role = Role::where('uuid', $uuid)
            ->whereNull('deleted_at')
            ->value('name');

        if (!$role) {
            return $fallback ?? 'user';
        }

        return $role;
    }

    private function managedUserQuery()
    {
        $query = User::query();

        if ($this->role === 'user') {
            return $query->where('role', 'user');
        }

        return $query->where('role', '!=', 'user');
    }

    private function isSystemAdminRole(?string $role): bool
    {
        return in_array(strtolower((string) $role), self::SYSTEM_ADMIN_ROLES, true);
    }

    private function ensureAnotherActiveSystemAdminExists(User $user): void
    {
        $exists = User::query()
            ->where('id', '!=', $user->id)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->whereIn(DB::raw('LOWER(role)'), self::SYSTEM_ADMIN_ROLES)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'user' => __('At least one active system administrator must remain before this action.'),
            ]);
        }
    }

    private function resolveCreationMode(object $request): string
    {
        $mode = trim((string) ($request->input('creation_mode') ?? ''));

        if ($mode !== '') {
            return $mode;
        }

        if ($request->has('organization_name')) {
            return 'owner_new_org';
        }

        return 'user_only';
    }
}
