<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrganizationEmployee extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'invited_at' => 'datetime',
        'invite_expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    public function mainOrganization()
    {
        return $this->belongsTo(Organization::class, 'main_organization_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function invitedByUser()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function assignments()
    {
        return $this->hasMany(OrganizationEmployeeAssignment::class, 'organization_employee_id')
            ->whereNull('deleted_at');
    }

    public function fullName(): ?string
    {
        $userName = trim((string) ($this->user?->full_name ?? ''));
        if ($userName !== '') {
            return $userName;
        }

        $snapshotName = trim(implode(' ', array_filter([
            $this->first_name,
            $this->last_name,
        ])));

        return $snapshotName !== '' ? $snapshotName : null;
    }
}
