<?php

namespace App\Models;

use App\Mail\CustomEmailVerification;
use App\Services\EmailVerificationCodeService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'facebook_id',
        'google_id',
        'password',
        'avatar',
        'role',
        'is_system_owner',
        'phone',
        'address',
        'language',
        'deleted_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_system_owner' => 'boolean',
        'password' => 'hashed',
    ];

    protected $appends = ['full_name'];

    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function isSystemOwner(): bool
    {
        return (bool) $this->is_system_owner;
    }

    public function hasSystemAdminRole(): bool
    {
        return in_array(strtolower((string) $this->role), ['admin', 'owner'], true);
    }

    public function listAll($role, $searchTerm, $organizationId = null)
    {
        $query = $this->where(function ($query) use ($role) {
                if ($role === 'user') {
                    $query->where('users.role', '=', 'user');
                } else {
                    $query->where('users.role', '!=', 'user');
                }
            })
            ->where(function ($query) use ($searchTerm) {
                $query->where('first_name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('last_name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('email', 'like', '%' . $searchTerm . '%')
                    ->orWhere('phone', 'like', '%' . $searchTerm . '%');
            })
            ->latest('users.created_at');

        if ($organizationId !== null) {
            $query->join('teams', 'teams.user_id', '=', 'users.id')
                ->join('organization_roles', 'organization_roles.id', '=', 'teams.organization_role_id')
                ->where('teams.organization_id', '=', $organizationId)
                ->select('users.*', 'organization_roles.name as role');
        }

        return $query->paginate(10);
    }

    public function teams()
    {
        return $this->hasMany(Team::class);
    }

    public function organizationEmployees()
    {
        return $this->hasMany(OrganizationEmployee::class, 'user_id');
    }

    public function teamsWithOrganizations(){
        return $this->teams()->with('organization');
    }

    public function role(){
        return $this->belongsTo(Role::class, 'role', 'name');
    }

    public function sendEmailVerificationNotification(){
        try {
            $code = app(EmailVerificationCodeService::class)->generate($this);

            \Mail::to($this->email)->send(new CustomEmailVerification($this, $code));
        } catch (\Exception $e) {
            \Log::error('Failed to send verification email: ' . $e->getMessage());
        }
    }
}
