<?php

namespace App\Http\Resources;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Use pre-computed counts if available (from optimized controller query)
        $computedCounts = $this->computed_counts ?? null;

        if ($computedCounts) {
            // Use pre-computed counts to avoid N+1 queries
            // All counts are pre-calculated in the controller with proper indexes
            $contactsCount = $computedCounts['contacts_count'] ?? 0;
            $deliveryCount = $computedCounts['delivery_count'] ?? 0;
            $readCount = $computedCounts['read_count'] ?? 0;
            $contactGroupCount = $computedCounts['contact_group_count'] ?? 0;
        } else {
            // Fallback to model methods if computed counts not available
            $contactsCount = $this->contactsCount();
            $deliveryCount = $this->deliveryCount();
            $readCount = $this->readCount();
            
            if ($this->contact_group_id == 0 || $this->contact_group_id === '0') {
                // With proper indexes, this query is fast without caching
                $contactGroupCount = Contact::where('organization_id', $this->organization_id)
                    ->whereNull('deleted_at')
                    ->count();
            } else {
                $contactGroupCount = $this->contactGroupCount();
            }
        }

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'template' => $this->template,
            'status' => $this->status,
            'contacts_count' => $contactsCount,
            'delivery_count' => $deliveryCount,
            'read_count' => $readCount,
            'contact_group_count' => $contactGroupCount,
            // Add other attributes as needed...
        ];
    }
}
