<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationApiKey extends Model {
    use HasFactory;
    use HasUuid;

    protected $guarded = [];
    public $timestamps = true;
    protected $hidden = [
        'id',
        'token',
        'organization_id',
        'deleted_by',
    ];
    protected $casts = [
        'last_used_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
