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

    /**
     * Prices are in SAR a month and only recorded here: Salla collects the payment. `replies` is the
     * monthly AI reply limit and `daily` caps one day, so a traffic spike cannot use a month at once.
     */
    public const DEFINITIONS = [
        'free' => ['rank' => 1, 'en' => 'Salla Free', 'ar' => 'سلة المجانية', 'price' => 0, 'replies' => 100, 'daily' => 50, 'names' => ['free', 'مجانية', 'المجانية', 'مجاني', 'الباقة المجانية']],
        self::TRIAL => ['rank' => 2, 'en' => 'Salla Trial', 'ar' => 'تجربة سلة', 'price' => 0, 'replies' => 100, 'daily' => 50, 'names' => ['trial', 'trail', 'free trial', 'تجربة', 'تجربة مجانية']],
        self::FALLBACK => ['rank' => 3, 'en' => 'Salla Basic', 'ar' => 'سلة الأساسية', 'price' => 79, 'replies' => 1500, 'daily' => 300, 'names' => ['basic', 'starter', 'أساسية', 'الأساسية', 'الباقة الأساسية']],
        'growth' => ['rank' => 4, 'en' => 'Salla Growth', 'ar' => 'سلة النمو', 'price' => 199, 'replies' => 5000, 'daily' => 800, 'names' => ['growth', 'نمو', 'النمو', 'باقة النمو']],
        'pro' => ['rank' => 5, 'en' => 'Salla Pro', 'ar' => 'سلة الاحترافية', 'price' => 499, 'replies' => 15000, 'daily' => 2000, 'names' => ['pro', 'professional', 'احترافية', 'الاحترافية', 'الباقة الاحترافية']],
    ];

    /** What --sync rewrites on existing rows: the agreed prices and limits. Other edits stay. */
    private const SYNCED_KEYS = ['salla_plan_names', 'tier_rank', 'ai_system_key_monthly_quota', 'widget_daily_reply_limit'];

    /**
     * Creates missing plans. With $sync, existing plans also get the prices and limits above.
     *
     * @return array{created:int, updated:int}
     */
    public function setup(bool $sync = false): array
    {
        $existing = $this->all()->keyBy(fn (SubscriptionPlan $plan) => (string) $plan->metadataValue('salla_plan_key'));
        $created = 0;
        $updated = 0;

        foreach (self::DEFINITIONS as $key => $definition) {
            $plan = $existing->get($key);

            if (! $plan) {
                SubscriptionPlan::create([
                    'name' => $definition['en'],
                    'name_ar' => $definition['ar'],
                    'name_en' => $definition['en'],
                    'price' => $definition['price'],
                    'period' => 'monthly',
                    'status' => 'inactive',
                    'metadata' => json_encode($this->metadata($key, $definition), JSON_UNESCAPED_UNICODE),
                ]);
                $created++;

                continue;
            }

            if ($sync) {
                $wanted = array_intersect_key($this->metadata($key, $definition), array_flip(self::SYNCED_KEYS));
                $plan->update([
                    'price' => $definition['price'],
                    'metadata' => json_encode(array_replace($plan->metadataArray(), $wanted), JSON_UNESCAPED_UNICODE),
                ]);
                $updated++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
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
            'widget_daily_reply_limit' => $definition['daily'],
            'ai_text_response_limit' => -1,
            'ai_audio_response_limit' => 0,
            'ai_organization_key_enabled' => false,
            'widget_voice_minutes_monthly' => 0,
            'widget_remove_branding' => '0',
            // Zero would stop every reply and the merchant's inbox. Cost is capped by the reply quota above.
            'message_limit' => -1,
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
