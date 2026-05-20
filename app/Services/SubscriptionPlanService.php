<?php

namespace App\Services;

use App\Models\Addon;
use App\Http\Resources\SubscriptionPlanResource;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Support\SaClientPlanProfile;
use Illuminate\Validation\ValidationException;

class SubscriptionPlanService
{
    /**
     * Get all subscription plans based on the provided request filters.
     *
     * @param Request $request
     * @return mixed
     */
    public function get(object $request)
    {
        $subscriptionPlans = (new SubscriptionPlan)->listAll($request->query('search'));

        return SubscriptionPlanResource::collection($subscriptionPlans);
    }

    /**
     * Retrieve a subscription plan by its UUID.
     *
     * @param string $uuid
     * @return \App\Models\SubscriptionPlan
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getByUuid($uuid = null)
    {
        return SubscriptionPlan::where('uuid', $uuid)->first();
    }

    /**
     * Store a new subscription plan based on the provided request data.
     *
     * @param Request $request
     */
    public function store(Object $request)
    {
        $nameAr = $this->normalizeText($request->input('name_ar'));
        $nameEn = $this->normalizeText($request->input('name_en'));
        $previousName = $this->normalizeText($request->input('name'));
        $resolvedName = $this->resolvePlanName($nameAr, $nameEn, $previousName);

        $newSubscriptionPlan = SubscriptionPlan::create([
            'name' => $resolvedName,
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'price' => $request->input('price'),
            'period' => $request->input('period'),
            'status' => $request->input('status'),
            'metadata' => json_encode($this->buildPlanMetadata($request)),
        ]);

        return $newSubscriptionPlan;
    }

    /**
     * Update an existing subscription plan based on the provided request data.
     *
     * @param Request $request
     */
    public function update(Object $request, $uuid)
    {
        $plan = SubscriptionPlan::where('uuid', $uuid)->firstOrFail();
        $nameAr = $this->normalizeText($request->input('name_ar'));
        $nameEn = $this->normalizeText($request->input('name_en'));
        $previousName = $this->normalizeText($request->input('name'));
        $resolvedName = $this->resolvePlanName($nameAr, $nameEn, $previousName, $plan->name);
        $metadata = json_decode($plan->metadata, true);
        if (!is_array($metadata)) {
            $metadata = [];
        }
        $metadata = SaClientPlanProfile::sanitizePlanMetadata(array_merge($metadata, $this->buildPlanMetadata($request)));

        $plan->name = $resolvedName;
        $plan->name_ar = $nameAr;
        $plan->name_en = $nameEn;
        $plan->price = $request->input('price');
        $plan->metadata = json_encode($metadata);
        $plan->period = $request->input('period');
        $plan->status = $request->input('status');

        $plan->save();

        return $plan;
    }

    /**
     * Check if a subscription plan has subscribers (any status)
     *
     * @param string $uuid
     * @return array
     */
    public function checkSubscribers($uuid)
    {
        $subscriptionPlan = SubscriptionPlan::where('uuid', $uuid)->firstOrFail();
        $allSubscriptions = Subscription::query()
            ->with('organization')
            ->where(function ($query) use ($subscriptionPlan) {
                $query->where('plan_id', $subscriptionPlan->id)
                    ->orWhere('scheduled_plan_id', $subscriptionPlan->id);
            })
            ->get()
            ->unique('id')
            ->values();
            
        return [
            'has_subscribers' => $allSubscriptions->count() > 0,
            'subscriber_count' => $allSubscriptions->count(),
            'subscribers' => $allSubscriptions,
            'plan' => $subscriptionPlan
        ];
    }

    /**
     * Transfer subscribers from one plan to another
     *
     * @param string $fromPlanUuid
     * @param string $toPlanUuid
     * @return bool
     */
    public function transferSubscribers($fromPlanUuid, $toPlanUuid)
    {
        $fromPlan = SubscriptionPlan::where('uuid', $fromPlanUuid)->firstOrFail();
        $toPlan = SubscriptionPlan::where('uuid', $toPlanUuid)->firstOrFail();

        Subscription::query()
            ->where('plan_id', $fromPlan->id)
            ->update(['plan_id' => $toPlan->id]);

        Subscription::query()
            ->where('scheduled_plan_id', $fromPlan->id)
            ->update(['scheduled_plan_id' => $toPlan->id]);
        
        return true;
    }

    /**
     * Destroy (delete) an existing subscription plan based on the provided request data.
     *
     * @param Request $request
     */
    public function destroy($uuid)
    {
        $subscriptionPlan = SubscriptionPlan::where('uuid', $uuid)->firstOrFail();
        $subscriberCheck = $this->checkSubscribers($uuid);

        if ($subscriberCheck['has_subscribers']) {
            throw ValidationException::withMessages([
                'plan' => __('This plan cannot be deleted while it is assigned to active or scheduled subscriptions. Transfer those subscriptions first.'),
            ]);
        }

        $subscriptionPlan->update(['deleted_at' => now()]);

    }

    private function normalizeText($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        return strip_tags($normalized);
    }

    private function resolvePlanName(?string $nameAr, ?string $nameEn, ?string $previousName, ?string $fallback = null): string
    {
        $resolved = $this->firstFilled($nameEn, $nameAr, $previousName, $fallback);

        return $resolved ?? '';
    }

    private function normalizeCustomFeatures($features): array
    {
        if (!is_array($features)) {
            return [];
        }

        return collect($features)
            ->map(function ($feature) {
                $textAr = $this->normalizeText(data_get($feature, 'text_ar'));
                $textEn = $this->normalizeText(data_get($feature, 'text_en'));

                if ($textAr === null && $textEn === null) {
                    return null;
                }

                return [
                    'text_ar' => $textAr,
                    'text_en' => $textEn,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeAddons($addons): array
    {
        if (!is_array($addons)) {
            return [];
        }

        $allowedAddons = $this->planRestrictedAddonNames();

        return collect($addons)
            ->mapWithKeys(function ($value, $key) {
                $normalizedKey = trim((string) $key);

                if ($normalizedKey === '') {
                    return [];
                }

                return [$normalizedKey => SaClientPlanProfile::normalizeBoolean($value)];
            })
            ->filter(fn ($value, string $key) => in_array($key, $allowedAddons, true))
            ->all();
    }

    private function planRestrictedAddonNames(): array
    {
        return Addon::query()
            ->where('status', 1)
            ->where('is_plan_restricted', 1)
            ->whereIn('name', SaClientPlanProfile::planAddonNames())
            ->pluck('name')
            ->all();
    }

    private function firstFilled(...$values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return $value;
            }
        }

        return null;
    }

    private function buildPlanMetadata(object $request): array
    {
        return SaClientPlanProfile::sanitizePlanMetadata([
            'tier_rank' => $this->normalizeTierRank($request->input('tier_rank')),
            'campaign_limit' => $this->normalizeLimit($request->input('campaign_limit')),
            'message_limit' => $this->normalizeLimit($request->input('message_limit')),
            'contacts_limit' => $this->normalizeLimit($request->input('contacts_limit')),
            'canned_replies_limit' => $this->normalizeLimit($request->input('canned_replies_limit')),
            'team_limit' => $this->normalizeLimit($request->input('team_limit')),
            'receive_messages_after_expiration' => $request->boolean('receive_messages_after_expiration') ? 1 : 0,
            'ai_text_response_limit' => $this->normalizeLimit($request->input('ai_text_response_limit')),
            'ai_audio_response_limit' => $this->normalizeLimit($request->input('ai_audio_response_limit')),
            'ai_organization_key_enabled' => $request->boolean('ai_organization_key_enabled') ? 1 : 0,
            'branches_limit' => $this->normalizeLimit($request->input('branches_limit')),
            'ai_system_key_monthly_quota' => $this->normalizeLimit($request->input('ai_system_key_monthly_quota')),
            'flow_builder_active_flows_limit' => $this->normalizeLimit($request->input('flow_builder_active_flows_limit')),
            'flow_builder_nodes_per_flow_limit' => $this->normalizeLimit($request->input('flow_builder_nodes_per_flow_limit')),
            'flow_builder_monthly_runs_limit' => $this->normalizeLimit($request->input('flow_builder_monthly_runs_limit')),
            'flow_builder_advanced_enabled' => $request->boolean('flow_builder_advanced_enabled') ? 1 : 0,
            'addons' => $this->normalizeAddons($request->input('addons', [])),
            'custom_features' => $this->normalizeCustomFeatures($request->input('custom_features', [])),
        ]);
    }

    private function normalizeLimit($value): int
    {
        if ($value === null || $value === '') {
            return -1;
        }

        $normalized = (int) $value;

        return $normalized < -1 ? -1 : $normalized;
    }

    private function normalizeTierRank($value): int
    {
        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : 1;
    }

}
