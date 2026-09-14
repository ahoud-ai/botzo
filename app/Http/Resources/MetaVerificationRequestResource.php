<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MetaVerificationRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'business_name' => $this->business_name,
            'legal_company_name' => $this->legal_company_name,
            'phone' => $this->phone,
            'whatsapp_number' => $this->whatsapp_number,
            'email' => $this->email,
            'website_url' => $this->website_url,
            'documents_count' => $this->whenCounted('documents', fn () => $this->documents_count, 0),
            'status' => $this->status,
            'organization' => $this->whenLoaded('organization', fn () => [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
