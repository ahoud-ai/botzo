<?php

namespace App\Http\Resources;

use App\Helpers\DateTimeHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeveloperResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'token_last_four' => $this->token_last_four,
            'masked_token' => '************************************' . ($this->token_last_four ?? '----'),
            'last_used_at' => $this->last_used_at ? DateTimeHelper::formatDate((string) $this->last_used_at) : null,
            'updated_at' => DateTimeHelper::formatDate($this->updated_at),
            'created_at' => DateTimeHelper::formatDate($this->created_at),
        ];
    }
}
