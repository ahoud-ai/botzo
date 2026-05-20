<?php

namespace App\Http\Resources;

use App\Helpers\DateTimeHelper;
use App\Services\OrganizationHierarchyService;
use App\Services\SubscriptionPlanLimitService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $billingOwner = app(OrganizationHierarchyService::class)->billingOwner($this->id);
        $planLimitService = app(SubscriptionPlanLimitService::class);
        $effectiveSubscription = $planLimitService->subscriptionForOrganization($this->id);
        $billingDisplayState = SubscriptionService::billingDisplayState($this->id, $effectiveSubscription);

        $data['updated_at'] = DateTimeHelper::formatDate($this->updated_at);
        $data['created_at'] = DateTimeHelper::formatDate($this->created_at);
        $data['billing_owner'] = $billingOwner ? [
            'id' => $billingOwner->id,
            'uuid' => $billingOwner->uuid,
            'name' => $billingOwner->name,
        ] : null;
        $data['branch_summary'] = [
            'count' => (int) ($this->branches_count ?? 0),
            'preview' => $this->whenLoaded('branches', function () {
                return $this->branches
                    ->take(3)
                    ->map(fn ($branch) => [
                        'uuid' => $branch->uuid,
                        'name' => $branch->name,
                        'updated_at' => DateTimeHelper::formatDate($branch->updated_at),
                    ])
                    ->values()
                    ->all();
            }, []),
        ];
        $data['subscription_display'] = [
            'plan_name' => $effectiveSubscription?->plan?->name
                ?? ($billingDisplayState['variant'] === 'billing_pending' ? __('Not selected yet') : null),
            'plan_uuid' => $effectiveSubscription?->plan?->uuid,
            'valid_until' => $effectiveSubscription?->getRawOriginal('valid_until')
                ? DateTimeHelper::formatDate($effectiveSubscription->getRawOriginal('valid_until'))
                : null,
            'status' => $billingDisplayState['variant'],
            'status_label' => $billingDisplayState['label'],
            'source' => $this->organization_type === 'branch' ? 'parent' : 'direct',
            'managed_by_parent' => $this->organization_type === 'branch' && $billingOwner && $billingOwner->id !== $this->id,
            'managed_by' => $billingOwner?->name,
        ];

        return $data;
    }
}
