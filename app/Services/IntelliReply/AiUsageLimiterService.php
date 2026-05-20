<?php

namespace App\Services\IntelliReply;

use App\Models\OrganizationAiUsageCounter;
use App\Models\Setting;
use App\Models\Subscription;
use App\Services\OrganizationHierarchyService;
use App\Services\SubscriptionPlanLimitService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AiUsageLimiterService
{
    public function __construct(
        private readonly SubscriptionPlanLimitService $planLimitService,
        private readonly OrganizationHierarchyService $organizationHierarchyService,
    ) {
    }

    public function canUseText(int $organizationId, ?string $keySource = null): bool
    {
        return $this->canUse($organizationId, 'text', $keySource);
    }

    public function canUseAudio(int $organizationId, ?string $keySource = null): bool
    {
        return $this->canUse($organizationId, 'audio', $keySource);
    }

    public function consumeText(int $organizationId, ?string $keySource = null): bool
    {
        return $this->consume($organizationId, 'text', $keySource);
    }

    public function consumeAudio(int $organizationId, ?string $keySource = null): bool
    {
        return $this->consume($organizationId, 'audio', $keySource);
    }

    /**
     * @return array{enabled:bool,text_limit:int,audio_limit:int,text_count:int,audio_count:int,period_start:?string,period_end:?string,system_key_monthly_quota:int,system_key_count:int,system_key_period_start:?string,system_key_period_end:?string}
     */
    public function getSnapshot(int $organizationId): array
    {
        $usageOwnerId = $this->organizationHierarchyService->billingOwnerId($organizationId) ?? $organizationId;
        [$enforced, $subscription, $limits] = $this->resolveRuntimeContext($organizationId);
        if (!$enforced || !$subscription) {
            return [
                'enabled' => false,
                'text_limit' => -1,
                'audio_limit' => -1,
                'text_count' => 0,
                'audio_count' => 0,
                'period_start' => null,
                'period_end' => null,
                'system_key_monthly_quota' => -1,
                'system_key_count' => 0,
                'system_key_period_start' => null,
                'system_key_period_end' => null,
            ];
        }

        $counter = $this->resolveCounter($usageOwnerId, $subscription);
        $systemCounter = $this->resolveSystemKeyCounter($usageOwnerId);
        $systemKeyQuota = $this->resolveSystemKeyMonthlyQuota($usageOwnerId);
        $systemKeyCount = (int) $systemCounter->text_count + (int) $systemCounter->audio_count;

        return [
            'enabled' => true,
            'text_limit' => $limits['text'],
            'audio_limit' => $limits['audio'],
            'text_count' => (int) $counter->text_count,
            'audio_count' => (int) $counter->audio_count,
            'period_start' => $counter->period_start,
            'period_end' => $counter->period_end,
            'system_key_monthly_quota' => $systemKeyQuota,
            'system_key_count' => $systemKeyCount,
            'system_key_period_start' => $systemCounter->period_start,
            'system_key_period_end' => $systemCounter->period_end,
        ];
    }

    private function canUse(int $organizationId, string $channel, ?string $keySource = null): bool
    {
        $usageOwnerId = $this->organizationHierarchyService->billingOwnerId($organizationId) ?? $organizationId;
        [$enforced, $subscription, $limits] = $this->resolveRuntimeContext($organizationId);
        if (!$enforced) {
            return true;
        }

        if (!$subscription) {
            return false;
        }

        if (!$this->shouldEnforcePlanUsageLimits($keySource)) {
            return true;
        }

        $limit = $channel === 'audio' ? $limits['audio'] : $limits['text'];
        if ($limit !== -1) {
            $counter = $this->resolveCounter($usageOwnerId, $subscription);
            $count = $channel === 'audio' ? (int) $counter->audio_count : (int) $counter->text_count;

            if ($count >= $limit) {
                return false;
            }
        }

        if (!$this->shouldEnforceSystemKeyQuota($keySource)) {
            return true;
        }

        $systemLimit = $this->resolveSystemKeyMonthlyQuota($usageOwnerId);
        if ($systemLimit === -1) {
            return true;
        }

        $systemCounter = $this->resolveSystemKeyCounter($usageOwnerId);
        $systemCount = (int) $systemCounter->text_count + (int) $systemCounter->audio_count;

        return $systemCount < $systemLimit;
    }

    private function consume(int $organizationId, string $channel, ?string $keySource = null): bool
    {
        $usageOwnerId = $this->organizationHierarchyService->billingOwnerId($organizationId) ?? $organizationId;
        [$enforced, $subscription, $limits] = $this->resolveRuntimeContext($organizationId);
        if (!$enforced) {
            return true;
        }

        if (!$subscription) {
            return false;
        }

        if (!$this->shouldEnforcePlanUsageLimits($keySource)) {
            return true;
        }

        $limit = $channel === 'audio' ? $limits['audio'] : $limits['text'];
        $enforceSystemKeyQuota = $this->shouldEnforceSystemKeyQuota($keySource);
        $systemLimit = $enforceSystemKeyQuota
            ? $this->resolveSystemKeyMonthlyQuota($usageOwnerId)
            : -1;

        if ($limit === -1 && $systemLimit === -1) {
            return true;
        }

        return DB::transaction(function () use ($usageOwnerId, $subscription, $channel, $limit, $systemLimit): bool {
            $countColumn = $channel === 'audio' ? 'audio_count' : 'text_count';
            $counter = null;
            $systemCounter = null;

            if ($limit !== -1) {
                $counter = $this->resolveCounter($usageOwnerId, $subscription, true);
                $current = (int) $counter->{$countColumn};
                if ($current >= $limit) {
                    return false;
                }
            }

            if ($systemLimit !== -1) {
                $systemCounter = $this->resolveSystemKeyCounter($usageOwnerId, true);
                $systemCurrent = (int) $systemCounter->text_count + (int) $systemCounter->audio_count;
                if ($systemCurrent >= $systemLimit) {
                    return false;
                }
            }

            if ($counter) {
                $counter->increment($countColumn);
            }

            if ($systemCounter) {
                $systemCounter->increment($countColumn);
            }

            return true;
        });
    }

    private function resolveCounter(int $organizationId, Subscription $subscription, bool $lockForUpdate = false): OrganizationAiUsageCounter
    {
        $query = OrganizationAiUsageCounter::query()
            ->where('organization_id', $organizationId)
            ->where('subscription_id', $subscription->id)
            ->where('period_start', $this->periodStart($subscription))
            ->where('period_end', $this->periodEnd($subscription));

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $counter = $query->first();
        if ($counter) {
            return $counter;
        }

        return OrganizationAiUsageCounter::create([
            'organization_id' => $organizationId,
            'subscription_id' => $subscription->id,
            'period_start' => $this->periodStart($subscription),
            'period_end' => $this->periodEnd($subscription),
            'text_count' => 0,
            'audio_count' => 0,
        ]);
    }

    private function resolveSystemKeyCounter(int $organizationId, bool $lockForUpdate = false): OrganizationAiUsageCounter
    {
        $window = $this->planLimitService->currentMonthWindow();
        $periodStart = $window['start']->toDateTimeString();
        $periodEnd = $window['end']->toDateTimeString();

        $query = OrganizationAiUsageCounter::query()
            ->where('organization_id', $organizationId)
            ->whereNull('subscription_id')
            ->where('period_start', $periodStart)
            ->where('period_end', $periodEnd);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $counter = $query->first();
        if ($counter) {
            return $counter;
        }

        return OrganizationAiUsageCounter::create([
            'organization_id' => $organizationId,
            'subscription_id' => null,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'text_count' => 0,
            'audio_count' => 0,
        ]);
    }

    private function resolveSystemKeyMonthlyQuota(int $organizationId): int
    {
        return $this->planLimitService->limitForOrganization(
            $organizationId,
            'ai_system_key_monthly_quota',
            -1
        );
    }

    /**
     * @return array{0:bool,1:?Subscription,2:array{text:int,audio:int}}
     */
    private function resolveRuntimeContext(int $organizationId): array
    {
        $billingOwnerId = $this->organizationHierarchyService->billingOwnerId($organizationId) ?? $organizationId;
        $enforced = (string) (Setting::where('key', 'enable_ai_billing')->value('value') ?? '0') === '1';
        $subscription = Subscription::with('plan')
            ->where('organization_id', $billingOwnerId)
            ->where(function ($query) {
                $query->where('status', 'active')
                    ->orWhere(function ($trialQuery) {
                        $trialQuery->where('status', 'trial')
                            ->where('valid_until', '>', now());
                    });
            })
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 WHEN status = 'trial' THEN 1 ELSE 2 END")
            ->orderByDesc('valid_until')
            ->first();

        // Backward-compatible fallback for previous records where status may not match current semantics.
        if (!$subscription) {
            $subscription = Subscription::with('plan')
                ->where('organization_id', $billingOwnerId)
                ->orderByDesc('valid_until')
                ->first();
        }

        if (!$subscription || !$subscription->plan) {
            return [$enforced, null, ['text' => -1, 'audio' => -1]];
        }

        $metadata = $subscription->plan->metadata;
        if (is_string($metadata) && trim($metadata) !== '') {
            $metadata = json_decode($metadata, true);
        }
        $metadata = is_array($metadata) ? $metadata : [];

        $textLimit = $this->normalizeLimit($metadata['ai_text_response_limit'] ?? -1);
        $audioLimit = $this->normalizeLimit($metadata['ai_audio_response_limit'] ?? -1);

        return [$enforced, $subscription, ['text' => $textLimit, 'audio' => $audioLimit]];
    }

    private function shouldEnforceSystemKeyQuota(?string $keySource = null): bool
    {
        return is_string($keySource) && strtolower(trim($keySource)) === 'global';
    }

    private function shouldEnforcePlanUsageLimits(?string $keySource = null): bool
    {
        if (!is_string($keySource) || trim($keySource) === '') {
            return true;
        }

        return strtolower(trim($keySource)) !== 'organization';
    }

    private function normalizeLimit($value): int
    {
        if ($value === null || $value === '') {
            return -1;
        }

        $limit = (int) $value;
        return $limit < -1 ? -1 : $limit;
    }

    private function periodStart(Subscription $subscription): string
    {
        return Carbon::parse($subscription->start_date)->toDateTimeString();
    }

    private function periodEnd(Subscription $subscription): string
    {
        return Carbon::parse($subscription->valid_until)->toDateTimeString();
    }
}
