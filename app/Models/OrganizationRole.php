<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use App\Support\OrganizationPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrganizationRole extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'organization_id',
        'name',
        'description',
        'permissions'
    ];

    protected $casts = [
        'permissions' => 'array'
    ];

    /**
     * Check if this is the universal Owner role
     */
    public function isOwnerRole(): bool
    {
        return is_null($this->organization_id) && $this->name === 'Owner';
    }

    /**
     * Check if role has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->getPermissionsArray();
        
        // Owner role or wildcard permission means all permissions
        if ($this->isOwnerRole() || in_array('*', $permissions)) {
            return true;
        }
        
        return in_array($permission, $permissions);
    }

    /**
     * Get all permissions as a flat array
     */
    public function getPermissionsArray(): array
    {
        return OrganizationPermissions::normalizePermissions($this->permissions ?? []);
    }

    public function getPermissionsAttribute($value): array
    {
        $decoded = is_array($value) ? $value : (json_decode((string) $value, true) ?: []);

        return OrganizationPermissions::normalizePermissions($decoded);
    }

    public function setPermissionsAttribute($value): void
    {
        $this->attributes['permissions'] = json_encode(
            OrganizationPermissions::normalizePermissions(is_array($value) ? $value : [])
        );
    }

    /**
     * Relationship to Organization
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * Relationship to Teams
     */
    public function teams()
    {
        return $this->hasMany(Team::class, 'organization_role_id');
    }
}
