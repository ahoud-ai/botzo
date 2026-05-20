<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model {
    use HasFactory;
    use HasUuid;

    protected $guarded = [];
    public $timestamps = true;

    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(User::class, 'role', 'name')
            ->whereNull('users.deleted_at');
    }

    public function listAll($searchTerm)
    {
        return $this->where('deleted_at', null)
                    ->where(function ($query) use ($searchTerm) {
                        $query->where('name', 'like', '%' . $searchTerm . '%');
                    })
                    ->latest()
                    ->paginate(10);
    }
}
