<?php

namespace App\Http\Resources;

use App\Helpers\DateTimeHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        // Convert updated_at to the organization's timezone and format it
        $updatedAt = DateTimeHelper::convertToOrganizationTimezone($this->updated_at);
        $data['updated_at'] = DateTimeHelper::formatDate($updatedAt);

        // Include organization role information
        if ($this->organizationRole) {
            $data['role_name'] = $this->organizationRole->name;
            $data['organization_role_id'] = $this->organizationRole->id;
            $data['role_uuid'] = $this->organizationRole->uuid;
        }

        return $data;
    }
}
