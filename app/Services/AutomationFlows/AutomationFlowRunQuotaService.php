<?php

namespace App\Services\AutomationFlows;

use App\Models\AutomationFlowRun;
use App\Services\OrganizationHierarchyService;
use App\Services\SubscriptionPlanLimitService;
use Illuminate\Support\Facades\Log;

class AutomationFlowRunQuotaService
{
    public function __construct(
        private readonly SubscriptionPlanLimitService $planLimitService,
        private readonly OrganizationHierarchyService $organizationHierarchyService,
    ) {
    }

    public function canStartRunForOrganization(int $organizationId): bool
    {
        $familyOrganizationIds = $this->organizationHierarchyService->familyOrganizationIds($organizationId);
        $monthlyLimit = $this->planLimitService->limitForOrganization(
            $organizationId,
            'flow_builder_monthly_runs_limit',
            -1
        );

        if ($monthlyLimit < 0) {
            return true;
        }

        $window = $this->planLimitService->currentMonthWindow();
        $runsCount = AutomationFlowRun::query()
            ->whereIn('organization_id', $familyOrganizationIds !== [] ? $familyOrganizationIds : [$organizationId])
            ->whereBetween('created_at', [$window['start'], $window['end']])
            ->count();

        if ($runsCount < $monthlyLimit) {
            return true;
        }

        Log::warning('automation_flows.limit.reached', [
            'organization_id' => $organizationId,
            'limit_key' => 'flow_builder_monthly_runs_limit',
            'limit' => $monthlyLimit,
        ]);

        return false;
    }
}
