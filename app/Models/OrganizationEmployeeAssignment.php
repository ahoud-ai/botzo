<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrganizationEmployeeAssignment extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'assigned_at' => 'datetime',
        'activated_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(OrganizationEmployee::class, 'organization_employee_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function organizationRole()
    {
        return $this->belongsTo(OrganizationRole::class, 'organization_role_id');
    }

    public function assignedByUser()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
