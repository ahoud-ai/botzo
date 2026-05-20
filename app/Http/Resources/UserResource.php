<?php

namespace App\Http\Resources;

use App\Helpers\DateTimeHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $data['updated_at'] = DateTimeHelper::formatDate($this->updated_at);
        $data['created_at'] = DateTimeHelper::formatDate($this->created_at);
        $data['is_system_owner'] = (bool) ($this->is_system_owner ?? false);
        $data['system_owner_label'] = $data['is_system_owner'] ? __('System Owner') : null;
        $data['can_delete_account'] = ! $data['is_system_owner'];
        $data['can_update_role'] = ! $data['is_system_owner'];

        return $data;
    }
}
