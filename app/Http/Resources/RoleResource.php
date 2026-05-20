<?php

namespace App\Http\Resources;

use App\Helpers\DateTimeHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    private const SYSTEM_ROLE_NAME = 'admin';

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $data['updated_at'] = DateTimeHelper::formatDate($this->updated_at);

        $membersCount = (int) ($this->members_count ?? 0);
        $permissionsCount = (int) ($this->permissions_count ?? 0);
        $isSystemRole = strtolower((string) ($this->name ?? '')) === self::SYSTEM_ROLE_NAME;

        $data['members_count'] = $membersCount;
        $data['permissions_count'] = $permissionsCount;
        $data['is_system_role'] = $isSystemRole;
        $data['can_edit'] = ! $isSystemRole;
        $data['can_delete'] = ! $isSystemRole && $membersCount === 0;

        return $data;
    }
}
