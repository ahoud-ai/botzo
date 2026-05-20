<?php

namespace App\Services;

use App\Models\AutoReply;
use App\Models\AutomationFlow;
use App\Models\AutomationFlowRun;
use App\Models\Campaign;
use App\Services\IntelliReply\AiUsageLimiterService;

class OrganizationUsageSummaryService
{
    public function __construct(
        private readonly SubscriptionPlanLimitService $planLimitService,
        private readonly OrganizationHierarchyService $organizationHierarchyService,
        private readonly AiUsageLimiterService $aiUsageLimiterService,
        private readonly SubscriptionFeatureUsageService $subscriptionFeatureUsageService,
        private readonly AddonStateService $addonStateService,
    ) {
    }

    public function build(int $organizationId, array $familyOrganizationIds = []): array
    {
        $window = $this->planLimitService->currentMonthWindow();
        $usageOrganizationIds = $familyOrganizationIds !== []
            ? $familyOrganizationIds
            : $this->organizationHierarchyService->familyOrganizationIds($organizationId);
        $usageOrganizationIds = $usageOrganizationIds !== [] ? $usageOrganizationIds : [$organizationId];
        $workspaceCount = count($usageOrganizationIds);
        $aiSnapshot = $this->aiUsageLimiterService->getSnapshot($organizationId);
        $teamSnapshot = $this->subscriptionFeatureUsageService->snapshot($organizationId, 'team_limit');
        $contactsSnapshot = $this->subscriptionFeatureUsageService->snapshot($organizationId, 'contacts_limit');
        $messagesSnapshot = $this->subscriptionFeatureUsageService->snapshot($organizationId, 'message_limit');
        $aiAssistantEnabled = $this->addonStateService->isModuleEnabledForOrganization('AI Assistant', $organizationId);
        $flowBuilderEnabled = $this->addonStateService->isModuleEnabledForOrganization('Flow builder', $organizationId);
        $notices = [];

        $metrics = [
            $this->makeMetric(
                'branches',
                __('Branches'),
                $workspaceCount,
                $this->planLimitService->limitForOrganization($organizationId, 'branches_limit', -1),
                __('Shared across the parent organization and its branches.')
            ),
            $this->makeMetric(
                'users',
                __('Users'),
                (int) ($teamSnapshot['used'] ?? 0),
                (int) ($teamSnapshot['limit'] ?? -1),
                __('Unique people across the company family. Pending invites reserve a seat.')
            ),
            $this->makeMetric(
                'contacts',
                __('Contacts'),
                (int) ($contactsSnapshot['used'] ?? 0),
                (int) ($contactsSnapshot['limit'] ?? -1),
                __('Active contacts across the company family.')
            ),
            $this->makeMetric(
                'campaigns',
                __('Campaigns'),
                Campaign::query()->whereIn('organization_id', $usageOrganizationIds)->count(),
                $this->planLimitService->limitForOrganization($organizationId, 'campaign_limit', -1),
                __('Campaigns created across all linked workspaces.')
            ),
            $this->makeMetric(
                'messages',
                __('Messages'),
                (int) ($messagesSnapshot['used'] ?? 0),
                (int) ($messagesSnapshot['limit'] ?? -1),
                __('Current subscription-cycle outbound messages across the company family.')
            ),
            $this->makeMetric(
                'canned_replies',
                __('Canned replies'),
                AutoReply::query()->whereIn('organization_id', $usageOrganizationIds)->whereNull('deleted_at')->count(),
                $this->planLimitService->limitForOrganization($organizationId, 'canned_replies_limit', -1),
                __('Saved replies and automation shortcuts across workspaces.')
            ),
        ];

        if ($aiAssistantEnabled && ($aiSnapshot['enabled'] ?? false)) {
            $aiTextMetric = $this->makeMetric(
                'ai_text',
                __('AI text responses'),
                (int) ($aiSnapshot['text_count'] ?? 0),
                (int) ($aiSnapshot['text_limit'] ?? -1),
                __('Current billing-cycle AI text responses that use the global AI key.')
            );
            $aiAudioMetric = $this->makeMetric(
                'ai_audio',
                __('AI audio responses'),
                (int) ($aiSnapshot['audio_count'] ?? 0),
                (int) ($aiSnapshot['audio_limit'] ?? -1),
                __('Current billing-cycle AI audio responses that use the global AI key.')
            );
            $globalAiMetric = $this->makeMetric(
                'ai_system_key',
                __('Global AI monthly quota'),
                (int) ($aiSnapshot['system_key_count'] ?? 0),
                (int) ($aiSnapshot['system_key_monthly_quota'] ?? -1),
                __('Total AI requests billed to the global AI key during the current month.')
            );

            $metrics[] = $aiTextMetric;
            $metrics[] = $aiAudioMetric;
            $metrics[] = $globalAiMetric;
            $notices = array_merge($notices, $this->buildAiNotices([
                $aiTextMetric,
                $aiAudioMetric,
                $globalAiMetric,
            ]));
        }

        if ($flowBuilderEnabled) {
            $metrics[] = $this->makeMetric(
                'active_flows',
                __('Active flows'),
                AutomationFlow::query()
                    ->whereIn('organization_id', $usageOrganizationIds)
                    ->where('status', 'published')
                    ->whereNull('deleted_at')
                    ->count(),
                $this->planLimitService->limitForOrganization($organizationId, 'flow_builder_active_flows_limit', -1),
                __('Published flows across the company family.')
            );
            $metrics[] = $this->makeMetric(
                'flow_runs',
                __('Flow runs'),
                AutomationFlowRun::query()
                    ->whereIn('organization_id', $usageOrganizationIds)
                    ->whereBetween('created_at', [$window['start'], $window['end']])
                    ->count(),
                $this->planLimitService->limitForOrganization($organizationId, 'flow_builder_monthly_runs_limit', -1),
                __('Current month Flow Builder runs.')
            );
        }

        return [
            'period_label' => __('Current month'),
            'metrics' => $metrics,
            'notices' => $notices,
        ];
    }

    private function makeMetric(string $key, string $label, int $used, int $limit, string $helper): array
    {
        $remaining = $limit < 0 ? null : max($limit - $used, 0);
        $percentage = $limit < 1 ? null : min(100, (int) round(($used / $limit) * 100));

        return [
            'key' => $key,
            'label' => $label,
            'used' => $used,
            'limit' => $limit,
            'remaining' => $remaining,
            'percentage' => $percentage,
            'status' => $this->resolveStatus($used, $limit),
            'helper' => $helper,
        ];
    }

    private function resolveStatus(int $used, int $limit): string
    {
        if ($limit < 0) {
            return 'unlimited';
        }

        if ($used >= $limit) {
            return 'exceeded';
        }

        if ($limit > 0 && (($used / $limit) >= 0.8)) {
            return 'warning';
        }

        return 'healthy';
    }

    /**
     * @param  array<int, array<string, mixed>>  $metrics
     * @return array<int, array{key:string,type:string,title:string,message:string}>
     */
    private function buildAiNotices(array $metrics): array
    {
        $notices = [
            $this->makeNotice(
                'ai_usage_scope',
                'info',
                __('Tracked AI usage'),
                __('These AI counters track only requests billed to the global AI key. Requests sent with an organization key do not consume these limits.')
            ),
        ];

        foreach ($metrics as $metric) {
            $status = (string) ($metric['status'] ?? 'healthy');
            if (!in_array($status, ['warning', 'exceeded'], true)) {
                continue;
            }

            $metricKey = (string) ($metric['key'] ?? '');
            if ($metricKey === 'ai_text') {
                $notices[] = $status === 'warning'
                    ? $this->makeNotice(
                        'ai_text_warning',
                        'warning',
                        __('AI text responses nearing limit'),
                        __('Automatic AI text replies that use the global AI key are close to the plan limit for this billing cycle.')
                    )
                    : $this->makeNotice(
                        'ai_text_exceeded',
                        'danger',
                        __('AI text response limit reached'),
                        __('Automatic AI text replies that use the global AI key may pause until the billing cycle resets or the organization switches to its own key.')
                    );

                continue;
            }

            if ($metricKey === 'ai_audio') {
                $notices[] = $status === 'warning'
                    ? $this->makeNotice(
                        'ai_audio_warning',
                        'warning',
                        __('AI audio responses nearing limit'),
                        __('Automatic AI audio replies that use the global AI key are close to the plan limit for this billing cycle.')
                    )
                    : $this->makeNotice(
                        'ai_audio_exceeded',
                        'danger',
                        __('AI audio response limit reached'),
                        __('Automatic AI audio replies that use the global AI key may pause until the billing cycle resets or the organization switches to its own key.')
                    );

                continue;
            }

            if ($metricKey === 'ai_system_key') {
                $notices[] = $status === 'warning'
                    ? $this->makeNotice(
                        'ai_global_quota_warning',
                        'warning',
                        __('Global AI quota nearing limit'),
                        __('The shared global AI key quota is close to its monthly limit. Automatic AI replies that depend on the global key may pause soon.')
                    )
                    : $this->makeNotice(
                        'ai_global_quota_exceeded',
                        'danger',
                        __('Global AI quota reached'),
                        __('The shared global AI key quota has been exhausted. Automatic AI replies that depend on the global key may pause until the monthly window resets or the organization switches to its own key.')
                    );
            }
        }

        return $notices;
    }

    /**
     * @return array{key:string,type:string,title:string,message:string}
     */
    private function makeNotice(string $key, string $type, string $title, string $message): array
    {
        return [
            'key' => $key,
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ];
    }
}
