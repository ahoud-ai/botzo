<?php

namespace App\Services\Salla;

use App\Exceptions\SallaPlansMissing;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Salla's plans are rows in subscription_plans, inactive so they stay off the public pricing page,
 * and marked with `salla_plan_key` in their metadata. Limits are edited on those rows.
 */
class SallaPlans
{
    public const TRIAL = 'trial';

    /** A paying merchant whose plan name is not recognized must still get service. */
    public const FALLBACK = 'basic';

    /** The starting values. Existing rows are never overwritten. */
    public const DEFINITIONS = [
        self::TRIAL => ['rank' => 1, 'en' => 'Salla Trial', 'ar' => 'تجربة سلة', 'replies' => 100, 'names' => ['trial', 'trail', 'free trial', 'تجربة', 'تجربة مجانية']],
        self::FALLBACK => ['rank' => 2, 'en' => 'Salla Basic', 'ar' => 'سلة الأساسية', 'replies' => 1000, 'names' => ['basic', 'starter', 'أساسية', 'الأساسية', 'الباقة الأساسية']],
        'growth' => ['rank' => 3, 'en' => 'Salla Growth', 'ar' => 'سلة النمو', 'replies' => 3000, 'names' => ['growth', 'نمو', 'النمو', 'باقة النمو']],
        'pro' => ['rank' => 4, 'en' => 'Salla Pro', 'ar' => 'سلة الاحترافية', 'replies' => 10000, 'names' => ['pro', 'professional', 'احترافية', 'الاحترافية', 'الباقة الاحترافية']],
    ];

    public function setup(): int
    {
        $existing = $this->all()->map(fn (SubscriptionPlan $plan) => $plan->metadataValue('salla_plan_key'))->all();
        $created = 0;

        foreach (self::DEFINITIONS as $key => $definition) {
            if (in_array($key, $existing, true)) {
                continue;
            }

            SubscriptionPlan::create([
                'name' => $definition['en'],
                'name_ar' => $definition['ar'],
                'name_en' => $definition['en'],
                'price' => 0,
                'period' => 'monthly',
                'status' => 'inactive',
                'metadata' => json_encode($this->metadata($key, $definition), JSON_UNESCAPED_UNICODE),
            ]);
            $created++;
        }

        return $created;
    }

    public function trial(): SubscriptionPlan
    {
        return $this->byKey(self::TRIAL);
    }

    public function forName(?string $name, int $merchantId): SubscriptionPlan
    {
        $wanted = Str::lower(trim((string) $name));

        $match = $this->all()->first(fn (SubscriptionPlan $plan) => $wanted !== ''
            && in_array($wanted, array_map(fn ($value) => Str::lower((string) $value), (array) $plan->metadataValue('salla_plan_names', [])), true));

        if ($match) {
            return $match;
        }

        Log::warning('salla.plan.unknown_name', ['merchant_id' => $merchantId, 'plan_name' => Str::limit((string) $name, 64, '')]);

        return $this->byKey(self::FALLBACK);
    }

    private function byKey(string $key): SubscriptionPlan
    {
        return $this->all()->first(fn (SubscriptionPlan $plan) => $plan->metadataValue('salla_plan_key') === $key)
            ?? throw new SallaPlansMissing();
    }

    /** @return Collection<int, SubscriptionPlan> */
    private function all(): Collection
    {
        return SubscriptionPlan::query()
            ->whereNull('deleted_at')
            ->where('metadata', 'like', '%"salla_plan_key"%')
            ->get();
    }

    private function metadata(string $key, array $definition): array
    {
        return [
            'salla_plan_key' => $key,
            'salla_plan_names' => $definition['names'],
            'tier_rank' => $definition['rank'],
            // Botzo pays for these replies, so the limit holds whatever `enable_ai_billing` says.
            'ai_limits_enforced' => '1',
            'ai_system_key_monthly_quota' => $definition['replies'],
            'ai_text_response_limit' => -1,
            'ai_audio_response_limit' => 0,
            'ai_organization_key_enabled' => false,
            'widget_voice_minutes_monthly' => 0,
            'widget_remove_branding' => '0',
            'message_limit' => 0,
            'contacts_limit' => -1,
            'team_limit' => 3,
            'canned_replies_limit' => 20,
            'campaign_limit' => 0,
            'branches_limit' => 1,
            'addons' => ['AI Assistant' => true, 'Embedded Signup' => false, 'Flow builder' => false],
            'flow_builder_advanced_enabled' => false,
        ];
    }
}
